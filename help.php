<?php
session_start();

// Redirect to login if the user is not authenticated
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
        <title>Aide - Loup-Garou</title>
        <link rel="stylesheet" href="css/help.css" />
    </head>
    <body>
        <div style="position: fixed; top: 16px; left: 16px">
            <a href="index.php">← Retour</a>
        </div>

        <div id="container">
            <h1>Aide</h1>

            <!-- PART 1: How the site works -->
            <section id="fonctionnement">
                <h2>Comment ça marche ?</h2>
                <p><!-- TODO: add explanation of how the site works --></p>
            </section>

            <hr />

            <!-- PART 2: Cards -->
            <section id="cartes">
                <h2>Les cartes</h2>

                <div class="carte-bloc">
                    <div class="carte-container" onclick="flip(this)">
                        <div class="carte-inner">
                            <div class="carte-front">
                                <img
                                    src="sprite/backCard.png"
                                    alt="Dos de carte"
                                />
                            </div>
                            <div class="carte-back">
                                <img
                                    src="sprite/loupGarou.png"
                                    alt="Loup-Garou"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="carte-info">
                        <h3>Loup-Garou</h3>
                        <p>
                            Chaque nuit, les Loups-Garous se réveillent et
                            choisissent ensemble une victime à éliminer parmi
                            les villageois. Leur objectif est d'éliminer tous
                            les villageois avant d'être découverts. Le jour, ils
                            font semblant d'être des villageois ordinaires pour
                            éviter d'être accusés.
                        </p>
                    </div>
                </div>

                <div class="carte-bloc">
                    <div class="carte-container" onclick="flip(this)">
                        <div class="carte-inner">
                            <div class="carte-front">
                                <img
                                    src="sprite/backCard.png"
                                    alt="Dos de carte"
                                />
                            </div>
                            <div class="carte-back">
                                <img
                                    src="sprite/villageois.png"
                                    alt="Villageois"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="carte-info">
                        <h3>Villageois</h3>
                        <p>
                            Le Villageois n'a aucun pouvoir spécial. Son seul
                            outil est la parole : chaque jour, il doit
                            convaincre les autres de son innocence et tenter
                            d'identifier les Loups-Garous parmi le groupe pour
                            les éliminer par vote.
                        </p>
                    </div>
                </div>

                <div class="carte-bloc">
                    <div class="carte-container" onclick="flip(this)">
                        <div class="carte-inner">
                            <div class="carte-front">
                                <img
                                    src="sprite/backCard.png"
                                    alt="Dos de carte"
                                />
                            </div>
                            <div class="carte-back">
                                <img src="sprite/voyante.png" alt="Voyante" />
                            </div>
                        </div>
                    </div>
                    <div class="carte-info">
                        <h3>Voyante</h3>
                        <p>
                            Chaque nuit, la Voyante peut observer secrètement
                            l'identité d'un joueur de son choix. Elle sait ainsi
                            s'il est Loup-Garou ou non. Elle doit utiliser cette
                            information avec prudence pour guider les villageois
                            sans se dévoiler.
                        </p>
                    </div>
                </div>

                <div class="carte-bloc">
                    <div class="carte-container" onclick="flip(this)">
                        <div class="carte-inner">
                            <div class="carte-front">
                                <img
                                    src="sprite/backCard.png"
                                    alt="Dos de carte"
                                />
                            </div>
                            <div class="carte-back">
                                <img src="sprite/cupidon.png" alt="Cupidon" />
                            </div>
                        </div>
                    </div>
                    <div class="carte-info">
                        <h3>Cupidon</h3>
                        <p>
                            La première nuit, Cupidon désigne deux joueurs qui
                            tombent amoureux. Ces deux amants sont liés : si
                            l'un d'eux meurt, l'autre meurt de chagrin
                            immédiatement. Si les amants sont dans des camps
                            opposés, ils forment alors un troisième camp et
                            doivent survivre ensemble jusqu'à la fin.
                        </p>
                    </div>
                </div>

                <div class="carte-bloc">
                    <div class="carte-container" onclick="flip(this)">
                        <div class="carte-inner">
                            <div class="carte-front">
                                <img
                                    src="sprite/backCard.png"
                                    alt="Dos de carte"
                                />
                            </div>
                            <div class="carte-back">
                                <img src="sprite/chasseur.png" alt="Chasseur" />
                            </div>
                        </div>
                    </div>
                    <div class="carte-info">
                        <h3>Chasseur</h3>
                        <p>
                            Le Chasseur est un villageois armé. Au moment où il
                            est éliminé (de nuit par les Loups-Garous ou de jour
                            par le vote), il peut immédiatement abattre un autre
                            joueur de son choix. Son élimination a donc toujours
                            un prix pour ses adversaires.
                        </p>
                    </div>
                </div>

                <div class="carte-bloc">
                    <div class="carte-container" onclick="flip(this)">
                        <div class="carte-inner">
                            <div class="carte-front">
                                <img
                                    src="sprite/backCard.png"
                                    alt="Dos de carte"
                                />
                            </div>
                            <div class="carte-back">
                                <img src="sprite/sorciere.png" alt="Sorcière" />
                            </div>
                        </div>
                    </div>
                    <div class="carte-info">
                        <h3>Sorcière</h3>
                        <p>
                            La Sorcière possède deux potions à usage unique :
                            une potion de vie pour ressusciter la victime des
                            Loups-Garous cette nuit-là, et une potion de mort
                            pour éliminer n'importe quel joueur de son choix.
                            Elle peut utiliser une ou les deux potions au cours
                            d'une même nuit.
                        </p>
                    </div>
                </div>

                <div class="carte-bloc">
                    <div class="carte-container" onclick="flip(this)">
                        <div class="carte-inner">
                            <div class="carte-front">
                                <img
                                    src="sprite/backCard.png"
                                    alt="Dos de carte"
                                />
                            </div>
                            <div class="carte-back">
                                <img
                                    src="sprite/petite-fille.png"
                                    alt="Petite Fille"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="carte-info">
                        <h3>Petite Fille</h3>
                        <p>
                            La Petite Fille peut espionner les Loups-Garous
                            pendant leur phase de nuit en entrouvrant les yeux.
                            Si elle est surprise par un Loup-Garou, elle est
                            immédiatement éliminée à la place de la victime
                            initialement choisie. Un pouvoir risqué mais
                            précieux pour le camp des villageois.
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <script src="js/help.js"></script>
    </body>
</html>
