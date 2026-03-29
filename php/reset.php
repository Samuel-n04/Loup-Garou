<?php

session_start();
header("Content-Type: application/json");

$pseudo = $_SESSION['pseudo'] ?? null;
if (!$pseudo) {
    http_response_code(401);
    echo json_encode(["erreur" => "Non connecté"]);
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

// Vérifier que c'est bien l'hôte
if (file_exists($fichier)) {
    $etat = json_decode(file_get_contents($fichier), true);
    if ($etat && $etat["hote"] !== $pseudo) {
        http_response_code(403);
        echo json_encode(["erreur" => "Seul l'hôte peut réinitialiser"]);
        exit;
    }
}

// Supprimer les fichiers de la partie
if (file_exists($fichier)) {
    unlink($fichier);
}
if (file_exists($lock)) {
    unlink($lock);
}

// Retirer de l'index
$indexFichier = "../data/parties.json";
$indexLock    = fopen("../data/parties.lock", "w");
flock($indexLock, LOCK_EX);
if (file_exists($indexFichier)) {
    $index = json_decode(file_get_contents($indexFichier), true) ?? [];
    unset($index[$code]);
    file_put_contents($indexFichier, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
flock($indexLock, LOCK_UN);
fclose($indexLock);

echo json_encode(["ok" => true]);
