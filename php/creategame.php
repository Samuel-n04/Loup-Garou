<?php

session_start();
header("Content-Type: application/json");

$pseudo = $_SESSION['pseudo'] ?? null;
if (!$pseudo) {
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

// Check if the user already has an active game
$indexFichier = "../data/parties.json";
if (file_exists($indexFichier)) {
    $index = json_decode(file_get_contents($indexFichier), true) ?? [];
    foreach ($index as $infos) {
        if ($infos["hote"] === $pseudo && $infos["phase"] !== "fin") {
            http_response_code(409);
            echo json_encode(["erreur" => "Tu as déjà une partie en cours.", "code" => $infos["code"]]);
            exit;
        }
    }
}

$body        = json_decode(file_get_contents("php://input"), true) ?? [];
$estPublique = (bool) ($body["public"] ?? true);
$joueurMax   = max(4, min(20, intval($body["joueurMax"] ?? 8)));

$rolesDisponibles = ["voyante", "sorciere", "chasseur", "cupidon", "petite-fille"];
$rolesActifs      = array_values(array_intersect(
    $body["roles"] ?? $rolesDisponibles,
    $rolesDisponibles
));

$code = genererCode();

$etat = [
    "code"            => $code,
    "phase"           => "attente",
    "tour"            => 0,
    "hote"            => $pseudo,
    "public"          => $estPublique,
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
    "messages"        => [],
];

$fichierPartie = "../data/partie_$code.json";
$lockPartie    = "../data/partie_$code.lock";

$lock = fopen($lockPartie, "c");
flock($lock, LOCK_EX);
file_put_contents($fichierPartie, json_encode($etat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
flock($lock, LOCK_UN);
fclose($lock);

mettreAJourIndex($code, [
    "code"      => $code,
    "hote"      => $pseudo,
    "public"    => $estPublique,
    "phase"     => "attente",
    "nbJoueurs" => 1,
    "joueurMax" => $joueurMax,
    "createdAt" => time(),
]);

echo json_encode(["ok" => true, "code" => $code, "public" => $estPublique]);

function genererCode(): string
{
    // Exclude characters that look similar (O/0, I/1) to avoid confusion
    $chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    do {
        $code = "";
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
    } while (file_exists("../data/partie_$code.json"));
    return $code;
}

function mettreAJourIndex(string $code, array $infos): void
{
    $fichier = "../data/parties.json";
    $lock    = fopen("../data/parties.lock", "c");
    flock($lock, LOCK_EX);
    $index = file_exists($fichier)
        ? json_decode(file_get_contents($fichier), true) ?? []
        : [];
    $index[$code] = $infos;
    file_put_contents($fichier, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($lock, LOCK_UN);
    fclose($lock);
}
