<?php
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id    = trim($input['id'] ?? '');

if (!$id) {
    echo json_encode(['error' => 'id invalide']);
    exit;
}

if (file_exists('../data/games/' . $id . '.json')) {
    $game = json_decode(file_get_contents('../data/games/' . $id . '.json'), true);
} else {
    echo json_encode(['error' => 'Partie introuvable']);
    exit;
}

if (!is_array($game)) {
    echo json_encode(['error' => 'Partie corrompue']);
    exit;
}

if (count($game['players']) >= $game['max']) {
    echo json_encode(['error' => 'Partie pleine']);
    exit;
}

$game['players'][] = [
    'pseudo' => $_SESSION['pseudo'],
    'role'   => null,
    'alive'  => true
];

file_put_contents('../data/games/' . $id . '.json', json_encode($game, JSON_PRETTY_PRINT));

$_SESSION['game_id'] = $id;
echo json_encode(['status' => 'ok', 'game_id' => $id]);
