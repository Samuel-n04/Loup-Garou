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

                <p>
                    Loup-Garou est un jeu de déduction sociale en ligne. Une partie
                    se joue en tour par tour, en alternant des phases de <strong>nuit</strong>
                    et de <strong>jour</strong>.
                </p>

                <h3 style="margin-top:20px;">Créer ou rejoindre une partie</h3>
                <p>
                    Depuis la page d'accueil, tu peux <strong>créer une partie</strong> en
                    choisissant le nombre de joueurs maximum et les rôles spéciaux à inclure.
                    Un code à 6 lettres est généré : partage-le aux autres joueurs pour qu'ils
                    te rejoignent. Tu peux aussi rejoindre une <strong>partie publique</strong>
                    existante depuis la liste, ou entrer directement un code.
                </p>

                <h3 style="margin-top:20px;">Début de partie</h3>
                <p>
                    Une fois que tous les joueurs ont rejoint, l'hôte lance la partie.
                    Chaque joueur reçoit secrètement un <strong>rôle</strong> (Loup-Garou,
                    Villageois, Voyante…). Ta carte apparaît retournée face visible uniquement
                    pour toi, en bas de l'arène.
                </p>

                <h3 style="margin-top:20px;">La nuit</h3>
                <p>
                    Pendant la nuit, les joueurs aux rôles spéciaux agissent dans l'ordre :
                    Cupidon (première nuit), Voyante, Loups-Garous, Sorcière, Chasseur (si
                    éliminé). Chaque action est secrète : seul le joueur concerné voit ce
                    qu'il se passe. Les autres attendent.
                </p>

                <h3 style="margin-top:20px;">Le jour</h3>
                <p>
                    Au lever du jour, les morts de la nuit sont annoncés. Tous les joueurs
                    encore en vie discutent via le <strong>chat</strong> pour identifier les
                    Loups-Garous, puis votent pour éliminer un suspect. Le joueur qui
                    reçoit le plus de votes est éliminé.
                </p>

                <h3 style="margin-top:20px;">Conditions de victoire</h3>
                <p>
                    Les <strong>Loups-Garous</strong> gagnent quand ils sont au moins aussi
                    nombreux que les Villageois en vie. Les <strong>Villageois</strong>
                    gagnent en éliminant tous les Loups-Garous. Si Cupidon a lié deux amants
                    de camps opposés, ces derniers forment un troisième camp et gagnent s'ils
                    sont les deux derniers survivants.
                </p>

                <h3 style="margin-top:20px;">Auto-avance</h3>
                <p>
                    Si un joueur dont c'est le tour n'agit pas dans le temps imparti,
                    la phase passe automatiquement. Un compte à rebours est affiché
                    en bas de l'arène pour indiquer le temps restant.
                </p>
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
