<?php

session_start();
header("Content-Type: application/json");

$pseudo = $_SESSION['pseudo'] ?? null;
if (!$pseudo) {
    http_response_code(401);
    echo json_encode(["erreur" => "Non connecté"]);
    exit;
}

// Validate the CSRF token to prevent cross-site request forgery.
// See php/csrf.php for an explanation of how CSRF works.
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(["erreur" => "CSRF token invalid"]);
    exit;
}

$body = json_decode(file_get_contents("php://input"), true) ?? [];
$code = trim($body["code"] ?? "");

if (!$code || !preg_match('/^[A-Z0-9]{6}$/', $code)) {
    http_response_code(400);
    echo json_encode(["erreur" => "Code invalide"]);
    exit;
}

$fichier = "../data/partie_$code.json";
$lock    = "../data/partie_$code.lock";

// Verify that the requester is the game host
if (file_exists($fichier)) {
    $etat = json_decode(file_get_contents($fichier), true);
    if ($etat && $etat["hote"] !== $pseudo) {
        http_response_code(403);
        echo json_encode(["erreur" => "Seul l'hôte peut réinitialiser"]);
        exit;
    }
}

// Delete game files
if (file_exists($fichier)) {
    unlink($fichier);
}
if (file_exists($lock)) {
    unlink($lock);
}

// Remove from the index
$indexFichier = "../data/parties.json";
$indexLock    = fopen("../data/parties.lock", "c");
flock($indexLock, LOCK_EX);
if (file_exists($indexFichier)) {
    $index = json_decode(file_get_contents($indexFichier), true) ?? [];
    unset($index[$code]);
    file_put_contents($indexFichier, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
flock($indexLock, LOCK_UN);
fclose($indexLock);

echo json_encode(["ok" => true]);
