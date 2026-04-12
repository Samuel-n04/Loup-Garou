# Loup-Garou

Implémentation web multijoueur du jeu de société **Loup-Garou de Thiercelieux**.

## Fonctionnalités

- Parties publiques ou privées (4 à 20 joueurs)
- Rôles configurables à la création de partie
- Rejoindre une partie par code à 6 caractères ou depuis la liste des parties publiques
- Chat en jeu
- Auto-avancement de phase après 60 secondes d'inaction
- Lancement automatique du vote du village après 30 secondes de discussion
- Isolation des informations par rôle : chaque joueur ne reçoit que ce qu'il est autorisé à voir
- Page d'aide intégrée (`help.php`) avec description de chaque rôle
- Protection CSRF, sessions PHP, mots de passe hashés (bcrypt)

## Rôles disponibles

| Rôle | Camp | Pouvoir |
|---|---|---|
| Loup-Garou | Loups | Vote chaque nuit pour dévorer un villageois |
| Villageois | Village | Participe aux votes du jour |
| Voyante | Village | Révèle le rôle d'un joueur chaque nuit |
| Sorcière | Village | Dispose d'une potion de vie et d'une potion de mort |
| Chasseur | Village | Abat un joueur de son choix à sa mort |
| Cupidon | Village | Lie deux amants la première nuit ; si l'un meurt, l'autre meurt de chagrin |
| Petite-Fille | Village | Peut espionner les loups (1 chance sur 3 d'être repérée) |

## Phases de jeu

```
attente → distribution → nuit-cupidon → nuit-voyante → nuit-petite-fille
→ nuit-loups → nuit-sorciere → jour → vote → [revote] → [chasseur] → fin
```

## Stack technique

- **Backend** : PHP (sessions, API JSON, verrous de fichiers)
- **Frontend** : HTML/CSS/JavaScript vanilla (modules ES6)
- **Persistance** : fichiers JSON dans `data/` (pas de base de données)
- **Temps réel** : polling toutes les 2 secondes

## Structure du projet

```
├── index.php                     # Lobby (créer / rejoindre une partie)
├── help.php                      # Page d'aide avec description des rôles
├── game.html                     # Interface de jeu
├── login.html / register.html    # Authentification
├── js/
│   ├── api.js                    # Client API (polling, CSRF, actions)
│   ├── game.js                   # Logique de jeu (rendu, phases, timers)
│   ├── index.js                  # Lobby
│   ├── login.js / register.js
│   └── help.js                   # Animation des cartes sur la page d'aide
├── php/
│   ├── login.php / register.php / logout.php
│   ├── creategame.php            # Création de partie
│   ├── parties.php               # Liste des parties publiques + nettoyage
│   ├── etat.php                  # État filtré par rôle (anti-triche)
│   ├── action.php                # Toutes les actions joueur (verrou exclusif)
│   ├── reset.php                 # Annulation / réinitialisation de partie
│   └── csrf.php                  # Génération du token CSRF
├── css/
│   ├── game.css / index.css      # Interface de jeu et lobby
│   ├── login.css / register.css
│   ├── lobby.css
│   └── help.css
├── sprite/                       # Sprites des rôles (PNG + sources Aseprite)
└── data/                         # Données runtime (users.json, parties.json, …)
```

## Installation

**Prérequis** : PHP 8.1+ avec un serveur web (Apache/Nginx) ou `php -S`.

```bash
git clone <repo>
cd Loup-Garou
mkdir -p data
chmod 775 data
php -S localhost:8000
```

Ouvrir `http://localhost:8000` dans le navigateur.

> Le répertoire `data/` doit être accessible en écriture par le serveur web. Il est protégé par un `.htaccess` qui bloque l'accès HTTP direct.

## Conditions de victoire

- **Loups** : les loups-garous sont aussi nombreux que les villageois
- **Village** : tous les loups-garous sont éliminés
- **Amants** : les deux amants (de camps opposés) sont les deux derniers survivants
