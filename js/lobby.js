import * as API from "./api.js";

// ── État local ────────────────────────────────────────────
let estDansPartie = false;

// ── Au chargement ─────────────────────────────────────────
verifierEtat();
API.demarrerPolling(2000);

API.on.phaseChange = (etat) => {
    if (etat.phase === "attente") {
        afficherPartieEnCours(etat);
    } else {
        // Partie démarrée → aller sur game.html
        location.href = "game.html";
    }
};

// ── Vérifier s'il y a une partie en cours ─────────────────
async function verifierEtat() {
    try {
        const res = await fetch("php/etat.php");
        if (res.status === 401) {
            location.href = "login.html";
            return;
        }
        const etat = await res.json();

        if (etat.phase && etat.phase !== "attente") {
            // Partie déjà en cours → rediriger directement
            location.href = "game.html";
            return;
        }

        if (etat.phase === "attente") {
            // Une partie existe, afficher la section rejoindre
            document.getElementById("section-creer").hidden = true;
            document.getElementById("section-partie").hidden = false;
            afficherPartieEnCours(etat);
        }
        // Sinon : pas de partie → afficher la section créer (défaut)
    } catch (e) {
        console.error("Erreur vérification état :", e);
    }
}

// ── Afficher la liste des joueurs ─────────────────────────
function afficherPartieEnCours(etat) {
    document.getElementById("section-creer").hidden = true;
    document.getElementById("section-partie").hidden = false;

    document.getElementById("nb-joueurs").textContent = etat.joueurs.length;
    document.getElementById("info-hote").textContent =
        `Hôte : ${etat.joueurs[0]?.nom ?? ""}`;

    const liste = document.getElementById("liste-joueurs");
    liste.innerHTML = etat.joueurs.map((j) => `<li>${j.nom}</li>`).join("");

    estDansPartie = etat.joueurs.some((j) => j.id === etat.monPseudo);

    if (etat.estHote) {
        document.getElementById("actions-hote").hidden = false;
        document.getElementById("actions-joueur").hidden = true;
    } else if (!estDansPartie) {
        document.getElementById("actions-hote").hidden = true;
        document.getElementById("actions-joueur").hidden = false;
    } else {
        // Déjà dans la partie, en attente
        document.getElementById("actions-hote").hidden = true;
        document.getElementById("actions-joueur").hidden = true;
    }
}

// ── Créer une partie ──────────────────────────────────────
document.getElementById("btnCreer").addEventListener("click", async () => {
    const roles = [
        ...document.querySelectorAll("input[name='role']:checked"),
    ].map((cb) => cb.value);
    const joueurMax = parseInt(document.getElementById("joueurMax").value);

    try {
        await API.creerPartie(roles, joueurMax);
        document.getElementById("erreur-creer").textContent = "";
        verifierEtat();
    } catch (e) {
        document.getElementById("erreur-creer").textContent = e.message;
    }
});

// ── Rejoindre une partie ──────────────────────────────────
document.getElementById("btnRejoindre").addEventListener("click", async () => {
    try {
        await API.rejoindre();
        document.getElementById("erreur-partie").textContent = "";
        document.getElementById("actions-joueur").hidden = true;
    } catch (e) {
        document.getElementById("erreur-partie").textContent = e.message;
    }
});

// ── Démarrer (hôte) ───────────────────────────────────────
document.getElementById("btnDemarrer").addEventListener("click", async () => {
    try {
        await API.demarrer();
        location.href = "game.html";
    } catch (e) {
        document.getElementById("erreur-partie").textContent = e.message;
    }
});

// ── Annuler / reset (hôte) ────────────────────────────────
document.getElementById("btnReset").addEventListener("click", async () => {
    if (!confirm("Annuler la partie en cours ?")) return;
    try {
        await API.reset();
        location.reload();
    } catch (e) {
        document.getElementById("erreur-partie").textContent = e.message;
    }
});
