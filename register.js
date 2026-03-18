import { auth } from "./firebase.js";
import { createUserWithEmailAndPassword } from "https://www.gstatic.com/firebasejs/12.10.0/firebase-auth.js";

document.getElementById("btnInscription").addEventListener("click", () => {
    const email = document.getElementById("email").value.trim();
    const motDePasse = document.getElementById("motDePasse").value;
    const confirmation = document.getElementById("confirmation").value;
    const erreur = document.getElementById("erreur");

    if (!email || !motDePasse || !confirmation) {
        erreur.textContent = "Remplis tous les champs.";
        return;
    }

    if (motDePasse !== confirmation) {
        erreur.textContent = "Les mots de passe ne correspondent pas.";
        return;
    }

    if (motDePasse.length < 6) {
        erreur.textContent =
            "Le mot de passe doit faire au moins 6 caractères.";
        return;
    }

    createUserWithEmailAndPassword(auth, email, motDePasse)
        .then((userCredential) => {
            // Compte créé, redirige vers le jeu
            window.location.href = "index.html";
        })
        .catch((error) => {
            if (error.code === "auth/email-already-in-use") {
                erreur.textContent = "Cet email est déjà utilisé.";
            } else {
                erreur.textContent = "Erreur lors de la création du compte.";
            }
            console.error(error.message);
        });
});
