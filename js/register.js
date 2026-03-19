
document.getElementById("btnInscription").addEventListener("click", async (e) => {
    e.preventDefault();
    const email = document.getElementById("email").value.trim();
    const motDePasse = document.getElementById("motDePasse").value;
    const confirmation = document.getElementById("confirmation").value;
    const pseudo = document.getElementById("pseudo").value.trim();
    const erreur = document.getElementById("erreur");

    if (!pseudo || !email || !motDePasse || !confirmation) {
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

    const request = await fetch('..php / register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pseudo, email, motDePasse })
    });
    const answer = await request.json();

    if (answer.status === "mail existant") {
        erreur.textContent = "Le mail appartient deja a un compte existant";
    }

    if (answer.status === "pseudo existant") {
        erreur.textContent = "Le pseudo que vous avez indiqué appartient deja a un compte existant";
    }


    if (answer.status === "ok") {
        window.location.href = 'index.html';



    }
});