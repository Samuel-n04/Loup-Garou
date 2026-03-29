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

$fichierUsers = '../data/users.json';
$lockUsers    = '../data/users.lock';

$lock = fopen($lockUsers, 'w');
flock($lock, LOCK_EX);

if (file_exists($fichierUsers)) {
    $users = json_decode(file_get_contents($fichierUsers), true);
} else {
    $users = [];
}

if (!is_array($users)) {
    $users = [];
}

if (isset($users[$pseudo])) {
    flock($lock, LOCK_UN);
    fclose($lock);
    echo json_encode(['error' => 'pseudo existant']);
    exit;
}

if (in_array($mail, array_column(array_values($users), 'email'))) {
    flock($lock, LOCK_UN);
    fclose($lock);
    echo json_encode(['error' => 'mail existant']);
    exit;
}

$users[$pseudo] = [
    'password'   => password_hash($mdp, PASSWORD_BCRYPT),
    'email'      => $mail,
    'created_at' => date('Y-m-d')
];

file_put_contents($fichierUsers, json_encode($users, JSON_PRETTY_PRINT));
flock($lock, LOCK_UN);
fclose($lock);

$_SESSION['pseudo'] = $pseudo;
$_SESSION['mail']   = $mail;
echo json_encode(['status' => 'ok']);
