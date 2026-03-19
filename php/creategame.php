<?php
// ============================================================
//  creategame.php — Crée une nouvelle partie
// ============================================================

session_start();
header("Content-Type: application/json");

$pseudo = $_SESSION['pseudo'] ?? null;
if (!$pseudo) {
    http_response_code(401);
    echo json_encode(["erreur" => "Non connecté"]);
    exit;
}

$fichierPartie = "../data/partie.json";
$fichierLock   = "../data/partie.lock";

// Vérifier qu'il n'y a pas déjà une partie en cours
if (file_exists($fichierPartie)) {
    $etatExistant = json_decode(file_get_contents($fichierPartie), true);
    if ($etatExistant && $etatExistant["phase"] !== "fin") {
        http_response_code(409);
        echo json_encode(["erreur" => "Une partie est déjà en cours."]);
        exit;
    }
}

$body = json_decode(file_get_contents("php://input"), true) ?? [];

// ── Rôles actifs (par défaut tous activés) ─────────────────
$rolesDisponibles = ["voyante", "sorciere", "chasseur", "cupidon", "petite-fille"];
$rolesActifs = $body["roles"] ?? $rolesDisponibles;

// Garder uniquement les rôles valides
$rolesActifs = array_values(array_intersect($rolesActifs, $rolesDisponibles));

// ── Nombre max de joueurs (4 à 20) ─────────────────────────
$joueurMax = intval($body["joueurMax"] ?? 20);
$joueurMax = max(4, min(20, $joueurMax));

// ── Créer l'état initial ───────────────────────────────────
$etat = [
    "phase"           => "attente",
    "tour"            => 0,
    "hote"            => $pseudo,
    "joueurMax"       => $joueurMax,
    "rolesActifs"     => $rolesActifs,
    "joueurs"         => [[
        "id"         => $pseudo,
        "nom"        => $pseudo,
        "role"       => null,
        "vivant"     => true,
        "amant"      => null,
        "potionVie"  => null,
        "potionMort" => null,
        "peutTirer"  => null,
    ]],
    "victime"         => null,
    "votesLoups"      => [],
    "votesJour"       => [],
    "prets"           => [],
    "vainqueur"       => null,
    "resultatVoyante" => null,
    "cupidonFait"     => false,
];

// Vérouiller avant écriture
$lock = fopen($fichierLock, "w");
flock($lock, LOCK_EX);

file_put_contents($fichierPartie, json_encode($etat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

flock($lock, LOCK_UN);
fclose($lock);

echo json_encode(["ok" => true, "hote" => $pseudo, "joueurMax" => $joueurMax, "rolesActifs" => $rolesActifs]);
