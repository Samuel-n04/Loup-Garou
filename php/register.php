<?php
session_start();
header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true);
$pseudo = trim($data['pseudo'] ?? '');
$mail   = trim($data['email']  ?? '');
$mdp    = $data['mdp'] ?? '';

if (!$pseudo || !$mail || !$mdp) {
    echo json_encode(['error' => 'Champs manquants']);
    exit;
}

if (file_exists('../data/users.json')) {
    $users = json_decode(file_get_contents('../data/users.json'), true);
} else {
    $users = [];
}

if (!is_array($users)) $users = [];

if (isset($users[$pseudo])) {
    echo json_encode(['error' => 'pseudo existant']);
    exit;
}

if (in_array($mail, array_column(array_values($users), 'email'))) {
    echo json_encode(['error' => 'mail existant']);
    exit;
}

$users[$pseudo] = [
    'password'   => password_hash($mdp, PASSWORD_BCRYPT),
    'email'      => $mail,
    'created_at' => date('Y-m-d')
];

file_put_contents('../data/users.json', json_encode($users, JSON_PRETTY_PRINT));
$_SESSION['pseudo'] = $pseudo;
$_SESSION['mail']   = $mail;
echo json_encode(['status' => 'ok']);
