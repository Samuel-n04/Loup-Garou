// import {
//     protegerPage,

//     deconnecter,
// } from "js/auth.js";

// // Redirige vers login si pas connecté
// protegerPage();

// Affiche l'email de l'utilisateur connecté

;

// Bouton déconnexion
// document
//     .getElementById("btnDeconnexion")
//     .addEventListener("click", deconnecter);

document.getElementById('btnDeconnexion')
    .addEventListener('click', async () => {
        await fetch('php/logout.php');
        window.location.href = 'login.html';
    });
document.getElementById('btnDeconnexion')
    .addEventListener('click', async () => {
        await fetch('php/logout.php');
        window.location.href = 'login.html';
    });

document.getElementById('join')
    .addEventListener('click', async (e) => {
        e.preventDefault();

        const code = document.getElementById('codeInput').value.trim();
        const error = document.getElementById('erreur');
        error.textContent = '';

        if (!code) {
            error.textContent = 'Entrez un code de partie.';
            return;
        }

        const request = await fetch('php/join_game.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: code })
        });
        const answer = await request.json();

        if (answer.status === 'ok') window.location.href = 'lobby.html';
        else error.textContent = answer.error;
    });
