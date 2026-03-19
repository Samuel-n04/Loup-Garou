import * as API from "./api.js";

// ── Au chargement ─────────────────────────────────────────
verifierEtat();

API.on.phaseChange = (etat) => {
    if (etat.phase === "attente") {
        afficherPartieEnCours(etat);
    } else {
        location.href = "game.html";
    }
};

// ── Vérifier s'il y a une partie en cours ─────────────────
async function verifierEtat() {
    try {
        const res = await fetch("php/etat.php?depuis=0");
        if (res.status === 401) { location.href = "login.html"; return; }
        const etat = await res.json();

        if (etat.phase && etat.phase !== "attente") {
            location.href = "game.html";
            return;
        }

        if (etat.phase === "attente") {
            afficherPartieEnCours(etat);
            API.demarrerPolling(2000);
        } else {
            afficherFormulaire();
        }
    } catch (e) {
        console.error("Erreur vérification état :", e);
        afficherFormulaire();
    }
}

// ── Afficher le formulaire de création ────────────────────
function afficherFormulaire() {
    document.getElementById("section-creer").hidden  = false;
    document.getElementById("section-partie").hidden = true;
}

// ── Afficher la section lobby ─────────────────────────────
function afficherPartieEnCours(etat) {
    document.getElementById("section-creer").hidden  = true;
    document.getElementById("section-partie").hidden = false;

    document.getElementById("nb-joueurs").textContent = etat.joueurs.length;
    document.getElementById("info-hote").textContent  = `Hôte : ${etat.joueurs[0]?.nom ?? ""}`;

    const liste = document.getElementById("liste-joueurs");
    liste.innerHTML = etat.joueurs.map(j => `<li>${j.nom}</li>`).join("");

    const estDansPartie = etat.joueurs.some(j => j.id === etat.monPseudo);

    // Cacher tous les blocs d'actions d'abord
    document.getElementById("actions-hote").hidden    = true;
    document.getElementById("actions-joueur").hidden  = true;
    document.getElementById("actions-quitter").hidden = true;

    if (etat.estHote) {
        document.getElementById("actions-hote").hidden    = false;
    } else if (!estDansPartie) {
        document.getElementById("actions-joueur").hidden  = false;
    } else {
        // Dans la partie mais pas hôte → bouton quitter
        document.getElementById("actions-quitter").hidden = false;
    }
}

// ── Créer une partie ──────────────────────────────────────
document.getElementById("btnCreer").addEventListener("click", async () => {
    const roles = [...document.querySelectorAll("input[name='role']:checked")]
        .map(cb => cb.value);
    const joueurMax = parseInt(document.getElementById("joueurMax").value);

    try {
        await API.creerPartie(roles, joueurMax);
        document.getElementById("erreur-creer").textContent = "";
        await verifierEtat();
        API.demarrerPolling(2000);
    } catch (e) {
        if (e.message === "Une partie est déjà en cours.") {
            document.getElementById("erreur-creer").textContent =
                "Une partie est déjà en cours. Tu peux la rejoindre ci-dessous.";
            await verifierEtat();
        } else {
            document.getElementById("erreur-creer").textContent = e.message;
        }
    }
});

// ── Rejoindre une partie ──────────────────────────────────
document.getElementById("btnRejoindre").addEventListener("click", async () => {
    try {
        await API.rejoindre();
        document.getElementById("erreur-partie").textContent = "";
        document.getElementById("actions-joueur").hidden  = true;
        document.getElementById("actions-quitter").hidden = false;
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

// ── Annuler la partie (hôte) ──────────────────────────────
document.getElementById("btnReset").addEventListener("click", async () => {
    if (!confirm("Annuler la partie en cours ?")) return;
    try {
        await API.reset();
        API.arreterPolling();
        afficherFormulaire();
    } catch (e) {
        document.getElementById("erreur-partie").textContent = e.message;
    }
});

// ── Quitter la partie (joueur) ────────────────────────────
document.getElementById("btnQuitterLobby").addEventListener("click", async () => {
    if (!confirm("Quitter la partie ?")) return;
    try {
        await API.quitter();
        API.arreterPolling();
        afficherFormulaire();
    } catch (e) {
        document.getElementById("erreur-partie").textContent = e.message;
    }
});
