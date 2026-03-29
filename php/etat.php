<?php

session_start();
header("Content-Type: application/json");
header("Cache-Control: no-cache");

$idJoueur = $_SESSION['pseudo'] ?? null;
if (!$idJoueur) {
    http_response_code(401);
    echo json_encode(["erreur" => "Non connecté"]);
    exit;
}

$code = trim($_GET["code"] ?? "");
if (!$code || !preg_match('/^[A-Z0-9]{6}$/', $code)) {
    http_response_code(400);
    echo json_encode(["erreur" => "Code de partie invalide"]);
    exit;
}

$etat = lireJSON("../data/partie_$code.json");
if (!$etat) {
    http_response_code(404);
    echo json_encode(["erreur" => "Partie introuvable"]);
    exit;
}

$joueur = trouverDans($etat["joueurs"], fn ($j) => $j["id"] === $idJoueur);

$reponse = [
    "phase"     => $etat["phase"],
    "tour"      => $etat["tour"],
    "estHote"   => $etat["hote"] === $idJoueur,
    "monPseudo" => $idJoueur,
    "alerteEspionnage" => $etat["alerteEspionnage"] ?? false,
    "joueurs"   => array_map(function ($j) {
        $data = [
            "id"     => $j["id"],
            "nom"    => $j["nom"],
            "vivant" => $j["vivant"],
        ];
        if (!$j["vivant"]) {
            $data["role"] = $j["role"];
        }
        return $data;
    }, $etat["joueurs"]),
];

if ($joueur) {
    $reponse["monRole"]  = $joueur["role"];
    $reponse["vivant"]   = $joueur["vivant"];
    $reponse["estAmant"] = $joueur["amant"] !== null;
    $reponse["monAmant"] = $joueur["amant"];

    if ($joueur["role"] === "sorciere") {
        $reponse["potionVie"]  = $joueur["potionVie"];
        $reponse["potionMort"] = $joueur["potionMort"];
    }
    if ($joueur["role"] === "chasseur") {
        $reponse["peutTirer"] = $joueur["peutTirer"];
    }
    if ($joueur["role"] === "loup-garou" && $etat["phase"] === "nuit-loups") {
        $reponse["votesLoups"] = $etat["votesLoups"];
    }
    if ($etat["resultatVoyante"] && $joueur["role"] === "voyante") {
        $reponse["resultatVoyante"] = $etat["resultatVoyante"];
        $reponse["cibleVoyante"]    = $etat["cibleVoyante"] ?? null;
    }
}

if ($etat["phase"] === "nuit-sorciere" && $joueur && $joueur["role"] === "sorciere" && $etat["victime"]) {
    $reponse["victime"] = $etat["victime"];
}

if ($etat["resultatVoyante"] && $joueur && $joueur["role"] === "voyante") {
    $reponse["resultatVoyante"] = $etat["resultatVoyante"];
}

if (isset($etat["resultatEspionnage"]) && $joueur && $joueur["role"] === "petite-fille") {
    $reponse["resultatEspionnage"] = $etat["resultatEspionnage"];
}

if ($etat["phase"] === "vote") {
    $reponse["nbVotes"]   = count($etat["votesJour"]);
    $reponse["nbVivants"] = count(array_filter($etat["joueurs"], fn ($j) => $j["vivant"]));
    $reponse["votesJour"] = $etat["votesJour"]; // Pour montrer qui a voté contre qui
}

// ── Messages chat ──────────────────────────────────────────
$depuis = intval($_GET["depuis"] ?? 0);
$reponse["messages"] = array_values(array_filter(
    $etat["messages"] ?? [],
    fn ($m) => $m["ts"] > $depuis
));

if ($etat["phase"] === "fin") {
    $reponse["vainqueur"] = $etat["vainqueur"];
    $reponse["joueurs"]   = array_map(fn ($j) => [
        "id"     => $j["id"],
        "nom"    => $j["nom"],
        "vivant" => $j["vivant"],
        "role"   => $j["role"],
    ], $etat["joueurs"]);
}

echo json_encode($reponse);

function lireJSON(string $chemin): ?array
{
    $lockPath = str_replace(".json", ".lock", $chemin);
    // Créer le fichier de verrou s'il n'existe pas, sans le tronquer.
    // Le @ supprime les avertissements si le fichier existe déjà, ce qui est normal.
    @touch($lockPath); 
    
    $lockHandle = fopen($lockPath, "r");
    if (!$lockHandle) {
        // Impossible d'ouvrir le fichier de verrou, abandonner.
        return null;
    }

    // Demander un verrou partagé (LOCK_SH).
    // Le script attendra ici si un verrou exclusif (LOCK_EX) est détenu par un autre processus (comme action.php).
    // Plusieurs scripts peuvent détenir un verrou partagé en même temps.
    if (!flock($lockHandle, LOCK_SH)) {
        fclose($lockHandle);
        return null; // N'a pas pu obtenir le verrou
    }

    if (!file_exists($chemin)) {
        flock($lockHandle, LOCK_UN); // Libérer le verrou
        fclose($lockHandle);
        return null;
    }

    $contenu = file_get_contents($chemin);

    // Libérer le verrou dès que la lecture est terminée.
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    if ($contenu === false) {
        return null;
    }

    // Le décodage JSON se fait après avoir libéré le verrou.
    return json_decode($contenu, true);
}

function trouverDans(array $arr, callable $fn): ?array
{
    foreach ($arr as $item) {
        if ($fn($item)) {
            return $item;
        }
    }
    return null;
}
