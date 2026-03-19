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
        <a href="help.html">? Help</a>
        <button id="btnDeconnexion">Se déconnecter</button>
    </div>

    <div id="container">
        <h1>Loup-Garou</h1>

        <button id="btnCreer">Créer une partie</button>

        <br /><br />

        <label for="codeInput">Rejoindre une partie privée :</label>
        <input type="text" id="codeInput" maxlength="6" placeholder="XXXXXX" />
        <button id="btnRejoindre">Rejoindre</button>
    </div>

    <script type="module" src="js/index.js"></script>
</body>
</html>
