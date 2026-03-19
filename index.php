<!doctype html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Loup-Garou</title>
</head>

<body>
    <div style="
                position: fixed;
                top: 16px;
                right: 16px;
                display: flex;
                gap: 12px;
            ">

        <span>

            <?php
            session_start();

            echo ($_SESSION['pseudo']);
            ?>

        </span>
        <span id="emailUser"></span>
        <a href="help.html">? Help</a>
        <button id="btnDeconnexion">Se déconnecter</button>
    </div>

    <div id="container">
        <h1>Loup-Garou</h1>

        <button>Rejoindre une partie publique</button>

        <br /><br />

        <label for="codeInput">Code de partie privée :</label>
        <input type="text" id="codeInput" maxlength="6" placeholder="XXXXXX" />
        <button id="join">Rejoindre</button>
        <p id="erreur"></p>
    </div>

    <script type="module" src="js/index.js">

    </script>
</body>

</html>