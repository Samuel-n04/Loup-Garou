import * as API from "./api.js";

// Small utility to safely add event listeners (avoids null errors)
function on(id, event, handler) {
    const el = document.getElementById(id);
    if (el) el.addEventListener(event, handler);
}

// ── Public games polling ──────────────────────────────────
// When the user is on the lobby screen (not in a game), we
// periodically refresh the list of available public games
// so new games appear automatically without a page reload.
let _publicGamesTimer = null;

function demarrerPollingParties() {
    if (_publicGamesTimer) return; // already running
    chargerPartiesPubliques();
    _publicGamesTimer = setInterval(chargerPartiesPubliques, 3000);
}

function arreterPollingParties() {
    clearInterval(_publicGamesTimer);
    _publicGamesTimer = null;
}

document.addEventListener("DOMContentLoaded", () => {
    // ── Logout ────────────────────────────────────────────────
    on("btnDeconnexion", "click", async () => {
        await fetch("php/logout.php");
        location.href = "login.html";
    });

    // ── Join with a code (placeholder) ───────────────────────
    on("btnRejoindre", "click", () => {
        const input = document.getElementById("codeInput");
        const code = input ? input.value.trim() : "";
        if (!code) return;
        alert("Parties privées pas encore disponibles.");
    });

    // ── On page load ──────────────────────────────────────────
    const codeExistant = API.getCode();
    if (codeExistant) {
        verifierPartieExistante(codeExistant);
    } else {
        afficherFormulaire();
    }

    // ── Game state polling ────────────────────────────────────
    // Once inside a lobby, phaseChange fires on every poll.
    // If the phase moved past "attente", the game started: redirect to game.html.
    API.on.phaseChange = (etat) => {
        if (etat.phase === "attente") {
            afficherLobbyPartie(etat);
        } else {
            location.href = "game.html";
        }
    };

    // ── Create a game ─────────────────────────────────────────
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

    // ── Join by code ──────────────────────────────────────────
    on("btnRejoindreCode", "click", async () => {
        const input = document.getElementById("inputCode");
        const code = input ? input.value.trim().toUpperCase() : "";
        if (!code) return;
        await rejoindrePartie(code);
    });

    // ── Start game (host) ─────────────────────────────────────
    on("btnDemarrer", "click", async () => {
        try {
            await API.demarrer();
            location.href = "game.html";
        } catch (e) {
            document.getElementById("erreur-partie").textContent = e.message;
        }
    });

    // ── Cancel game (host) ────────────────────────────────────
    on("btnReset", "click", async () => {
        if (!confirm("Annuler la partie ?")) return;
        try {
            await API.reset();
            API.clearCode();
            API.arreterPolling();
            afficherFormulaire();
        } catch (e) {
            document.getElementById("erreur-partie").textContent = e.message;
        }
    });

    // ── Leave game (player) ───────────────────────────────────
    on("btnQuitterLobby", "click", async () => {
        if (!confirm("Quitter la partie ?")) return;
        try {
            await API.quitter();
            API.clearCode();
            API.arreterPolling();
            afficherFormulaire();
        } catch (e) {
            document.getElementById("erreur-partie").textContent = e.message;
        }
    });

    // ── Leave page ────────────────────────────────────────────
    on("btnQuitter", "click", () => {
        API.arreterPolling();
        location.href = "index.php";
    });
});

// ── Check if the game still exists ───────────────────────
async function verifierPartieExistante(code) {
    try {
        const res = await fetch(`php/etat.php?code=${code}&depuis=0`);
        if (!res.ok) {
            API.clearCode();
            afficherFormulaire();
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
    }
}

// ── UI helpers ────────────────────────────────────────────
function afficherFormulaire() {
    document.getElementById("section-creer").hidden = false;
    document.getElementById("section-parties").hidden = false;
    document.getElementById("section-partie").hidden = true;
    // Start auto-refreshing the public games list
    demarrerPollingParties();
}

function afficherLobbyPartie(etat) {
    document.getElementById("section-creer").hidden = true;
    document.getElementById("section-parties").hidden = true;
    document.getElementById("section-partie").hidden = false;
    // Stop refreshing the public list while we are inside a lobby
    arreterPollingParties();

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

// ── Public games ──────────────────────────────────────────
async function chargerPartiesPubliques() {
    try {
        const data = await API.listerParties();
        afficherPartiesPubliques(data.parties ?? []);
    } catch (e) {
        console.error("Error loading public games:", e);
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

// ── Join game ─────────────────────────────────────────────
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
