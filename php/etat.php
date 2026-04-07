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

// Build the response, filtering data the client is allowed to see.
// Players only get information relevant to their role and the current phase.
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
        // Reveal the role of dead players (common game rule: you see the role on death)
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

    // Only the witch knows about her potions
    if ($joueur["role"] === "sorciere") {
        $reponse["potionVie"]  = $joueur["potionVie"];
        $reponse["potionMort"] = $joueur["potionMort"];
    }
    // Only the hunter knows he can shoot
    if ($joueur["role"] === "chasseur") {
        $reponse["peutTirer"] = $joueur["peutTirer"];
    }
    // Werewolves see each other's votes during their night phase
    if ($joueur["role"] === "loup-garou" && $etat["phase"] === "nuit-loups") {
        $reponse["votesLoups"] = $etat["votesLoups"];
    }
    // Seer sees the result of her investigation
    if ($etat["resultatVoyante"] && $joueur["role"] === "voyante") {
        $reponse["resultatVoyante"] = $etat["resultatVoyante"];
        $reponse["cibleVoyante"]    = $etat["cibleVoyante"] ?? null;
    }
}

// Witch sees who the werewolves targeted this night
if ($etat["phase"] === "nuit-sorciere" && $joueur && $joueur["role"] === "sorciere" && $etat["victime"]) {
    $reponse["victime"] = $etat["victime"];
}

// Little girl sees the wolves she spied on
if (isset($etat["resultatEspionnage"]) && $joueur && $joueur["role"] === "petite-fille") {
    $reponse["resultatEspionnage"] = $etat["resultatEspionnage"];
}

// During the vote phase, everyone can see the vote count
if ($etat["phase"] === "vote") {
    $reponse["nbVotes"]   = count($etat["votesJour"]);
    $reponse["nbVivants"] = count(array_filter($etat["joueurs"], fn ($j) => $j["vivant"]));
    $reponse["votesJour"] = $etat["votesJour"];
}

// Chat messages — only send messages newer than the client's last known timestamp
// This avoids resending the entire chat history on every poll
$depuis = intval($_GET["depuis"] ?? 0);
$reponse["messages"] = array_values(array_filter(
    $etat["messages"] ?? [],
    fn ($m) => $m["ts"] > $depuis
));

// At game end, reveal all roles
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
    // Create the lock file if it doesn't exist, without truncating it.
    // 'c' mode: create if missing, don't truncate if existing.
    $lockHandle = fopen($lockPath, "c");
    if (!$lockHandle) {
        return null;
    }

    // Request a shared lock (LOCK_SH).
    // Multiple readers can hold a shared lock simultaneously.
    // This script will wait here if action.php holds an exclusive lock (LOCK_EX).
    if (!flock($lockHandle, LOCK_SH)) {
        fclose($lockHandle);
        return null;
    }

    if (!file_exists($chemin)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        return null;
    }

    $contenu = file_get_contents($chemin);

    // Release the lock as soon as reading is done
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    if ($contenu === false) {
        return null;
    }

    // JSON decoding happens after releasing the lock
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
