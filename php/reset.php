<?php
session_start();
header("Content-Type: application/json");

// Seul un joueur connecté peut réinitialiser
$pseudo = $_SESSION['pseudo'] ?? null;
if (!$pseudo) {
    http_response_code(401);
    echo json_encode(["erreur" => "Non connecté"]);
    exit;
}

$fichier = "../data/partie.json";
$lock    = "../data/partie.lock";

// Vérifier que c'est bien l'hôte qui réinitialise
if (file_exists($fichier)) {
    $etat = json_decode(file_get_contents($fichier), true);
    if ($etat && $etat["hote"] !== $pseudo) {
        http_response_code(403);
        echo json_encode(["erreur" => "Seul l'hôte peut réinitialiser la partie"]);
        exit;
    }
}

// Supprimer les fichiers de partie
if (file_exists($fichier)) unlink($fichier);
if (file_exists($lock))    unlink($lock);

echo json_encode(["ok" => true]);
