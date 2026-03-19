<?php
session_start();
header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true);
$pseudo = trim($data['pseudo'] ?? '');
$mail = trim($data['mail'] ?? '');
$mdp    = $data['mdp'] ?? '';

if (!$mail || !$mdp) {
    echo json_encode(['error' => 'Champs manquants']);
    exit;
}

if (file_exists('../data/users.json')) {
    $users = json_decode(file_get_contents('../data/users.json'), true);
} else {
    $users = [];
}

if (!is_array($users)) $users = [];


if (!isset($users[$mail])) {
    echo json_encode(['error' => 'Mail introuvable']);
    exit;
}

if (!password_verify($mdp, $users[$mail]['password'])) {
    echo json_encode(['error' => 'Mot de passe incorrect']);
    exit;
}

$_SESSION['mail'] = $mail;
$_SESSION['pseudo'] = $pseudo;

echo json_encode(['status' => 'ok']);
