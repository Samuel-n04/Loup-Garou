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
            "decouverte" => null,
            "aEspionne"  => null,
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
        $hasVoyante = (bool) trouverDans($etat["joueurs"], fn ($j) => $j["role"] === "voyante" && $j["vivant"]);
        $etat["phase"] = $hasVoyante ? "nuit-voyante" : prochainAvantLoups($etat);
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
        $etat["cibleVoyante"] = $body["idCible"];
        $etat["phase"] = prochainAvantLoups($etat);
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
            $etat["victime"] = null;
            $joueurRef = &findJoueurRef($etat, $idJoueur);
            $joueurRef["potionVie"] = false;
        }

        if (!empty($body["idCibleMort"]) && $joueur["potionMort"]) {
            tuerJoueur($etat, $body["idCibleMort"]);
            $joueurRef = &findJoueurRef($etat, $idJoueur);
            $joueurRef["potionMort"] = false;
        }

        // On ne remet PLUS resultatVoyante à null ici
        $etat = finNuit($etat);
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

            $hasSorciere = (bool) trouverDans(
                $etat["joueurs"],
                fn ($j) =>
                $j["role"] === "sorciere" && $j["vivant"]
            );

            if ($hasSorciere) {
                $etat["phase"] = "nuit-sorciere";
            } else {
                $etat = finNuit($etat);
            }
        }
        break;

    case "petiteFilleEspionne":
        if ($etat["phase"] !== "nuit-petite-fille") {
            $erreur = "Mauvaise phase";
            break;
        }
        $joueur = &findJoueurRef($etat, $idJoueur);
        if ($joueur["role"] !== "petite-fille" || !$joueur["vivant"]) {
            $erreur = "Non autorisé";
            break;
        }

        // 1 chance sur 3 d'être repérée
        $decouverte = rand(1, 3) === 1;

        if ($decouverte) {
            // Repérée : les loups l'éliminent à sa place, les votes loups sont annulés
            $etat["victime"] = $joueur["id"];
            $etat["resultatEspionnage"] = ["decouverte" => true];

            $hasSorciere = (bool) trouverDans(
                $etat["joueurs"],
                fn ($j) => $j["role"] === "sorciere" && $j["vivant"]
            );
            if ($hasSorciere) {
                $etat["phase"] = "nuit-sorciere";
            } else {
                $etat = finNuit($etat);
            }
        } else {
            // Non repérée : révèle les identités des loups, le tour des loups commence
            $loups = array_values(array_filter(
                $etat["joueurs"],
                fn ($j) => $j["role"] === "loup-garou" && $j["vivant"]
            ));
            $etat["resultatEspionnage"] = [
                "loups" => array_map(fn ($l) => $l["id"], $loups)
            ];
            $etat["alerteEspionnage"] = true; // signal aux loups que la petite fille les observe
            $etat["phase"] = "nuit-loups";
        }
        break;

    case "petiteFillePasser":
        if ($etat["phase"] !== "nuit-petite-fille") {
            $erreur = "Mauvaise phase";
            break;
        }
        $joueur = findJoueur($etat, $idJoueur);
        if ($joueur["role"] !== "petite-fille" || !$joueur["vivant"]) {
            $erreur = "Non autorisé";
            break;
        }
        $etat["phase"] = "nuit-loups";
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
        $etat["phase"] = "vote";
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
            $idElimine = majoritéVotes($etat["votesJour"]);
            $nomElimine = findJoueur($etat, $idElimine)["nom"];
            $roleElimine = findJoueur($etat, $idElimine)["role"];
            
            $etat["messages"][] = [
                "auteur" => "Narrateur",
                "texte"  => "Le village a décidé d'éliminer $nomElimine. Il était " . nomRole($roleElimine) . ".",
                "ts"     => time(),
            ];
            
            $etat["votesJour"] = [];
            
            $elimine = findJoueur($etat, $idElimine);
            if ($elimine["role"] === "chasseur" && $elimine["peutTirer"]) {
                tuerJoueur($etat, $idElimine);
                $etat["phase"] = "chasseur";
            } else {
                tuerJoueur($etat, $idElimine);
                $etat = apresElimination($etat);
            }
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

        $cible = findJoueur($etat, $body["idCible"]);
        $etat["messages"][] = [
            "auteur" => "Narrateur",
            "texte"  => "Le Chasseur abat " . $cible["nom"] . " (" . nomRole($cible["role"]) . ") dans son dernier souffle !",
            "ts"     => time(),
        ];

        tuerJoueur($etat, $body["idCible"]);

        $joueurRef = &findJoueurRef($etat, $idJoueur);
        $joueurRef["peutTirer"] = false;

        // Si le chasseur a été tué de nuit, la partie continue sur le jour (pas une nouvelle nuit)
        if ($etat["chasseurDeNuit"] ?? false) {
            $etat["chasseurDeNuit"] = false;
            $fin = verifierFin($etat);
            if ($fin) {
                $etat["phase"] = "fin";
                $etat["vainqueur"] = $fin;
            } else {
                $etat["phase"] = "jour";
            }
        } else {
            $etat = apresElimination($etat);
        }
        break;

    case "quitter":
        $estHote = $etat["hote"] === $idJoueur;
        $enCours = $etat["phase"] !== "attente";

        $etat["joueurs"] = array_values(array_filter(
            $etat["joueurs"],
            fn ($j) => $j["id"] !== $idJoueur
        ));

        if ($estHote && $enCours) {
            $etat["phase"] = "fin";
            $etat["vainqueur"] = "annule";
        } elseif ($estHote && count($etat["joueurs"]) > 0) {
            $etat["hote"] = $etat["joueurs"][0]["id"];
        } elseif ($estHote && count($etat["joueurs"]) === 0) {
            $etat["phase"] = "fin";
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
    foreach ($etat["joueurs"] as &$j) {
        if (isset($j["decouverte"])) {
            $j["decouverte"] = false;
        }
    }
    $etat["victime"]           = null;
    $etat["votesLoups"]        = [];
    $etat["alerteEspionnage"]  = false;
    $etat["resultatEspionnage"] = null;
    $hasCupidon = (bool) trouverDans($etat["joueurs"], fn ($j) => $j["role"] === "cupidon" && $j["vivant"]);
    if ($etat["tour"] === 1 && $hasCupidon && !($etat["cupidonFait"] ?? false)) {
        $etat["phase"] = "nuit-cupidon";
    } else {
        $hasVoyante = (bool) trouverDans($etat["joueurs"], fn ($j) => $j["role"] === "voyante" && $j["vivant"]);
        $etat["phase"] = $hasVoyante ? "nuit-voyante" : prochainAvantLoups($etat);
    }
    return $etat;
}

// Retourne la phase qui précède immédiatement nuit-loups
// (nuit-petite-fille si la petite fille est vivante, sinon nuit-loups directement)
function prochainAvantLoups(array $etat): string
{
    $hasPetiteFille = (bool) trouverDans(
        $etat["joueurs"],
        fn ($j) => $j["role"] === "petite-fille" && $j["vivant"]
    );
    return $hasPetiteFille ? "nuit-petite-fille" : "nuit-loups";
}

function finNuit(array $etat): array
{
    $morts = [];
    if ($etat["victime"]) {
        $morts[] = $etat["victime"];
    }

    foreach ($morts as $id) {
        tuerJoueur($etat, $id);
    }

    // Si le chasseur est mort cette nuit, il tire avant l'aube
    foreach ($morts as $id) {
        $j = findJoueur($etat, $id);
        if ($j["role"] === "chasseur" && ($j["peutTirer"] ?? false)) {
            $etat["chasseurDeNuit"] = true;
            $etat["victime"]        = null;
            $etat["resultatVoyante"] = null;
            $etat["cibleVoyante"]   = null;
            $etat["phase"] = "chasseur";
            return $etat;
        }
    }

    if (count($morts) > 0) {
        $détails = array_map(function ($id) use (&$etat) {
            $j = findJoueur($etat, $id);
            return $j["nom"] . " (" . nomRole($j["role"]) . ")";
        }, $morts);
        $etat["messages"][] = [
            "auteur" => "Narrateur",
            "texte"  => "Le village se réveille. " . implode(" et ", $détails) . " nous ont quittés cette nuit.",
            "ts"     => time(),
        ];
    } else {
        $etat["messages"][] = [
            "auteur" => "Narrateur",
            "texte"  => "Le village se réveille. La nuit a été calme.",
            "ts"     => time(),
        ];
    }

    $etat["victime"]         = null;
    $etat["resultatVoyante"] = null;
    $etat["cibleVoyante"]    = null;

    $fin = verifierFin($etat);
    if ($fin) {
        $etat["phase"] = "fin";
        $etat["vainqueur"] = $fin;
        return $etat;
    }
    $etat["phase"] = "jour";
    return $etat;
}

function majoritéVotes(array $votes): string
{
    $comptage = [];
    foreach ($votes as $cible) {
        $comptage[$cible] = ($comptage[$cible] ?? 0) + 1;
    }
    
    if (empty($comptage)) {
        return ""; 
    }
    
    arsort($comptage);
    $max = reset($comptage);
    $gagnants = array_keys($comptage, $max);
    
    return $gagnants[array_rand($gagnants)];
}

function tuerJoueur(array &$etat, string $id): void
{
    $j = &findJoueurRef($etat, $id);
    if (!$j["vivant"]) return;
    
    $j["vivant"] = false;
    
    if ($j["amant"]) {
        $amant = &findJoueurRef($etat, $j["amant"]);
        if ($amant["vivant"]) {
            $amant["vivant"] = false;
            $etat["messages"][] = [
                "auteur" => "Narrateur",
                "texte"  => $amant["nom"] . " (" . nomRole($amant["role"]) . ") meurt de chagrin a la perte de son amour.",
                "ts"     => time(),
            ];
        }
    }
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

function verifierFin(array $etat): ?string
{
    $vivants = array_filter($etat["joueurs"], fn ($j) => $j["vivant"]);

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

function nomRole(string $role): string
{
    $noms = [
        "loup-garou"   => "Loup-Garou",
        "villageois"   => "Villageois",
        "voyante"      => "Voyante",
        "sorciere"     => "Sorciere",
        "chasseur"     => "Chasseur",
        "cupidon"      => "Cupidon",
        "petite-fille" => "Petite Fille",
    ];
    return $noms[$role] ?? ucfirst($role);
}
