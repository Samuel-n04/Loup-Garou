<?php

// ============================================================
//  action.php — Receives player actions (POST JSON)
// ============================================================

session_start();
header("Content-Type: application/json");

const COOLDOWN_SECONDES = 60;

$idJoueur = $_SESSION['pseudo'] ?? null;
if (!$idJoueur) {
    http_response_code(401);
    echo json_encode(["erreur" => "Non connecté"]);
    exit;
}

// Validate the CSRF token to prevent cross-site request forgery.
// See php/csrf.php for an explanation of how CSRF works.
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(["erreur" => "CSRF token invalid"]);
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

// Acquire exclusive lock before reading/writing game state.
// This prevents two players from modifying the game at the same time.
$lock = fopen($fichierLock, "c");
flock($lock, LOCK_EX);

try {
    $etat = lireJSON($fichierPartie);

    if (!$etat) {
        http_response_code(404);
        echo json_encode(["erreur" => "Partie introuvable."]);
        exit;
    }
    $erreur = null;
    $phaseAvant = $etat["phase"];

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
        if (!trouverDans($etat["joueurs"], fn ($j) => $j["id"] === $idJoueur)) {
            $erreur = "Non autorisé";
            break;
        }
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
        if ($joueur["role"] !== "cupidon" || !$joueur["vivant"]) {
            $erreur = "Non autorisé";
            break;
        }
        $idA = trim($body["idA"] ?? "");
        $idB = trim($body["idB"] ?? "");
        if (!$idA || !$idB) {
            $erreur = "Players not specified";
            break;
        }
        if ($idA === $idB) {
            $erreur = "The two lovers must be different players";
            break;
        }
        $joueurA = trouverDans($etat["joueurs"], fn ($j) => $j["id"] === $idA);
        $joueurB = trouverDans($etat["joueurs"], fn ($j) => $j["id"] === $idB);
        if (!$joueurA || !$joueurB) {
            $erreur = "Player(s) not found in this game";
            break;
        }
        // Both targets must be alive (night 1, so everyone should be, but let's be safe)
        if (!$joueurA["vivant"] || !$joueurB["vivant"]) {
            $erreur = "Cannot link dead players";
            break;
        }
        $etat = lierAmants($etat, $idA, $idB);
        $hasVoyante = (bool) trouverDans($etat["joueurs"], fn ($j) => $j["role"] === "voyante" && $j["vivant"]);
        $etat["phase"] = $hasVoyante ? "nuit-voyante" : prochainAvantLoups($etat);
        break;

    case "voyante":
        if ($etat["phase"] !== "nuit-voyante") {
            $erreur = "Mauvaise phase";
            break;
        }
        $joueur = findJoueur($etat, $idJoueur);
        if ($joueur["role"] !== "voyante" || !$joueur["vivant"]) {
            $erreur = "Non autorisé";
            break;
        }
        $idCible = trim($body["idCible"] ?? "");
        if (!$idCible) {
            $erreur = "No target specified";
            break;
        }
        if ($idCible === $idJoueur) {
            $erreur = "Cannot investigate yourself";
            break;
        }
        $cible = trouverDans($etat["joueurs"], fn ($j) => $j["id"] === $idCible);
        if (!$cible || !$cible["vivant"]) {
            $erreur = "Invalid target";
            break;
        }
        $etat["resultatVoyante"] = $cible["role"];
        $etat["cibleVoyante"]    = $idCible;
        $etat["phase"] = prochainAvantLoups($etat);
        break;

    case "sorciere":
        if ($etat["phase"] !== "nuit-sorciere") {
            $erreur = "Mauvaise phase";
            break;
        }
        $joueur = findJoueur($etat, $idJoueur);
        if ($joueur["role"] !== "sorciere" || !$joueur["vivant"]) {
            $erreur = "Non autorisé";
            break;
        }

        if (!empty($body["utiliserVie"]) && $joueur["potionVie"] && $etat["victime"]) {
            $victime = &findJoueurRef($etat, $etat["victime"]);
            $victime["vivant"] = true;
            $etat["victime"]   = null;
            $joueurRef = &findJoueurRef($etat, $idJoueur);
            $joueurRef["potionVie"] = false;
        }

        // Trim first, then check non-empty (avoids !empty("  ") = true but trim = "")
        $idCibleMort = trim($body["idCibleMort"] ?? "");
        if ($idCibleMort !== "" && $joueur["potionMort"]) {
            // Witch cannot poison herself
            if ($idCibleMort === $idJoueur) {
                $erreur = "Cannot poison yourself";
                break;
            }
            $cibleMort = trouverDans($etat["joueurs"], fn ($j) => $j["id"] === $idCibleMort);
            if (!$cibleMort || !$cibleMort["vivant"]) {
                $erreur = "Invalid poison target";
                break;
            }
            tuerJoueur($etat, $idCibleMort);
            $joueurRef = &findJoueurRef($etat, $idJoueur);
            $joueurRef["potionMort"] = false;
        }

        // We no longer reset resultatVoyante to null here (seer keeps her result)
        $etat = finNuit($etat);
        break;

    case "loupVote":
        if ($etat["phase"] !== "nuit-loups") {
            $erreur = "Mauvaise phase";
            break;
        }
        $joueur = findJoueur($etat, $idJoueur);
        if ($joueur["role"] !== "loup-garou" || !$joueur["vivant"]) {
            $erreur = "Non autorisé";
            break;
        }
        // Validate target
        $idCible = trim($body["idCible"] ?? "");
        if (!$idCible) {
            $erreur = "No target specified";
            break;
        }
        if ($idCible === $idJoueur) {
            $erreur = "Cannot vote for yourself";
            break;
        }
        $cibleJoueur = trouverDans($etat["joueurs"], fn ($j) => $j["id"] === $idCible);
        if (!$cibleJoueur || !$cibleJoueur["vivant"]) {
            $erreur = "Invalid target";
            break;
        }
        // Wolves cannot eat other wolves
        if ($cibleJoueur["role"] === "loup-garou") {
            $erreur = "Wolves cannot eat other wolves";
            break;
        }

        $etat["votesLoups"][$idJoueur] = $idCible;
        $loups = array_filter($etat["joueurs"], fn ($j) => $j["role"] === "loup-garou" && $j["vivant"]);

        if (count($etat["votesLoups"]) >= count($loups)) {
            $etat["victime"]    = majoritéVotes($etat["votesLoups"]);
            $etat["votesLoups"] = [];

            $finAvant = verifierFin($etat);
            if ($finAvant) {
                $etat["phase"] = "fin";
                $etat["vainqueur"] = $finAvant;
                break;
            }

            $hasSorciere = (bool) trouverDans(
                $etat["joueurs"],
                fn ($j) => $j["role"] === "sorciere" && $j["vivant"]
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

        // 1 in 3 chance of being spotted by the wolves
        $decouverte = rand(1, 3) === 1;

        if ($decouverte) {
            // Spotted: wolves eliminate her instead, their original votes are cancelled
            $etat["victime"]    = $joueur["id"];
            $etat["votesLoups"] = [];
            $etat["resultatEspionnage"] = ["decouverte" => true];

            $finAvant = verifierFin($etat);
            if ($finAvant) {
                $etat["phase"] = "fin";
                $etat["vainqueur"] = $finAvant;
                break;
            }

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
            // Not spotted: reveals the wolf identities, wolves' turn starts next
            $loups = array_values(array_filter(
                $etat["joueurs"],
                fn ($j) => $j["role"] === "loup-garou" && $j["vivant"]
            ));
            $etat["resultatEspionnage"] = [
                "loups" => array_map(fn ($l) => $l["id"], $loups)
            ];
            // Signal to the wolves that the little girl is watching them
            $etat["alerteEspionnage"] = true;
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
        // Validate target
        $idCible = trim($body["idCible"] ?? "");
        if (!$idCible) {
            $erreur = "No target specified";
            break;
        }
        if ($idCible === $idJoueur) {
            $erreur = "Cannot vote for yourself";
            break;
        }
        $cibleJoueur = trouverDans($etat["joueurs"], fn ($j) => $j["id"] === $idCible);
        if (!$cibleJoueur || !$cibleJoueur["vivant"]) {
            $erreur = "Invalid target";
            break;
        }

        $etat["votesJour"][$idJoueur] = $idCible;
        $vivants = array_filter($etat["joueurs"], fn ($j) => $j["vivant"]);

        if (count($etat["votesJour"]) >= count($vivants)) {
            $idElimine = majoritéVotes($etat["votesJour"]);

            if ($idElimine === "") {
                $candidats = candidatsEgaux($etat["votesJour"]);
                $etat["votesJour"] = [];
                $etat["candidatsRevote"] = $candidats;
                $noms = implode(" et ", array_map(fn($id) => findJoueur($etat, $id)["nom"], $candidats));
                $etat["messages"][] = [
                    "auteur" => "Narrateur",
                    "texte"  => "Égalité entre $noms ! Le village doit se mettre d'accord. Un second vote est organisé.",
                    "ts"     => time(),
                ];
                $etat["phase"] = "revote";
            } else {
                $etat["votesJour"] = [];
                $nomElimine  = findJoueur($etat, $idElimine)["nom"];
                $roleElimine = findJoueur($etat, $idElimine)["role"];

                $etat["messages"][] = [
                    "auteur" => "Narrateur",
                    "texte"  => "Le village a décidé d'éliminer $nomElimine. Il était " . nomRole($roleElimine) . ".",
                    "ts"     => time(),
                ];

                $elimine = findJoueur($etat, $idElimine);
                if ($elimine["role"] === "chasseur" && ($elimine["peutTirer"] ?? false)) {
                    tuerJoueur($etat, $idElimine);
                    $etat["phase"] = "chasseur";
                } else {
                    tuerJoueur($etat, $idElimine);
                    $etat = apresElimination($etat);
                }
            }
        }
        break;

    case "revote":
        if ($etat["phase"] !== "revote") {
            $erreur = "Mauvaise phase";
            break;
        }
        $joueur = findJoueur($etat, $idJoueur);
        if (!$joueur["vivant"]) {
            $erreur = "Joueur mort";
            break;
        }
        $idCible = trim($body["idCible"] ?? "");
        if (!$idCible) {
            $erreur = "No target specified";
            break;
        }
        if ($idCible === $idJoueur) {
            $erreur = "Cannot vote for yourself";
            break;
        }
        if (!in_array($idCible, $etat["candidatsRevote"] ?? [])) {
            $erreur = "Cible non autorisée pour le revote";
            break;
        }
        $cibleJoueur = trouverDans($etat["joueurs"], fn ($j) => $j["id"] === $idCible);
        if (!$cibleJoueur || !$cibleJoueur["vivant"]) {
            $erreur = "Invalid target";
            break;
        }

        $etat["votesJour"][$idJoueur] = $idCible;
        $vivants = array_filter($etat["joueurs"], fn ($j) => $j["vivant"]);

        if (count($etat["votesJour"]) >= count($vivants)) {
            // On tie: pick arbitrarily
            $idElimine = majoritéVotes($etat["votesJour"], true);
            $etat["votesJour"] = [];
            $etat["candidatsRevote"] = [];

            $nomElimine  = findJoueur($etat, $idElimine)["nom"];
            $roleElimine = findJoueur($etat, $idElimine)["role"];

            $etat["messages"][] = [
                "auteur" => "Narrateur",
                "texte"  => "Le village a décidé d'éliminer $nomElimine. Il était " . nomRole($roleElimine) . ".",
                "ts"     => time(),
            ];

            $elimine = findJoueur($etat, $idElimine);
            if ($elimine["role"] === "chasseur" && ($elimine["peutTirer"] ?? false)) {
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
        $idCible = trim($body["idCible"] ?? "");
        if (!$idCible) {
            $erreur = "No target specified";
            break;
        }
        // Hunter cannot shoot themselves (they're already dying)
        if ($idCible === $idJoueur) {
            $erreur = "Cannot shoot yourself";
            break;
        }
        $cible = trouverDans($etat["joueurs"], fn ($j) => $j["id"] === $idCible);
        if (!$cible || !$cible["vivant"]) {
            $erreur = "Invalid target: player not found or already dead";
            break;
        }

        // If the hunter died at night (grief or witch poison), add the morning
        // announcement BEFORE recording the shot — otherwise the shot target
        // would wrongly appear in the "died last night" list.
        if ($etat["chasseurDeNuit"] ?? false) {
            annoncerMortsDeLaNuit($etat);
        }

        $etat["messages"][] = [
            "auteur" => "Narrateur",
            "texte"  => "Le Chasseur abat " . $cible["nom"] . " (" . nomRole($cible["role"]) . ") dans son dernier souffle !",
            "ts"     => time(),
        ];

        tuerJoueur($etat, $idCible);

        $joueurRef = &findJoueurRef($etat, $idJoueur);
        $joueurRef["peutTirer"] = false;

        // If the hunter died at night, continue to day (not a new night)
        if ($etat["chasseurDeNuit"] ?? false) {
            $etat["chasseurDeNuit"] = false;
            $fin = verifierFin($etat);
            if ($fin) {
                $etat["phase"]     = "fin";
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
            // Host left mid-game: cancel the game
            $etat["phase"]     = "fin";
            $etat["vainqueur"] = "annule";
        } elseif ($estHote && count($etat["joueurs"]) > 0) {
            // Host left the lobby: transfer host to next player
            $etat["hote"] = $etat["joueurs"][0]["id"];
        } elseif ($estHote && count($etat["joueurs"]) === 0) {
            // Last player left
            $etat["phase"]     = "fin";
            $etat["vainqueur"] = "annule";
        }
        break;

    case "chat":
        if (!trouverDans($etat["joueurs"], fn ($j) => $j["id"] === $idJoueur)) {
            $erreur = "Non autorisé";
            break;
        }
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

        // Keep only the last 100 messages to limit file size
        if (count($etat["messages"]) > 100) {
            $etat["messages"] = array_slice($etat["messages"], -100);
        }
        break;

    case "tick":
        // Triggered by the client when the cooldown timer expires.
        // Advances the current phase automatically if the timeout has passed.
        avancerCooldown($etat);
        break;

    default:
        $erreur = "Action inconnue : $action";
    }
    if (!$erreur) {
        // Whenever the phase changes, reset the cooldown clock.
        if ($etat["phase"] !== $phaseAvant) {
            $etat["phaseDebutTs"] = time();
        }
        ecrireJSON($fichierPartie, $etat);
        mettreAJourIndex($code, $etat["phase"], count($etat["joueurs"]));
    }

    if ($erreur) {
        http_response_code(400);
        echo json_encode(["erreur" => $erreur]);
    } else {
        echo json_encode(["ok" => true, "phase" => $etat["phase"]]);
    }
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

// ============================================================
//  GAME LOGIC
// ============================================================
function distribuerRoles(array $etat): array
{
    $n     = count($etat["joueurs"]);
    $roles = genererRoles($n, $etat["rolesActifs"] ?? []);
    shuffle($roles);
    foreach ($etat["joueurs"] as $i => &$j) {
        $j["role"] = $roles[$i];
        if ($j["role"] === "sorciere") {
            $j["potionVie"]  = true;
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
    $roles = [];
    // Number of werewolves scales with player count
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
    // Fill remaining slots with villagers
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
    $etat["victime"]            = null;
    $etat["votesLoups"]         = [];
    $etat["alerteEspionnage"]   = false;
    $etat["candidatsRevote"]    = [];
    $etat["resultatEspionnage"] = null;
    // Reset the night-death tracker at the start of each new night.
    // tuerJoueur() will populate this list for ALL deaths that occur during
    // this night: wolf victim, witch poison, and grief deaths.
    $etat["mortsCeNuit"] = [];

    $hasCupidon = (bool) trouverDans($etat["joueurs"], fn ($j) => $j["role"] === "cupidon" && $j["vivant"]);
    // Cupid acts only on night 1
    if ($etat["tour"] === 1 && $hasCupidon && !($etat["cupidonFait"] ?? false)) {
        $etat["phase"] = "nuit-cupidon";
    } else {
        $hasVoyante   = (bool) trouverDans($etat["joueurs"], fn ($j) => $j["role"] === "voyante" && $j["vivant"]);
        $etat["phase"] = $hasVoyante ? "nuit-voyante" : prochainAvantLoups($etat);
    }
    return $etat;
}

// Returns the phase that comes just before the wolves' turn.
// If the little girl is alive, she gets a chance to spy first.
function prochainAvantLoups(array $etat): string
{
    $hasPetiteFille = (bool) trouverDans(
        $etat["joueurs"],
        fn ($j) => $j["role"] === "petite-fille" && $j["vivant"]
    );
    return $hasPetiteFille ? "nuit-petite-fille" : "nuit-loups";
}

// ============================================================
//  finNuit — Resolves all night deaths and transitions to day.
//
//  Design note on mort tracking:
//  Deaths during the night can happen from THREE sources:
//    1. Wolf attack  → this function kills $etat["victime"]
//    2. Witch poison → tuerJoueur() was called before finNuit()
//    3. Grief        → tuerJoueur() kills the lover automatically
//
//  All three cases are recorded in $etat["mortsCeNuit"] by
//  tuerJoueur(). This lets us:
//    a) Announce ALL deaths in the morning message
//    b) Detect if the Chasseur died (from any cause) and give
//       him the chance to shoot before dawn
// ============================================================
function finNuit(array $etat): array
{
    // Kill the wolf's victim (witch poison deaths already happened before this call)
    if ($etat["victime"]) {
        tuerJoueur($etat, $etat["victime"]);
    }

    $mortsCeNuit = $etat["mortsCeNuit"] ?? [];

    // Check if the Chasseur died tonight for ANY reason:
    //   - Directly as the wolf's victim
    //   - Poisoned by the witch
    //   - From grief (his lover was killed)
    // In all three cases, the Chasseur gets to shoot before dawn.
    foreach ($mortsCeNuit as $id) {
        $j = findJoueur($etat, $id);
        if ($j["role"] === "chasseur" && ($j["peutTirer"] ?? false)) {
            $etat["chasseurDeNuit"]  = true;
            $etat["victime"]         = null;
            $etat["resultatVoyante"] = null;
            $etat["cibleVoyante"]    = null;
            $etat["phase"] = "chasseur";
            // Morning announcement is deferred: chasseurTire() will call
            // annoncerMortsDeLaNuit() before announcing the shot.
            return $etat;
        }
    }

    // No chasseur died tonight — announce all deaths now
    annoncerMortsDeLaNuit($etat);

    $etat["victime"]         = null;
    $etat["resultatVoyante"] = null;
    $etat["cibleVoyante"]    = null;

    $fin = verifierFin($etat);
    if ($fin) {
        $etat["phase"]     = "fin";
        $etat["vainqueur"] = $fin;
        return $etat;
    }
    $etat["phase"] = "jour";
    return $etat;
}

// Adds the "village wakes up" message listing all who died this night.
// Called by finNuit() (normal case) or chasseurTire() (delayed case).
function annoncerMortsDeLaNuit(array &$etat): void
{
    $mortsCeNuit = $etat["mortsCeNuit"] ?? [];
    if (count($mortsCeNuit) > 0) {
        $détails = array_map(function ($id) use (&$etat) {
            $j = findJoueur($etat, $id);
            return $j["nom"] . " (" . nomRole($j["role"]) . ")";
        }, $mortsCeNuit);
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
}

function majoritéVotes(array $votes, bool $arbitraire = false): string
{
    $comptage = [];
    foreach ($votes as $cible) {
        $comptage[$cible] = ($comptage[$cible] ?? 0) + 1;
    }

    if (empty($comptage)) {
        return "";
    }

    arsort($comptage);
    $max      = reset($comptage);
    $gagnants = array_keys($comptage, $max);

    if (count($gagnants) > 1) {
        return $arbitraire ? $gagnants[array_rand($gagnants)] : "";
    }

    return $gagnants[0];
}

function candidatsEgaux(array $votes): array
{
    $comptage = [];
    foreach ($votes as $cible) {
        $comptage[$cible] = ($comptage[$cible] ?? 0) + 1;
    }
    if (empty($comptage)) return [];
    $max = max($comptage);
    return array_values(array_keys($comptage, $max));
}

// ============================================================
//  tuerJoueur — Kills a player and handles cascading effects.
//
//  Side effects:
//    - Sets joueur["vivant"] = false
//    - Records the death in $etat["mortsCeNuit"] (used by finNuit)
//    - If the player has a lover, kills the lover from grief too
//      (also recorded in mortsCeNuit)
// ============================================================
function tuerJoueur(array &$etat, string $id): void
{
    $j = &findJoueurRef($etat, $id);
    if (!$j["vivant"]) return;

    $j["vivant"] = false;

    // Record this death in the night tracker.
    // We use isset to guard against the field not existing yet
    // (e.g. deaths that happen before demarrerNuit initialises it).
    if (!in_array($id, $etat["mortsCeNuit"] ?? [])) {
        $etat["mortsCeNuit"][] = $id;
    }

    // If the dead player has a lover, the lover dies from grief
    if ($j["amant"]) {
        $amant = &findJoueurRef($etat, $j["amant"]);
        if ($amant["vivant"]) {
            $amant["vivant"] = false;
            if (!in_array($j["amant"], $etat["mortsCeNuit"] ?? [])) {
                $etat["mortsCeNuit"][] = $j["amant"];
            }
            $etat["messages"][] = [
                "auteur" => "Narrateur",
                "texte"  => $amant["nom"] . " (" . nomRole($amant["role"]) . ") meurt de chagrin à la perte de son amour.",
                "ts"     => time(),
            ];
        }
    }
}

function apresElimination(array $etat): array
{
    $fin = verifierFin($etat);
    if ($fin) {
        $etat["phase"]     = "fin";
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

    // Lovers win if they are the last two survivors
    $amants = array_filter($vivants, fn ($j) => $j["amant"] !== null);
    if (count($amants) === 2 && count($vivants) === 2) {
        return "amants";
    }

    $loups  = array_filter($vivants, fn ($j) => $j["role"] === "loup-garou");
    $autres = array_filter($vivants, fn ($j) => $j["role"] !== "loup-garou");
    // Villagers win if all wolves are dead
    if (count($loups) === 0) {
        return "villageois";
    }
    // Wolves win if they outnumber (or equal) the remaining players
    if (count($loups) >= count($autres)) {
        return "loups";
    }

    return null;
}

// ============================================================
//  UTILITIES
// ============================================================
// Note: this lireJSON is intentionally lock-free — it is always called
// while the exclusive flock is already held by this process (see top of file).
// etat.php has its own lireJSON that acquires a shared lock itself because
// it does not hold an exclusive lock.
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
    $lock    = fopen("../data/parties.lock", "c");
    flock($lock, LOCK_EX);

    $index = file_exists($fichier)
        ? json_decode(file_get_contents($fichier), true) ?? []
        : [];

    if ($phase === "fin") {
        // Remove the game from the index once it ends.
        // We intentionally do NOT delete the game file here to avoid a race condition:
        // clients may still be polling etat.php to read the final "fin" state.
        // Orphan files are cleaned up later by parties.php (after a 60-second delay).
        unset($index[$code]);
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
        "sorciere"     => "Sorcière",
        "chasseur"     => "Chasseur",
        "cupidon"      => "Cupidon",
        "petite-fille" => "Petite Fille",
    ];
    return $noms[$role] ?? ucfirst($role);
}

// ============================================================
//  avancerCooldown — Auto-advances a phase when a player is AFK.
//
//  Called by the "tick" action, which the client sends when its
//  local countdown reaches zero.  The exclusive file lock in
//  action.php ensures only one concurrent tick actually runs.
//
//  Returns true if the phase was advanced, false otherwise.
// ============================================================
function avancerCooldown(array &$etat): bool
{
    if (!isset($etat["phaseDebutTs"])) {
        // Game predates the cooldown feature — initialise the clock now.
        $etat["phaseDebutTs"] = time();
        return false;
    }

    if (time() - $etat["phaseDebutTs"] < COOLDOWN_SECONDES) {
        return false;
    }

    // The phase has timed out — simulate the missing action.
    switch ($etat["phase"]) {

        case "distribution":
            $etat["messages"][] = [
                "auteur" => "Narrateur",
                "texte"  => "La nuit commence automatiquement (délai expiré).",
                "ts"     => time(),
            ];
            $etat["prets"] = [];
            $etat = demarrerNuit($etat);
            return true;

        case "nuit-cupidon":
            $vivants = array_values(array_filter($etat["joueurs"], fn ($j) => $j["vivant"]));
            if (count($vivants) >= 2) {
                shuffle($vivants);
                $etat = lierAmants($etat, $vivants[0]["id"], $vivants[1]["id"]);
            }
            $etat["messages"][] = [
                "auteur" => "Narrateur",
                "texte"  => "Cupidon n'a pas agi à temps. L'amour frappe au hasard !",
                "ts"     => time(),
            ];
            $hasVoyante = (bool) trouverDans($etat["joueurs"], fn ($j) => $j["role"] === "voyante" && $j["vivant"]);
            $etat["phase"] = $hasVoyante ? "nuit-voyante" : prochainAvantLoups($etat);
            return true;

        case "nuit-voyante":
            $etat["messages"][] = [
                "auteur" => "Narrateur",
                "texte"  => "La Voyante n'a pas scruté les âmes cette nuit.",
                "ts"     => time(),
            ];
            $etat["phase"] = prochainAvantLoups($etat);
            return true;

        case "nuit-petite-fille":
            $etat["phase"] = "nuit-loups";
            return true;

        case "nuit-loups":
            $loups    = array_values(array_filter($etat["joueurs"], fn ($j) => $j["role"] === "loup-garou" && $j["vivant"]));
            $nonLoups = array_values(array_filter($etat["joueurs"], fn ($j) => $j["role"] !== "loup-garou" && $j["vivant"]));
            if (empty($nonLoups)) return false;
            foreach ($loups as $loup) {
                if (!isset($etat["votesLoups"][$loup["id"]])) {
                    $cible = $nonLoups[array_rand($nonLoups)];
                    $etat["votesLoups"][$loup["id"]] = $cible["id"];
                }
            }
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
            return true;

        case "nuit-sorciere":
            $etat["messages"][] = [
                "auteur" => "Narrateur",
                "texte"  => "La Sorcière range ses potions pour cette nuit.",
                "ts"     => time(),
            ];
            $etat = finNuit($etat);
            return true;

        case "jour":
            $etat["messages"][] = [
                "auteur" => "Narrateur",
                "texte"  => "Le vote commence automatiquement (délai expiré).",
                "ts"     => time(),
            ];
            $etat["votesJour"] = [];
            $etat["phase"]     = "vote";
            return true;

        case "vote":
            $vivants = array_values(array_filter($etat["joueurs"], fn ($j) => $j["vivant"]));
            foreach ($vivants as $j) {
                if (!isset($etat["votesJour"][$j["id"]])) {
                    $autres = array_values(array_filter($vivants, fn ($a) => $a["id"] !== $j["id"]));
                    if (!empty($autres)) {
                        $cible = $autres[array_rand($autres)];
                        $etat["votesJour"][$j["id"]] = $cible["id"];
                    }
                }
            }
            if (count($etat["votesJour"]) >= count($vivants)) {
                $idElimine = majoritéVotes($etat["votesJour"]);

                if ($idElimine === "") {
                    $candidats = candidatsEgaux($etat["votesJour"]);
                    $etat["votesJour"] = [];
                    $etat["candidatsRevote"] = $candidats;
                    $noms = implode(" et ", array_map(fn($id) => findJoueur($etat, $id)["nom"], $candidats));
                    $etat["messages"][] = [
                        "auteur" => "Narrateur",
                        "texte"  => "Égalité entre $noms ! Le village doit se mettre d'accord. Un second vote est organisé.",
                        "ts"     => time(),
                    ];
                    $etat["phase"] = "revote";
                } else {
                    $etat["votesJour"] = [];
                    $nomElimine  = findJoueur($etat, $idElimine)["nom"];
                    $roleElimine = findJoueur($etat, $idElimine)["role"];
                    $etat["messages"][] = [
                        "auteur" => "Narrateur",
                        "texte"  => "Le village a décidé d'éliminer $nomElimine. Il était " . nomRole($roleElimine) . ".",
                        "ts"     => time(),
                    ];
                    $elimine = findJoueur($etat, $idElimine);
                    if ($elimine["role"] === "chasseur" && ($elimine["peutTirer"] ?? false)) {
                        tuerJoueur($etat, $idElimine);
                        $etat["phase"] = "chasseur";
                    } else {
                        tuerJoueur($etat, $idElimine);
                        $etat = apresElimination($etat);
                    }
                }
            }
            return true;

        case "revote":
            $vivants = array_values(array_filter($etat["joueurs"], fn ($j) => $j["vivant"]));
            $candidats = $etat["candidatsRevote"] ?? [];
            foreach ($vivants as $j) {
                if (!isset($etat["votesJour"][$j["id"]]) && !empty($candidats)) {
                    // Auto-vote: pick among candidates only (excluding self if candidate)
                    $cibles = array_values(array_filter($candidats, fn ($id) => $id !== $j["id"]));
                    if (empty($cibles)) $cibles = $candidats; // edge case: only one candidate
                    $etat["votesJour"][$j["id"]] = $cibles[array_rand($cibles)];
                }
            }
            if (count($etat["votesJour"]) >= count($vivants)) {
                // Arbitraire on tie
                $idElimine = majoritéVotes($etat["votesJour"], true);
                $etat["votesJour"] = [];
                $etat["candidatsRevote"] = [];

                $nomElimine  = findJoueur($etat, $idElimine)["nom"];
                $roleElimine = findJoueur($etat, $idElimine)["role"];
                $etat["messages"][] = [
                    "auteur" => "Narrateur",
                    "texte"  => "Le village a décidé d'éliminer $nomElimine. Il était " . nomRole($roleElimine) . ".",
                    "ts"     => time(),
                ];
                $elimine = findJoueur($etat, $idElimine);
                if ($elimine["role"] === "chasseur" && ($elimine["peutTirer"] ?? false)) {
                    tuerJoueur($etat, $idElimine);
                    $etat["phase"] = "chasseur";
                } else {
                    tuerJoueur($etat, $idElimine);
                    $etat = apresElimination($etat);
                }
            }
            return true;

        case "chasseur":
            $chasseur = trouverDans($etat["joueurs"], fn ($j) => $j["role"] === "chasseur");
            if ($chasseur) {
                $ref = &findJoueurRef($etat, $chasseur["id"]);
                $ref["peutTirer"] = false;
            }
            $etat["messages"][] = [
                "auteur" => "Narrateur",
                "texte"  => "Le Chasseur n'a pas eu le temps de tirer.",
                "ts"     => time(),
            ];
            if ($etat["chasseurDeNuit"] ?? false) {
                $etat["chasseurDeNuit"] = false;
                $fin = verifierFin($etat);
                if ($fin) {
                    $etat["phase"]     = "fin";
                    $etat["vainqueur"] = $fin;
                } else {
                    $etat["phase"] = "jour";
                }
            } else {
                $etat = apresElimination($etat);
            }
            return true;

        default:
            return false;
    }
}
