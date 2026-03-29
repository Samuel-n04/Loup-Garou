<?php

// ============================================================
//  action.php — Reçoit les actions des joueurs (POST JSON)
// ============================================================

session_start();
header("Content-Type: application/json");

$idJoueur = $_SESSION['pseudo'] ?? null;
if (!$idJoueur) {
    http_response_code(401);
    echo json_encode(["erreur" => "Non connecté"]);
    exit;
}

$body   = json_decode(file_get_contents("php://input"), true);
$action = $body["action"] ?? null;
$code   = trim($body["code"] ?? "");

if (!$action) {
    http_response_code(400);
    echo json_encode(["erreur" => "action manquante"]);
    exit;
}

if (!$code || !preg_match('/^[A-Z0-9]{6}$/', $code)) {
    http_response_code(400);
    echo json_encode(["erreur" => "Code de partie invalide"]);
    exit;
}

$fichierPartie = "../data/partie_$code.json";
$fichierLock   = "../data/partie_$code.lock";

$lock = fopen($fichierLock, "w");
flock($lock, LOCK_EX);

$etat = lireJSON($fichierPartie);

if (!$etat) {
    flock($lock, LOCK_UN);
    fclose($lock);
    http_response_code(404);
    echo json_encode(["erreur" => "Partie introuvable."]);
    exit;
}
$erreur = null;

switch ($action) {

    case "rejoindre":
        if (trouverDans($etat["joueurs"], fn ($j) => $j["id"] === $idJoueur)) {
            $erreur = "Joueur déjà inscrit";
            break;
        }
        $etat["joueurs"][] = [
            "id"         => $idJoueur,
            "nom"        => $idJoueur,
            "role"       => null,
            "vivant"     => true,
            "amant"      => null,
            "potionVie"  => null,
            "potionMort" => null,
            "peutTirer"  => null,
        ];
        break;

    case "demarrer":
        if ($etat["hote"] !== $idJoueur) {
            $erreur = "Non autorisé";
            break;
        }
        if (count($etat["joueurs"]) < 2) {
            $erreur = "Minimum 2 joueurs";
            break;
        }
        if ($etat["phase"] !== "attente") {
            $erreur = "Partie déjà lancée";
            break;
        }
        $etat = distribuerRoles($etat);
        $etat["phase"] = "distribution";
        break;

    case "pret":
        $etat["prets"][$idJoueur] = true;
        if (count($etat["prets"]) >= count($etat["joueurs"])) {
            $etat["prets"] = [];
            $etat = demarrerNuit($etat);
        }
        break;

    case "cupidon":
        if ($etat["phase"] !== "nuit-cupidon") {
            $erreur = "Mauvaise phase";
            break;
        }
        $joueur = findJoueur($etat, $idJoueur);
        if ($joueur["role"] !== "cupidon") {
            $erreur = "Non autorisé";
            break;
        }
        $etat = lierAmants($etat, $body["idA"], $body["idB"]);
        $etat["phase"] = "nuit-voyante";
        break;

    case "voyante":
        if ($etat["phase"] !== "nuit-voyante") {
            $erreur = "Mauvaise phase";
            break;
        }
        $joueur = findJoueur($etat, $idJoueur);
        if ($joueur["role"] !== "voyante") {
            $erreur = "Non autorisé";
            break;
        }
        $cible = findJoueur($etat, $body["idCible"]);
        $etat["resultatVoyante"] = $cible["role"];
        $etat["phase"] = "nuit-loups";
        break;

    case "loupVote":
        if ($etat["phase"] !== "nuit-loups") {
            $erreur = "Mauvaise phase";
            break;
        }
        $joueur = findJoueur($etat, $idJoueur);
        if ($joueur["role"] !== "loup-garou") {
            $erreur = "Non autorisé";
            break;
        }
        $etat["votesLoups"][$idJoueur] = $body["idCible"];
        $loups = array_filter($etat["joueurs"], fn ($j) => $j["role"] === "loup-garou" && $j["vivant"]);
        if (count($etat["votesLoups"]) >= count($loups)) {
            $etat["victime"]    = majoritéVotes($etat["votesLoups"]);
            $etat["votesLoups"] = [];
            $hasSorciere = (bool) trouverDans($etat["joueurs"], fn ($j) => $j["role"] === "sorciere" && $j["vivant"]);
            if ($hasSorciere) {
                $etat["phase"] = "nuit-sorciere";
            } else {
                $etat = finNuit($etat);
            }
        }
        break;

    case "sorciere":
        if ($etat["phase"] !== "nuit-sorciere") {
            $erreur = "Mauvaise phase";
            break;
        }
        $joueur = findJoueur($etat, $idJoueur);
        if ($joueur["role"] !== "sorciere") {
            $erreur = "Non autorisé";
            break;
        }
        if (!empty($body["utiliserVie"]) && $joueur["potionVie"] && $etat["victime"]) {
            $victime = &findJoueurRef($etat, $etat["victime"]);
            $victime["vivant"] = true;
            $etat["victime"]   = null;
            $sorciere = &findJoueurRef($etat, $idJoueur);
            $sorciere["potionVie"] = false;
        }
        if (!empty($body["idCibleMort"])) {
            $cible = &findJoueurRef($etat, $body["idCibleMort"]);
            $cible["vivant"] = false;
            $sorciere = &findJoueurRef($etat, $idJoueur);
            $sorciere["potionMort"] = false;
        }
        $etat["resultatVoyante"] = null;
        $etat = finNuit($etat);
        break;

    case "finNuit":
        if ($etat["phase"] !== "fin-nuit") {
            $erreur = "Mauvaise phase";
            break;
        }
        $etat = finNuit($etat);
        break;

    case "demarrerVote":
        if ($etat["hote"] !== $idJoueur) {
            $erreur = "Non autorisé";
            break;
        }
        if ($etat["phase"] !== "jour") {
            $erreur = "Mauvaise phase";
            break;
        }
        $etat["votesJour"] = [];
        $etat["phase"]     = "vote";
        break;

    case "vote":
        if ($etat["phase"] !== "vote") {
            $erreur = "Mauvaise phase";
            break;
        }
        $joueur = findJoueur($etat, $idJoueur);
        if (!$joueur["vivant"]) {
            $erreur = "Joueur mort";
            break;
        }
        $etat["votesJour"][$idJoueur] = $body["idCible"];
        $vivants = array_filter($etat["joueurs"], fn ($j) => $j["vivant"]);
        if (count($etat["votesJour"]) >= count($vivants)) {
            $etat = depouiller($etat);
        }
        break;

    case "chasseurTire":
        if ($etat["phase"] !== "chasseur") {
            $erreur = "Mauvaise phase";
            break;
        }
        $joueur = findJoueur($etat, $idJoueur);
        if ($joueur["role"] !== "chasseur") {
            $erreur = "Non autorisé";
            break;
        }
        $cible = &findJoueurRef($etat, $body["idCible"]);
        $cible["vivant"] = false;
        $chasseur = &findJoueurRef($etat, $idJoueur);
        $chasseur["peutTirer"] = false;
        $etat = apresElimination($etat);
        break;

    case "quitter":
        $estHote = $etat["hote"] === $idJoueur;
        $enCours = $etat["phase"] !== "attente";
        $etat["joueurs"] = array_values(array_filter(
            $etat["joueurs"],
            fn ($j) => $j["id"] !== $idJoueur
        ));
        if ($estHote && $enCours) {
            $etat["phase"]     = "fin";
            $etat["vainqueur"] = "annule";
        } elseif ($estHote && count($etat["joueurs"]) > 0) {
            $etat["hote"] = $etat["joueurs"][0]["id"];
        } elseif ($estHote && count($etat["joueurs"]) === 0) {
            $etat["phase"]     = "fin";
            $etat["vainqueur"] = "annule";
        }
        break;

    case "chat":
        $texte = trim($body["texte"] ?? "");
        if (!$texte) {
            $erreur = "Message vide";
            break;
        }
        if (strlen($texte) > 200) {
            $erreur = "Message trop long";
            break;
        }
        $etat["messages"][] = [
            "auteur" => $idJoueur,
            "texte"  => $texte,
            "ts"     => time(),
        ];
        if (count($etat["messages"]) > 100) {
            $etat["messages"] = array_slice($etat["messages"], -100);
        }
        break;

    default:
        $erreur = "Action inconnue : $action";
}

if (!$erreur) {
    ecrireJSON($fichierPartie, $etat);
    mettreAJourIndex($code, $etat["phase"], count($etat["joueurs"]));
}

flock($lock, LOCK_UN);
fclose($lock);

if ($erreur) {
    http_response_code(400);
    echo json_encode(["erreur" => $erreur]);
} else {
    echo json_encode(["ok" => true, "phase" => $etat["phase"]]);
}

// ============================================================
//  LOGIQUE DE JEU
// ============================================================
function distribuerRoles(array $etat): array
{
    $n     = count($etat["joueurs"]);
    $roles = genererRoles($n, $etat["rolesActifs"] ?? []);
    shuffle($roles);
    foreach ($etat["joueurs"] as $i => &$j) {
        $j["role"] = $roles[$i];
        if ($j["role"] === "sorciere") {
            $j["potionVie"] = true;
            $j["potionMort"] = true;
        }
        if ($j["role"] === "chasseur") {
            $j["peutTirer"] = true;
        }
    }
    $etat["tour"] = 1;
    return $etat;
}

function genererRoles(int $n, array $rolesActifs = []): array
{
    $roles   = [];
    $nbLoups = $n >= 10 ? 3 : ($n >= 7 ? 2 : 1);
    for ($i = 0; $i < $nbLoups; $i++) {
        $roles[] = "loup-garou";
    }
    $speciaux = !empty($rolesActifs) ? $rolesActifs : ["voyante", "sorciere", "chasseur", "cupidon", "petite-fille"];
    foreach ($speciaux as $r) {
        if (count($roles) < $n) {
            $roles[] = $r;
        }
    }
    while (count($roles) < $n) {
        $roles[] = "villageois";
    }
    return $roles;
}

function demarrerNuit(array $etat): array
{
    $etat["victime"]    = null;
    $etat["votesLoups"] = [];
    $hasCupidon = (bool) trouverDans($etat["joueurs"], fn ($j) => $j["role"] === "cupidon" && $j["vivant"]);
    if ($etat["tour"] === 1 && $hasCupidon && !($etat["cupidonFait"] ?? false)) {
        $etat["phase"] = "nuit-cupidon";
    } else {
        $hasVoyante = (bool) trouverDans($etat["joueurs"], fn ($j) => $j["role"] === "voyante" && $j["vivant"]);
        $etat["phase"] = $hasVoyante ? "nuit-voyante" : "nuit-loups";
    }
    return $etat;
}

function finNuit(array $etat): array
{
    if ($etat["victime"]) {
        $victime = &findJoueurRef($etat, $etat["victime"]);
        $victime["vivant"] = false;
        if ($victime["amant"]) {
            $amant = &findJoueurRef($etat, $victime["amant"]);
            $amant["vivant"] = false;
        }
    }
    $etat["victime"] = null;
    $fin = verifierFin($etat);
    if ($fin) {
        $etat["phase"] = "fin";
        $etat["vainqueur"] = $fin;
        return $etat;
    }
    $etat["phase"] = "jour";
    return $etat;
}

function depouiller(array $etat): array
{
    $idElimine         = majoritéVotes($etat["votesJour"]);
    $etat["votesJour"] = [];
    $elimine           = &findJoueurRef($etat, $idElimine);
    if ($elimine["role"] === "chasseur" && $elimine["peutTirer"]) {
        $elimine["vivant"] = false;
        $etat["phase"]     = "chasseur";
        return $etat;
    }
    $elimine["vivant"] = false;
    if ($elimine["amant"]) {
        $amant = &findJoueurRef($etat, $elimine["amant"]);
        $amant["vivant"] = false;
    }
    return apresElimination($etat);
}

function apresElimination(array $etat): array
{
    $fin = verifierFin($etat);
    if ($fin) {
        $etat["phase"] = "fin";
        $etat["vainqueur"] = $fin;
        return $etat;
    }
    $etat["tour"]++;
    return demarrerNuit($etat);
}

function lierAmants(array $etat, string $idA, string $idB): array
{
    foreach ($etat["joueurs"] as &$j) {
        if ($j["id"] === $idA) {
            $j["amant"] = $idB;
        }
        if ($j["id"] === $idB) {
            $j["amant"] = $idA;
        }
    }
    $etat["cupidonFait"] = true;
    return $etat;
}

function majoritéVotes(array $votes): string
{
    $comptage = [];
    foreach ($votes as $cible) {
        $comptage[$cible] = ($comptage[$cible] ?? 0) + 1;
    }
    arsort($comptage);
    return array_key_first($comptage);
}

function verifierFin(array $etat): ?string
{
    $vivants = array_filter($etat["joueurs"], fn ($j) => $j["vivant"]);

    // La condition des amants est prioritaire
    $amants = array_filter($vivants, fn ($j) => $j["amant"] !== null);
    if (count($amants) === 2 && count($vivants) === 2) {
        return "amants";
    }

    $loups   = array_filter($vivants, fn ($j) => $j["role"] === "loup-garou");
    $autres  = array_filter($vivants, fn ($j) => $j["role"] !== "loup-garou");
    if (count($loups) === 0) {
        return "villageois";
    }
    if (count($loups) >= count($autres)) {
        return "loups";
    }
    
    return null;
}

// ============================================================
//  UTILITAIRES
// ============================================================
function lireJSON(string $chemin): ?array
{
    if (!file_exists($chemin)) {
        return null;
    }
    return json_decode(file_get_contents($chemin), true);
}

function ecrireJSON(string $chemin, array $data): void
{
    file_put_contents($chemin, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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

function findJoueur(array $etat, string $id): array
{
    return trouverDans($etat["joueurs"], fn ($j) => $j["id"] === $id)
        ?? throw new Exception("Joueur $id introuvable");
}

function &findJoueurRef(array &$etat, string $id): mixed
{
    foreach ($etat["joueurs"] as &$j) {
        if ($j["id"] === $id) {
            return $j;
        }
    }
    throw new Exception("Joueur $id introuvable");
}

function mettreAJourIndex(string $code, string $phase, int $nbJoueurs): void
{
    $fichier = "../data/parties.json";
    $lock    = fopen("../data/parties.lock", "w");
    flock($lock, LOCK_EX);

    $index = file_exists($fichier)
        ? json_decode(file_get_contents($fichier), true) ?? []
        : [];

    if ($phase === "fin") {
        unset($index[$code]);
        /* 
        // NOTE: On ne supprime plus les fichiers de partie ici pour éviter une race condition
        // où les clients essaient de poller un état final alors que le fichier a déjà été supprimé.
        // Un nettoyage périodique (cron job) serait une meilleure approche.
        $fichierPartie = "../data/partie_$code.json";
        $fichierLock   = "../data/partie_$code.lock";
        if (file_exists($fichierPartie)) {
            unlink($fichierPartie);
        }
        if (file_exists($fichierLock)) {
            unlink($fichierLock);
        }
        */
    } elseif (isset($index[$code])) {
        $index[$code]["phase"]     = $phase;
        $index[$code]["nbJoueurs"] = $nbJoueurs;
    }

    file_put_contents($fichier, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($lock, LOCK_UN);
    fclose($lock);
}
