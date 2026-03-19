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