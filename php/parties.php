<?php

// ============================================================
//  parties.php — Lists available public games
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
$indexLockF   = "../data/parties.lock";

if (!file_exists($indexFichier)) {
    echo json_encode(["parties" => []]);
    exit;
}

// Lock the index file for reading and potential cleanup
$indexLock = fopen($indexLockF, "c");
flock($indexLock, LOCK_EX);

$index = json_decode(file_get_contents($indexFichier), true) ?? [];

// Remove ghost entries: the index still references a game whose file was deleted
$modifie = false;
foreach ($index as $code => $infos) {
    if (!file_exists("../data/partie_$code.json")) {
        unset($index[$code]);
        $modifie = true;
    }
}

// Clean up orphan game files: files that are no longer in the index (game ended)
// and are old enough that all clients have had time to read the final state.
// We wait 60 seconds before deleting to avoid a race condition where a client
// is still polling etat.php for the "fin" phase result.
foreach (glob("../data/partie_*.json") as $fichier) {
    // Skip recently modified files — a client might still be reading them
    if (time() - filemtime($fichier) < 60) {
        continue;
    }
    // Extract the game code from the filename (e.g. "partie_ABC123.json" -> "ABC123")
    if (!preg_match('/partie_([A-Z0-9]{6})\.json$/', $fichier, $matches)) {
        continue;
    }
    $fileCode = $matches[1];
    // Only delete if NOT in the index (meaning the game has ended and was removed)
    if (isset($index[$fileCode])) {
        continue;
    }
    $gameData = @json_decode(file_get_contents($fichier), true);
    if ($gameData && $gameData['phase'] === 'fin') {
        unlink($fichier);
        $lockFile = "../data/partie_$fileCode.lock";
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
        $modifie = true;
    }
}

if ($modifie) {
    file_put_contents($indexFichier, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
flock($indexLock, LOCK_UN);
fclose($indexLock);

// Return only public games that are still in the waiting phase
$publiques = array_values(array_filter(
    $index,
    fn ($p) =>
    $p["public"] === true && $p["phase"] === "attente"
));

echo json_encode(["parties" => $publiques]);
