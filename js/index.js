import * as API from "./api.js";

// Petite fonction utilitaire pour éviter les erreurs null
function on(id, event, handler) {
    const el = document.getElementById(id);
    if (el) el.addEventListener(event, handler);
}

document.addEventListener("DOMContentLoaded", () => {
    // ── Déconnexion ───────────────────────────────────────────
    on("btnDeconnexion", "click", async () => {
        await fetch("php/logout.php");
        location.href = "login.html";
    });

    // ── Rejoindre avec un code (placeholder) ──────
    on("btnRejoindre", "click", () => {
        const input = document.getElementById("codeInput");
        const code = input ? input.value.trim() : "";
        if (!code) return;
        alert("Parties privées pas encore disponibles.");
    });

    // ── Au chargement ─────────────────────────────────────────
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

    // ── Créer une partie ──────────────────────────────────────
    on("btnCreer", "click", async () => {
        const roles = [
            ...document.querySelectorAll("input[name='role']:checked"),
        ].map((cb) => cb.value);

        const joueurMaxEl = document.getElementById("joueurMax");
        const joueurMax = joueurMaxEl ? parseInt(joueurMaxEl.value) : 0;

        const estPubliqueEl = document.getElementById("checkPublique");
        const estPublique = estPubliqueEl ? estPubliqueEl.checked : false;

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
    on("btnRejoindreCode", "click", async () => {
        const input = document.getElementById("inputCode");
        const code = input ? input.value.trim().toUpperCase() : "";
        if (!code) return;
        await rejoindrePartie(code);
    });

    // ── Démarrer (hôte) ───────────────────────────────────────
    on("btnDemarrer", "click", async () => {
        try {
            await API.demarrer();
            location.href = "game.html";
        } catch (e) {
            document.getElementById("erreur-partie").textContent = e.message;
        }
    });

    // ── Annuler (hôte) ────────────────────────────────────────
    on("btnReset", "click", async () => {
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
    on("btnQuitterLobby", "click", async () => {
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

    // ── Quitter page ──────────────────────────────────────────
    on("btnQuitter", "click", () => {
        API.arreterPolling();
        location.href = "index.php";
    });
});

// ── Vérifier si la partie existe encore ────────
async function verifierPartieExistante(code) {
    try {
        const res = await fetch(`php/etat.php?code=${code}&depuis=0`);
        if (!res.ok) {
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

// ── UI ─────────────────────────────────────────
function afficherFormulaire() {
    document.getElementById("section-creer").hidden = false;
    document.getElementById("section-parties").hidden = false;
    document.getElementById("section-partie").hidden = true;
}

function afficherLobbyPartie(etat) {
    document.getElementById("section-creer").hidden = true;
    document.getElementById("section-parties").hidden = true;
    document.getElementById("section-partie").hidden = false;

    const code = API.getCode();
    document.getElementById("code-partie").textContent = code;
    document.getElementById("nb-joueurs").textContent = etat.joueurs.length;
    document.getElementById("info-hote").textContent =
        `Hôte : ${etat.joueurs[0]?.nom ?? ""}`;

    const liste = document.getElementById("liste-joueurs");
    liste.innerHTML = etat.joueurs.map((j) => `<li>${j.nom}</li>`).join("");

    const estDansPartie = etat.joueurs.some((j) => j.id === etat.monPseudo);

    document.getElementById("actions-hote").hidden = true;
    document.getElementById("actions-joueur").hidden = true;
    document.getElementById("actions-quitter").hidden = true;

    if (etat.estHote) {
        document.getElementById("actions-hote").hidden = false;
    } else if (!estDansPartie) {
        document.getElementById("actions-joueur").hidden = false;
    } else {
        document.getElementById("actions-quitter").hidden = false;
    }
}

// ── Parties publiques ─────────────────────────
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

    const monPseudo =
        document.querySelector("div > span")?.textContent?.trim() ?? "";

    liste.innerHTML = parties
        .map(
            (p) => `
<li>
    Hôte : ${p.hote} — ${p.nbJoueurs}/${p.joueurMax} joueurs
    <button class="btn-rejoindre-public" data-code="${p.code}">Rejoindre</button>
    ${p.hote === monPseudo ? `<button class="btn-reset-public" data-code="${p.code}">Annuler</button>` : ""}
</li>
`,
        )
        .join("");

    document.querySelectorAll(".btn-rejoindre-public").forEach((btn) => {
        btn.addEventListener("click", () => rejoindrePartie(btn.dataset.code));
    });

    document.querySelectorAll(".btn-reset-public").forEach((btn) => {
        btn.addEventListener("click", async () => {
            if (!confirm("Annuler votre partie ?")) return;
            try {
                await API.reset(btn.dataset.code);
                chargerPartiesPubliques();
            } catch (e) {
                alert(e.message);
            }
        });
    });
}

// ── Rejoindre ─────────────────────────
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
