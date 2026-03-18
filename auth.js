import { auth } from "./firebase.js";
import {
    onAuthStateChanged,
    signOut,
} from "https://www.gstatic.com/firebasejs/12.10.0/firebase-auth.js";

// Vérifie si l'utilisateur est connecté
// A inclure sur toutes les pages protégées
export function protegerPage() {
    onAuthStateChanged(auth, (user) => {
        if (!user) {
            // Pas connecté → redirige vers login
            window.location.href = "login.html";
        }
    });
}

// Récupère l'utilisateur connecté
export function getUtilisateur(callback) {
    onAuthStateChanged(auth, (user) => {
        callback(user);
    });
}

// Déconnexion
export function deconnecter() {
    signOut(auth).then(() => {
        window.location.href = "login.html";
    });
}
