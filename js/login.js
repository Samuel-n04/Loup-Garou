import { auth } from "./firebase.js";
import { signInWithEmailAndPassword } from "https://www.gstatic.com/firebasejs/12.10.0/firebase-auth.js";

document.getElementById("btnConnexion").addEventListener("click", () => {
    const email = document.getElementById("email").value.trim();
    const motDePasse = document.getElementById("motDePasse").value;
    const erreur = document.getElementById("erreur");

    if (!email || !motDePasse) {
        erreur.textContent = "Remplis tous les champs.";
        return;
    }

    signInWithEmailAndPassword(auth, email, motDePasse)
        .then((userCredential) => {
            // Connecté, redirige vers le jeu
            window.location.href = "index.html";
        })
        .catch((error) => {
            erreur.textContent = "Email ou mot de passe incorrect.";
            console.error(error.message);
        });
});
