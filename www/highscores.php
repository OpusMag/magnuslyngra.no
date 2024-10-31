<?php
$file = 'highscores.txt';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Read high scores
    if (file_exists($file)) {
        $data = file_get_contents($file);
        echo $data;
    } else {
        echo json_encode([]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update high scores
    $newScore = json_decode(file_get_contents('php://input'), true);
    if (file_exists($file)) {
        $data = file_get_contents($file);
        $highScores = json_decode($data, true);
    } else {
        $highScores = [];
    }

    $highScores[] = $newScore;
    usort($highScores, function($a, $b) {
        return $b['score'] - $a['score'];
    });

    if (count($highScores) > 10) {
        array_pop($highScores);
    }

    file_put_contents($file, json_encode($highScores));
    echo json_encode($highScores);
}
?>