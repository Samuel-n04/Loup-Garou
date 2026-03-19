<?php
// ============================================================
//  etat.php — Appelé toutes les secondes par le client (polling)
//  Retourne l'état filtré selon le joueur connecté
// ============================================================

session_start();
header("Content-Type: application/json");
header("Cache-Control: no-cache");

$idJoueur = $_SESSION['pseudo'] ?? null;
if (!$idJoueur) {
    http_response_code(401);
    echo json_encode(["erreur" => "Non connecté"]);
    exit;
}

$etat = lireJSON("../data/partie.json");
if (!$etat) {
    echo json_encode(["phase" => "attente"]);
    exit;
}

// ── Trouver le joueur connecté ─────────────────────────────
$joueur = array_find($etat["joueurs"], fn($j) => $j["id"] === $idJoueur);

// ── Réponse de base (publique) ─────────────────────────────
$reponse = [
    "phase"   => $etat["phase"],
    "tour"    => $etat["tour"],
    "estHote" => $etat["hote"] === $idJoueur,
    "joueurs" => array_map(fn($j) => [
        "id"     => $j["id"],
        "nom"    => $j["nom"],
        "vivant" => $j["vivant"],
    ], $etat["joueurs"]),
];

// ── Infos privées du joueur connecté ──────────────────────
if ($joueur) {
    $reponse["monRole"]  = $joueur["role"];
    $reponse["vivant"]   = $joueur["vivant"];
    $reponse["estAmant"] = $joueur["amant"] !== null;

    if ($joueur["role"] === "sorciere") {
        $reponse["potionVie"]  = $joueur["potionVie"];
        $reponse["potionMort"] = $joueur["potionMort"];
    }
    if ($joueur["role"] === "chasseur") {
        $reponse["peutTirer"] = $joueur["peutTirer"];
    }
}

// ── Victime de la nuit (sorcière uniquement) ───────────────
if (
    $etat["phase"] === "nuit-sorciere" &&
    $joueur && $joueur["role"] === "sorciere" &&
    $etat["victime"]
) {
    $reponse["victime"] = $etat["victime"];
}

// ── Résultat voyante (voyante uniquement) ──────────────────
if (
    $etat["resultatVoyante"] &&
    $joueur && $joueur["role"] === "voyante"
) {
    $reponse["resultatVoyante"] = $etat["resultatVoyante"];
}

// ── Votes en cours (pour affichage temps réel) ─────────────
if ($etat["phase"] === "vote") {
    $reponse["nbVotes"]    = count($etat["votesJour"]);
    $reponse["nbVivants"]  = count(array_filter($etat["joueurs"], fn($j) => $j["vivant"]));
}

// ── Fin de partie : révéler tous les rôles ─────────────────
if ($etat["phase"] === "fin") {
    $reponse["vainqueur"] = $etat["vainqueur"];
    $reponse["joueurs"]   = array_map(fn($j) => [
        "id"     => $j["id"],
        "nom"    => $j["nom"],
        "vivant" => $j["vivant"],
        "role"   => $j["role"],
    ], $etat["joueurs"]);
}

echo json_encode($reponse);

// ============================================================
//  UTILITAIRES
// ============================================================
function lireJSON(string $chemin): ?array {
    if (!file_exists($chemin)) return null;
    return json_decode(file_get_contents($chemin), true);
}

function array_find(array $arr, callable $fn): ?array {
    foreach ($arr as $item) { if ($fn($item)) return $item; }
    return null;
}
