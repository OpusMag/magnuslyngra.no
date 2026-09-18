<?php
/**
 * Copy this file to tmdb-config.php on the server and fill in your keys.
 * tmdb-config.php is gitignored so the keys never end up in the repo.
 *
 * tmdb_key: free from https://www.themoviedb.org/settings/api
 *           (either the v3 API key or the v4 read access token)
 * omdb_key: optional, from https://www.omdbapi.com/apikey.aspx
 *           When set, the result card shows the real IMDb rating in
 *           addition to the TMDB score. Leave empty to skip it.
 *
 * Alternatively set the environment variables TMDB_API_KEY / OMDB_API_KEY
 * and skip this file entirely.
 */

return [
    'tmdb_key' => '',
    'omdb_key' => '',
];
