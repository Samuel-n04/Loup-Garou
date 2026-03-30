<?php

session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$mail = trim($data['mail'] ?? '');
$mdp  = $data['mdp'] ?? '';

if (!$mail || !$mdp) {
    echo json_encode(['error' => 'Champs manquants']);
    exit;
}

$fichierUsers = '../data/users.json';
$lockUsers    = '../data/users.lock';

// Open (or create) the lock file using 'c' mode:
// 'c' creates the file if it doesn't exist without truncating it if it does.
// This is safer than 'r' (fails if file missing) and 'w' (always truncates).
// LOCK_SH = shared lock, multiple readers can hold it at the same time.
$lock = fopen($lockUsers, 'c');
flock($lock, LOCK_SH);

if (file_exists($fichierUsers)) {
    $users = json_decode(file_get_contents($fichierUsers), true);
} else {
    $users = [];
}

if (!is_array($users)) {
    $users = [];
}

$pseudo = null;
foreach ($users as $p => $infos) {
    if ($infos['email'] === $mail) {
        $pseudo = $p;
        break;
    }
}

if ($pseudo === null) {
    flock($lock, LOCK_UN);
    fclose($lock);
    echo json_encode(['error' => 'Mail introuvable']);
    exit;
}

if (!password_verify($mdp, $users[$pseudo]['password'])) {
    flock($lock, LOCK_UN);
    fclose($lock);
    echo json_encode(['error' => 'Mot de passe incorrect']);
    exit;
}

flock($lock, LOCK_UN);
fclose($lock);

$_SESSION['pseudo'] = $pseudo;
$_SESSION['mail']   = $mail;
echo json_encode(['status' => 'ok']);
