# 🐺 Loup-Garou de Thiercelieux

Jeu de rôle multijoueur en ligne développé en PHP/JavaScript dans le cadre du projet de Programmation Web — L3 Informatique, Université Sorbonne Paris Nord (2025-2026).

## 👥 Auteurs

- [Nom 1]
- [Nom 2]

## 🎮 Description

Version web du célèbre jeu de société Loup-Garou de Thiercelieux.
Plusieurs joueurs s'affrontent en temps réel depuis leur navigateur.
La partie alterne entre une phase de nuit (actions secrètes) et une phase de jour (débat + vote).

### Rôles disponibles
- 🐺 Loup-Garou
- 🔮 Voyante
- 🧙 Sorcière
- 🏹 Chasseur
- 🧑‍🌾 Villageois

## 🛠️ Stack technique

- **Frontend** : HTML, CSS, JavaScript (Vanilla)
- **Backend** : PHP
- **Stockage** : Fichiers JSON (pas de base de données)
- **Synchronisation** : Polling (fetch toutes les 2s)

## 📁 Structure du projet
```
/loup-garou/
├── index.php
├── login.html
├── register.html
├── lobby.html
├── game.html
├── css/
│   ├── index.css
│   ├── login.css
│   ├── register.css
│   ├── lobby.css
│   └── game.css
├── js/
│   ├── index.js
│   ├── login.js
│   ├── register.js
│   ├── lobby.js
│   └── game.js
├── php/
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── create_game.php
│   ├── join_game.php
│   ├── get_state.php
│   ├── vote.php
│   └── night_action.php
├── data/
│   ├── users.json        ← créer manuellement (voir Installation)
│   └── games/
├── sprite/
│   ├── loupGarou.png
│   ├── villageois.png
│   └── voyante.png
└── README.md
```

## ⚙️ Installation

### Prérequis
- PHP 8.0 ou supérieur

### Lancer le projet

1. **Cloner le dépôt**
```bash
git clone https://github.com/[username]/loup-garou.git
cd loup-garou
```

2. **Créer le fichier users.json**
```bash
echo '{}' > data/users.json
```

3. **Créer le dossier games**
```bash
mkdir data/games
```

4. **Lancer le serveur PHP**
```bash
php -S localhost:8000
```

5. **Ouvrir dans le navigateur**
```
http://localhost:8000/login.html
```

## 🚀 Utilisation

1. Créer un compte sur `/register.html`
2. Se connecter sur `/login.html`
3. Créer ou rejoindre une partie depuis l'accueil
4. Attendre que la salle soit complète dans le lobby
5. Jouer !
