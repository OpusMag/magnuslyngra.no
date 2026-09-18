<?php
/**
 * TMDB proxy for serieroulette.
 * Keeps the API key server side and exposes only the handful of
 * read-only lookups the roulette page needs.
 *
 * Endpoints (GET):
 *   ?action=regions                                  -> countries with streaming data
 *   ?action=providers&region=NO&type=movie           -> streaming services in that country
 *   ?action=genres&type=movie                        -> genre list for movies/tv
 *   ?action=discover&type=movie&region=NO&providers=8|337&genres=27&score_min=6&score_max=10&page=3
 *   ?action=detail&type=movie&id=550                 -> overview, imdb id, runtime
 */

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    fail(405, 'Bare GET er støttet');
}

const TMDB_BASE = 'https://api.themoviedb.org/3';
// Kept outside the web root: the host serves this directory with nginx, which
// ignores .htaccess, so anything cached next to the script would be public.
define('CACHE_DIR', sys_get_temp_dir() . '/serieroulette-cache');
const CACHE_TTL_META = 86400;   // regions/providers/genres change rarely
const CACHE_TTL_LIST = 3600;    // discover/detail
const RATE_LIMIT_REQUESTS = 120;
const RATE_LIMIT_WINDOW = 60;
const DEFAULT_LANGUAGE = 'nb-NO';
const FALLBACK_LANGUAGE = 'en-US';

const ALLOWED_TYPES = ['movie', 'tv'];

/**
 * TMDB has no Norwegian genre translations - /genre/{type}/list returns
 * "name": null for every nb/no variant - so the labels are supplied here and
 * the English name is used for anything TMDB adds later.
 */
const GENRE_LABELS_NB = [
    12 => 'Eventyr',
    14 => 'Fantasy',
    16 => 'Animasjon',
    18 => 'Drama',
    27 => 'Skrekk',
    28 => 'Action',
    35 => 'Komedie',
    36 => 'Historie',
    37 => 'Western',
    53 => 'Thriller',
    80 => 'Krim',
    99 => 'Dokumentar',
    878 => 'Science fiction',
    9648 => 'Mysterium',
    10402 => 'Musikk',
    10749 => 'Romantikk',
    10751 => 'Familie',
    10752 => 'Krig',
    10759 => 'Action og eventyr',
    10762 => 'Barn',
    10763 => 'Nyheter',
    10764 => 'Reality',
    10765 => 'Sci-fi og fantasy',
    10766 => 'Såpeopera',
    10767 => 'Talkshow',
    10768 => 'Krig og politikk',
    10770 => 'TV-film',
];

function genreLabel(int $id, ?string $fallback): ?string {
    if (array_key_exists($id, GENRE_LABELS_NB)) {
        return GENRE_LABELS_NB[$id];
    }
    $fallback = trim((string) $fallback);
    return $fallback === '' ? null : $fallback;
}
const ALLOWED_SORTS = [
    'popularity.desc',
    'vote_average.desc',
    'vote_count.desc',
    'primary_release_date.desc',
    'first_air_date.desc',
];

function fail(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function ok($payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ------------------------------------------------------------------ config */

function tmdbConfig(): array {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $config = ['tmdb_key' => '', 'omdb_key' => ''];

    $file = __DIR__ . '/tmdb-config.php';
    if (is_readable($file)) {
        $fromFile = require $file;
        if (is_array($fromFile)) {
            $config = array_merge($config, $fromFile);
        }
    }

    foreach (['tmdb_key' => 'TMDB_API_KEY', 'omdb_key' => 'OMDB_API_KEY'] as $key => $env) {
        if (empty($config[$key])) {
            $value = getenv($env);
            if ($value !== false && $value !== '') {
                $config[$key] = $value;
            }
        }
    }

    return $config;
}

/* ----------------------------------------------------------- rate limiting */

function clientIP(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function checkRateLimit(): void {
    $file = CACHE_DIR . '/rate_limits.json';
    if (!ensureCacheDir()) {
        return; // no writable cache: skip limiting rather than break the page
    }

    $now = time();
    $limits = [];
    if (is_readable($file)) {
        $raw = file_get_contents($file);
        $limits = $raw ? (json_decode($raw, true) ?: []) : [];
    }

    $limits = array_filter($limits, function ($entry) use ($now) {
        return isset($entry['first']) && ($now - $entry['first']) < RATE_LIMIT_WINDOW;
    });

    // Hashed, never stored raw: the counter works the same and a leaked cache
    // file reveals no visitor addresses.
    $ip = substr(hash('sha256', clientIP() . '|serieroulette'), 0, 32);
    if (isset($limits[$ip])) {
        if ($limits[$ip]['count'] >= RATE_LIMIT_REQUESTS) {
            fail(429, 'For mange forespørsler, vent et øyeblikk');
        }
        $limits[$ip]['count']++;
    } else {
        $limits[$ip] = ['count' => 1, 'first' => $now];
    }

    file_put_contents($file, json_encode($limits), LOCK_EX);
}

/* ------------------------------------------------------------------ cache */

function ensureCacheDir(): bool {
    if (is_dir(CACHE_DIR)) {
        return is_writable(CACHE_DIR);
    }
    return @mkdir(CACHE_DIR, 0775, true) && is_writable(CACHE_DIR);
}

function cacheGet(string $key, int $ttl) {
    $path = CACHE_DIR . '/' . sha1($key) . '.json';
    if (!is_readable($path) || (time() - filemtime($path)) > $ttl) {
        return null;
    }
    $raw = file_get_contents($path);
    return $raw ? json_decode($raw, true) : null;
}

function cacheSet(string $key, $value): void {
    if (!ensureCacheDir()) {
        return;
    }
    file_put_contents(CACHE_DIR . '/' . sha1($key) . '.json', json_encode($value), LOCK_EX);
}

/* ------------------------------------------------------------------- http */

function httpGetJson(string $url, array $headers = [], bool $soft = false) {
    $headers[] = 'Accept: application/json';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'magnuslyngra.no-serieroulette/1.0',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            error_log('[serieroulette] cURL feilet: ' . $error);
            if ($soft) {
                return null;
            }
            fail(502, 'Klarte ikke å nå filmdatabasen');
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 12,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = 502;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
            }
        }
        if ($body === false) {
            if ($soft) {
                return null;
            }
            fail(502, 'Klarte ikke å nå filmdatabasen');
        }
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        if ($soft) {
            return null;
        }
        fail(502, 'Uventet svar fra filmdatabasen');
    }

    if ($status >= 400) {
        $detail = $decoded['status_message'] ?? 'ukjent feil';
        error_log('[serieroulette] HTTP ' . $status . ': ' . $detail);
        if ($soft) {
            return null;
        }
        fail($status === 401 ? 500 : $status, $status === 401
            ? 'API-nøkkelen mangler eller er ugyldig'
            : 'Filmdatabasen svarte med en feil');
    }

    return $decoded;
}

function tmdbGet(string $path, array $params = [], int $ttl = CACHE_TTL_LIST): array {
    $config = tmdbConfig();
    $key = $config['tmdb_key'];
    if ($key === '') {
        fail(500, 'API-nøkkel for TMDB er ikke satt opp på serveren');
    }

    $headers = [];
    // v4 read access tokens are JWTs and go in the Authorization header.
    if (strpos($key, 'eyJ') === 0) {
        $headers[] = 'Authorization: Bearer ' . $key;
    } else {
        $params['api_key'] = $key;
    }

    ksort($params);
    $url = TMDB_BASE . $path . '?' . http_build_query($params);

    $cacheKey = $path . '|' . http_build_query(array_diff_key($params, ['api_key' => 1]));
    $cached = cacheGet($cacheKey, $ttl);
    if ($cached !== null) {
        return $cached;
    }

    $data = httpGetJson($url, $headers);
    cacheSet($cacheKey, $data);
    return $data;
}

/* -------------------------------------------------------------- validation */

function paramType(): string {
    $type = $_GET['type'] ?? 'movie';
    if (!in_array($type, ALLOWED_TYPES, true)) {
        fail(400, 'Ugyldig type');
    }
    return $type;
}

function paramRegion(): string {
    $region = strtoupper(trim($_GET['region'] ?? ''));
    if (!preg_match('/^[A-Z]{2}$/', $region)) {
        fail(400, 'Ugyldig landkode');
    }
    return $region;
}

function paramIdList(string $name, string $separator): string {
    $raw = trim($_GET[$name] ?? '');
    if ($raw === '') {
        return '';
    }
    $ids = preg_split('/[|,]/', $raw);
    $clean = [];
    foreach ($ids as $id) {
        $id = trim($id);
        if ($id !== '' && ctype_digit($id)) {
            $clean[] = $id;
        }
    }
    if (count($clean) > 40) {
        $clean = array_slice($clean, 0, 40);
    }
    return implode($separator, $clean);
}

function paramScore(string $name, float $default): float {
    if (!isset($_GET[$name]) || $_GET[$name] === '') {
        return $default;
    }
    $value = (float) $_GET[$name];
    return max(0.0, min(10.0, $value));
}

function paramInt(string $name, int $default, int $min, int $max): int {
    if (!isset($_GET[$name]) || $_GET[$name] === '') {
        return $default;
    }
    return max($min, min($max, (int) $_GET[$name]));
}

/* ----------------------------------------------------------------- actions */

function actionRegions(): void {
    $data = tmdbGet('/watch/providers/regions', ['language' => DEFAULT_LANGUAGE], CACHE_TTL_META);
    $regions = [];
    foreach ($data['results'] ?? [] as $region) {
        $regions[] = [
            'code' => $region['iso_3166_1'],
            'name' => $region['native_name'] ?: $region['english_name'],
        ];
    }
    usort($regions, function ($a, $b) {
        return strcoll($a['name'], $b['name']);
    });
    ok(['regions' => $regions]);
}

function actionProviders(): void {
    $type = paramType();
    $region = paramRegion();
    $data = tmdbGet('/watch/providers/' . $type, [
        'language' => DEFAULT_LANGUAGE,
        'watch_region' => $region,
    ], CACHE_TTL_META);

    $providers = [];
    foreach ($data['results'] ?? [] as $provider) {
        $priority = $provider['display_priorities'][$region]
            ?? $provider['display_priority']
            ?? 999;
        $providers[] = [
            'id' => $provider['provider_id'],
            'name' => $provider['provider_name'],
            'logo' => $provider['logo_path'] ?? null,
            'priority' => $priority,
        ];
    }
    usort($providers, function ($a, $b) {
        return $a['priority'] === $b['priority']
            ? strcasecmp($a['name'], $b['name'])
            : $a['priority'] <=> $b['priority'];
    });

    ok(['providers' => $providers]);
}

function actionGenres(): void {
    $type = paramType();
    // Requested in English on purpose: the Norwegian response has null names.
    $data = tmdbGet('/genre/' . $type . '/list', ['language' => FALLBACK_LANGUAGE], CACHE_TTL_META);

    $genres = [];
    foreach ($data['genres'] ?? [] as $genre) {
        $name = genreLabel((int) $genre['id'], $genre['name'] ?? null);
        if ($name !== null) {
            $genres[] = ['id' => $genre['id'], 'name' => $name];
        }
    }
    // Case-insensitive so "Thriller" and "TV-film" sort as a reader expects.
    usort($genres, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    ok(['genres' => $genres]);
}

function actionDiscover(): void {
    $type = paramType();
    $region = paramRegion();
    $providers = paramIdList('providers', '|');
    $genres = paramIdList('genres', ',');
    $scoreMin = paramScore('score_min', 0.0);
    $scoreMax = paramScore('score_max', 10.0);
    if ($scoreMin > $scoreMax) {
        [$scoreMin, $scoreMax] = [$scoreMax, $scoreMin];
    }

    $sort = $_GET['sort'] ?? 'popularity.desc';
    if (!in_array($sort, ALLOWED_SORTS, true)) {
        $sort = 'popularity.desc';
    }

    $params = [
        'language' => DEFAULT_LANGUAGE,
        'watch_region' => $region,
        'with_watch_monetization_types' => 'flatrate',
        'include_adult' => 'false',
        'sort_by' => $sort,
        'page' => paramInt('page', 1, 1, 500),
        'vote_count.gte' => paramInt('votes_min', 40, 0, 10000),
        'vote_average.gte' => $scoreMin,
        'vote_average.lte' => $scoreMax,
    ];
    if ($providers !== '') {
        $params['with_watch_providers'] = $providers;
    }
    if ($genres !== '') {
        $params['with_genres'] = $genres;
    }

    $data = tmdbGet('/discover/' . $type, $params);

    $items = [];
    foreach ($data['results'] ?? [] as $item) {
        $date = $type === 'movie'
            ? ($item['release_date'] ?? '')
            : ($item['first_air_date'] ?? '');
        $items[] = [
            'id' => $item['id'],
            'type' => $type,
            'title' => $type === 'movie'
                ? ($item['title'] ?? $item['original_title'] ?? '')
                : ($item['name'] ?? $item['original_name'] ?? ''),
            'year' => $date !== '' ? substr($date, 0, 4) : '',
            'poster' => $item['poster_path'] ?? null,
            'backdrop' => $item['backdrop_path'] ?? null,
            'score' => round((float) ($item['vote_average'] ?? 0), 1),
            'votes' => (int) ($item['vote_count'] ?? 0),
            'overview' => trim($item['overview'] ?? ''),
        ];
    }

    ok([
        'page' => $data['page'] ?? 1,
        'total_pages' => min((int) ($data['total_pages'] ?? 0), 500),
        'total_results' => (int) ($data['total_results'] ?? 0),
        'results' => $items,
    ]);
}

function omdbRating(string $imdbId): ?string {
    $config = tmdbConfig();
    if (empty($config['omdb_key']) || $imdbId === '') {
        return null;
    }

    $cacheKey = 'omdb|' . $imdbId;
    $cached = cacheGet($cacheKey, CACHE_TTL_META);
    if ($cached !== null) {
        return $cached['rating'] ?? null;
    }

    $url = 'https://www.omdbapi.com/?' . http_build_query([
        'i' => $imdbId,
        'apikey' => $config['omdb_key'],
    ]);
    $data = httpGetJson($url, [], true);
    $rating = is_array($data) && isset($data['imdbRating']) && $data['imdbRating'] !== 'N/A'
        ? $data['imdbRating']
        : null;

    cacheSet($cacheKey, ['rating' => $rating]);
    return $rating;
}

function actionDetail(): void {
    $type = paramType();
    $id = paramInt('id', 0, 1, PHP_INT_MAX);
    if ($id === 0) {
        fail(400, 'Mangler id');
    }
    $region = strtoupper(trim($_GET['region'] ?? ''));
    if (!preg_match('/^[A-Z]{2}$/', $region)) {
        $region = '';
    }

    $data = tmdbGet('/' . $type . '/' . $id, [
        'language' => DEFAULT_LANGUAGE,
        'append_to_response' => 'external_ids,watch/providers',
    ], CACHE_TTL_META);

    $overview = trim($data['overview'] ?? '');
    $overviewLang = 'nb';
    if ($overview === '') {
        $fallback = tmdbGet('/' . $type . '/' . $id, ['language' => FALLBACK_LANGUAGE], CACHE_TTL_META);
        $overview = trim($fallback['overview'] ?? '');
        $overviewLang = 'en';
    }

    $date = $type === 'movie'
        ? ($data['release_date'] ?? '')
        : ($data['first_air_date'] ?? '');

    $genres = [];
    foreach ($data['genres'] ?? [] as $genre) {
        $name = genreLabel((int) $genre['id'], $genre['name'] ?? null);
        if ($name !== null) {
            $genres[] = $name;
        }
    }

    $imdbId = $data['external_ids']['imdb_id'] ?? ($data['imdb_id'] ?? '');

    $streaming = [];
    if ($region !== '') {
        $flatrate = $data['watch/providers']['results'][$region]['flatrate'] ?? [];
        foreach ($flatrate as $provider) {
            $streaming[] = [
                'name' => $provider['provider_name'],
                'logo' => $provider['logo_path'] ?? null,
            ];
        }
    }

    ok([
        'id' => $data['id'],
        'type' => $type,
        'title' => $type === 'movie'
            ? ($data['title'] ?? $data['original_title'] ?? '')
            : ($data['name'] ?? $data['original_name'] ?? ''),
        'original_title' => $type === 'movie'
            ? ($data['original_title'] ?? '')
            : ($data['original_name'] ?? ''),
        'year' => $date !== '' ? substr($date, 0, 4) : '',
        'poster' => $data['poster_path'] ?? null,
        'backdrop' => $data['backdrop_path'] ?? null,
        'score' => round((float) ($data['vote_average'] ?? 0), 1),
        'votes' => (int) ($data['vote_count'] ?? 0),
        'overview' => $overview,
        'overview_language' => $overviewLang,
        'genres' => $genres,
        'runtime' => $type === 'movie'
            ? ($data['runtime'] ?? null)
            : ($data['episode_run_time'][0] ?? null),
        'seasons' => $type === 'tv' ? ($data['number_of_seasons'] ?? null) : null,
        'episodes' => $type === 'tv' ? ($data['number_of_episodes'] ?? null) : null,
        'imdb_id' => $imdbId,
        'imdb_rating' => $imdbId ? omdbRating($imdbId) : null,
        'streaming' => $streaming,
    ]);
}

/* ------------------------------------------------------------------- route */

checkRateLimit();

switch ($_GET['action'] ?? '') {
    case 'regions':
        actionRegions();
        break;
    case 'providers':
        actionProviders();
        break;
    case 'genres':
        actionGenres();
        break;
    case 'discover':
        actionDiscover();
        break;
    case 'detail':
        actionDetail();
        break;
    default:
        fail(400, 'Ukjent handling');
}
