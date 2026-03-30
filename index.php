<?php

session_start();

// Rediriger vers login si pas connecté
if (!isset($_SESSION['pseudo'])) {
    header('Location: login.html');
    exit;
}
?>
<!doctype html>
<html lang="fr">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Loup-Garou</title>
        <link rel="stylesheet" href="css/index.css" />
    </head>
    <body>
        <div style="position: fixed; top: 16px; right: 16px; display: flex; gap: 12px;">
            <span><?= htmlspecialchars($_SESSION['pseudo']) ?></span>
            <a href="help.php">? Help</a>
            <button id="btnDeconnexion">Se déconnecter</button>
        </div>

        <!-- ── Créer une partie ── -->
        <section id="section-creer">
            <h2>Créer une partie</h2>

            <label for="joueurMax">Nombre de joueurs max</label>
            <input type="number" id="joueurMax" min="4" max="20" value="8" />

            <label>
                <input type="checkbox" id="checkPublique" checked />
                Partie publique
            </label>

            <fieldset>
                <legend>Rôles à activer</legend>
                <label><input type="checkbox" name="role" value="voyante"      checked /> Voyante</label>
                <label><input type="checkbox" name="role" value="sorciere"     checked /> Sorcière</label>
                <label><input type="checkbox" name="role" value="chasseur"     checked /> Chasseur</label>
                <label><input type="checkbox" name="role" value="cupidon"      checked /> Cupidon</label>
                <label><input type="checkbox" name="role" value="petite-fille" checked /> Petite Fille</label>
            </fieldset>

            <button id="btnCreer">Créer la partie</button>
            <p id="erreur-creer"></p>
        </section>

        <hr />

        <!-- ── Rejoindre par code ── -->
        <section id="section-parties">
            <h2>Rejoindre une partie</h2>

            <div>
                <input type="text" id="inputCode" maxlength="6" placeholder="Code (ex: ABC123)" />
                <button id="btnRejoindreCode">Rejoindre</button>
                <p id="erreur-code"></p>
            </div>

            <h3>Parties publiques disponibles</h3>
            <ul id="liste-parties-publiques"></ul>
        </section>

        <hr />

        <!-- ── Partie en cours ── -->
        <section id="section-partie" hidden>
            <h2>Partie — Code : <span id="code-partie"></span></h2>

            <p id="info-hote"></p>

            <h3>Joueurs (<span id="nb-joueurs">0</span>)</h3>
            <ul id="liste-joueurs"></ul>

            <div id="actions-hote" hidden>
                <button id="btnDemarrer">Démarrer la partie</button>
                <button id="btnReset">Annuler la partie</button>
            </div>

            <div id="actions-joueur" hidden>
                <button id="btnRejoindre">Rejoindre la partie</button>
            </div>

            <div id="actions-quitter" hidden>
                <button id="btnQuitterLobby">Quitter la partie</button>
            </div>

            <p id="erreur-partie"></p>
        </section>
        </div>

        <script type="module" src="js/index.js"></script>
    </body>
</html>
