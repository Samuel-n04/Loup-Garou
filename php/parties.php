<?php

// ============================================================
//  parties.php — Liste les parties disponibles
// ============================================================

session_start();
header("Content-Type: application/json");
header("Cache-Control: no-cache");

$pseudo = $_SESSION['pseudo'] ?? null;
if (!$pseudo) {
    http_response_code(401);
    echo json_encode(["erreur" => "Non connecté"]);
    exit;
}

$indexFichier = "../data/parties.json";

if (!file_exists($indexFichier)) {
    echo json_encode(["parties" => []]);
    exit;
}

$index = json_decode(file_get_contents($indexFichier), true) ?? [];

// Nettoyer les parties fantômes (fichier supprimé mais encore dans l'index)
$indexLock = fopen("../data/parties.lock", "w");
flock($indexLock, LOCK_EX);
$modifie = false;
foreach ($index as $code => $infos) {
    if (!file_exists("../data/partie_$code.json")) {
        unset($index[$code]);
        $modifie = true;
    }
}
if ($modifie) {
    file_put_contents($indexFichier, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
flock($indexLock, LOCK_UN);
fclose($indexLock);

// Retourner uniquement les parties publiques en attente
$publiques = array_values(array_filter(
    $index,
    fn ($p) =>
    $p["public"] === true && $p["phase"] === "attente"
));

echo json_encode(["parties" => $publiques]);
