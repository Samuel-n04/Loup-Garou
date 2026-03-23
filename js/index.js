import * as API from "./api.js";



// ── Déconnexion ───────────────────────────────────────────
document
    .getElementById("btnDeconnexion")
    .addEventListener("click", async () => {
        await fetch("php/logout.php");
        location.href = "login.html";
    });

// ── Rejoindre avec un code (à implémenter plus tard) ──────
document.getElementById("btnRejoindre").addEventListener("click", () => {
    const code = document.getElementById("codeInput").value.trim();
    if (!code) return;
    // TODO: parties privées avec code
    alert("Parties privées pas encore disponibles.");
});

// ── Au chargement ─────────────────────────────────────────
// Si un code est déjà en session → vérifier si la partie existe encore
const codeExistant = API.getCode();
if (codeExistant) {
    verifierPartieExistante(codeExistant);
} else {
    afficherFormulaire();
    chargerPartiesPubliques();
}

// ── Polling ───────────────────────────────────────────────
API.on.phaseChange = (etat) => {
    if (etat.phase === "attente") {
        afficherLobbyPartie(etat);
    } else {
        location.href = "game.html";
    }
};

// ── Vérifier si la partie en session existe encore ────────
async function verifierPartieExistante(code) {
    try {
        const res = await fetch(`php/etat.php?code=${code}&depuis=0`);
        if (!res.ok) {
            // Partie introuvable → effacer le code et afficher formulaire
            API.clearCode();
            afficherFormulaire();
            chargerPartiesPubliques();
            return;
        }
        const etat = await res.json();
        if (etat.phase && etat.phase !== "attente") {
            location.href = "game.html";
            return;
        }
        afficherLobbyPartie(etat);
        API.demarrerPolling(2000);
    } catch (e) {
        API.clearCode();
        afficherFormulaire();
        chargerPartiesPubliques();
    }
}

// ── Afficher le formulaire de création ────────────────────
function afficherFormulaire() {
    document.getElementById("section-creer").hidden   = false;
    document.getElementById("section-parties").hidden = false;
    document.getElementById("section-partie").hidden  = true;
}

// ── Afficher le lobby d'une partie ────────────────────────
function afficherLobbyPartie(etat) {
    document.getElementById("section-creer").hidden   = true;
    document.getElementById("section-parties").hidden = true;
    document.getElementById("section-partie").hidden  = false;

    const code = API.getCode();
    document.getElementById("code-partie").textContent = code;
    document.getElementById("nb-joueurs").textContent  = etat.joueurs.length;
    document.getElementById("info-hote").textContent   = `Hôte : ${etat.joueurs[0]?.nom ?? ""}`;

    const liste = document.getElementById("liste-joueurs");
    liste.innerHTML = etat.joueurs.map(j => `<li>${j.nom}</li>`).join("");

    const estDansPartie = etat.joueurs.some(j => j.id === etat.monPseudo);

    document.getElementById("actions-hote").hidden    = true;
    document.getElementById("actions-joueur").hidden  = true;
    document.getElementById("actions-quitter").hidden = true;

    if (etat.estHote) {
        document.getElementById("actions-hote").hidden = false;
    } else if (!estDansPartie) {
        document.getElementById("actions-joueur").hidden = false;
    } else {
        document.getElementById("actions-quitter").hidden = false;
    }
}

// ── Charger les parties publiques ─────────────────────────
async function chargerPartiesPubliques() {
    try {
        const data = await API.listerParties();
        afficherPartiesPubliques(data.parties ?? []);
    } catch (e) {
        console.error("Erreur chargement parties :", e);
    }
}

function afficherPartiesPubliques(parties) {
    const liste = document.getElementById("liste-parties-publiques");
    if (!parties.length) {
        liste.innerHTML = "<li>Aucune partie publique disponible.</li>";
        return;
    }

    // Récupère ton pseudo affiché en haut de la page
    const monPseudo = document.querySelector("div > span").textContent.trim();

    liste.innerHTML = parties.map(p => {
        // Affiche le bouton uniquement si l'hôte correspond à ton pseudo
        const btnAnnuler = (p.hote === monPseudo) 
            ? `<button class="btn-reset-public" data-code="${p.code}">Annuler</button>` 
            : "";

        return `
<li>
    Hôte : ${p.hote} — ${p.nbJoueurs}/${p.joueurMax} joueurs
    <button class="btn-rejoindre-public" data-code="${p.code}">Rejoindre</button>
    ${btnAnnuler}
</li>
`;
    }).join("");

    // Action : Rejoindre
    document.querySelectorAll(".btn-rejoindre-public").forEach(btn => {
        btn.addEventListener("click", () => rejoindrePartie(btn.dataset.code));
    });

    // Action : Annuler (réservé à l'hôte)
    document.querySelectorAll(".btn-reset-public").forEach(btn => {
        btn.addEventListener("click", async () => {
            if (!confirm("Annuler votre partie ?")) return;
            try {
                await API.reset(btn.dataset.code); // Assure-toi que API.reset() gère ce code
                chargerPartiesPubliques();
            } catch (e) {
                alert(e.message);
            }
        });
    });
}

// ── Rejoindre une partie (public ou code) ─────────────────
async function rejoindrePartie(code) {
    try {
        API.setCode(code);
        await API.rejoindre();
        const res = await fetch(`php/etat.php?code=${code}&depuis=0`);
        const etat = await res.json();
        afficherLobbyPartie(etat);
        API.demarrerPolling(2000);
        document.getElementById("erreur-code").textContent = "";
    } catch (e) {
        API.clearCode();
        document.getElementById("erreur-code").textContent = e.message;
    }
}

// ── Créer une partie ──────────────────────────────────────
document.getElementById("btnCreer").addEventListener("click", async () => {
    const roles = [...document.querySelectorAll("input[name='role']:checked")]
        .map(cb => cb.value);
    const joueurMax    = parseInt(document.getElementById("joueurMax").value);
    const estPublique  = document.getElementById("checkPublique").checked;

    try {
        const res = await API.creerPartie(roles, joueurMax, estPublique);
        API.setCode(res.code);
        document.getElementById("erreur-creer").textContent = "";
        await verifierPartieExistante(res.code);
        API.demarrerPolling(2000);
    } catch (e) {
        document.getElementById("erreur-creer").textContent = e.message;
    }
});

// ── Rejoindre par code ────────────────────────────────────
document.getElementById("btnRejoindreCode").addEventListener("click", async () => {
    const code = document.getElementById("inputCode").value.trim().toUpperCase();
    if (!code) return;
    await rejoindrePartie(code);
});

// ── Rejoindre (déjà dans le lobby) ───────────────────────
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

// ── Annuler (hôte) ────────────────────────────────────────
document.getElementById("btnReset").addEventListener("click", async () => {
    if (!confirm("Annuler la partie ?")) return;
    try {
        await API.reset();
        API.clearCode();
        API.arreterPolling();
        afficherFormulaire();
        chargerPartiesPubliques();
    } catch (e) {
        document.getElementById("erreur-partie").textContent = e.message;
    }
});

// ── Quitter (joueur) ──────────────────────────────────────
document.getElementById("btnQuitterLobby").addEventListener("click", async () => {
    if (!confirm("Quitter la partie ?")) return;
    try {
        await API.quitter();
        API.clearCode();
        API.arreterPolling();
        afficherFormulaire();
        chargerPartiesPubliques();
    } catch (e) {
        document.getElementById("erreur-partie").textContent = e.message;
    }
});

// ── Quitter le lobby ──────────────────────────────────────
document.getElementById("btnQuitter").addEventListener("click", () => {
    API.arreterPolling();
    location.href = "index.php";
});
