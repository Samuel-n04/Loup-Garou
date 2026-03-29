document.getElementById("btnConnexion").addEventListener("click", async (e) => {
    e.preventDefault();

    let erreur = document.getElementById("erreur");
    erreur.textContent = "";

    const mail = document.getElementById("mail").value.trim();
    const mdp = document.getElementById("motDePasse").value;

    if (!mail || !mdp) {
        erreur.textContent = "Remplis tous les champs.";
        return;
    }

    const request = await fetch("../php/login.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ mail, mdp }),
    });
    const answer = await request.json();

    if (answer.error === "Mot de passe incorrect")
        erreur.textContent = "Mot de passe incorrect";
    else if (answer.error === "Mail introuvable")
        erreur.textContent = "Mail introuvable";
    else if (answer.status === "ok") window.location.href = "index.php";
});
