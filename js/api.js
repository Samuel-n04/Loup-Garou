// ============================================================
//  api.js — Couche réseau côté client
//  L'identité du joueur est gérée par la session PHP (côté serveur)
//  Pas d'idJoueur côté JS — tout passe par le cookie de session
// ============================================================

let _phaseActuelle = null;
let _pollingTimer = null;

// ── Callbacks à brancher depuis ton code UI ────────────────
export const on = {
    phaseChange: null, // (etat) => {}
    fin: null, // (etat) => {}
};

// ============================================================
//  POLLING — appel toutes les secondes
// ============================================================
export function demarrerPolling(intervalMs = 1000) {
    if (_pollingTimer) return;
    _pollingTimer = setInterval(async () => {
        try {
            const etat = await getEtat();
            _traiterEtat(etat);
        } catch (e) {
            console.error("[polling] erreur :", e);
        }
    }, intervalMs);
}

export function arreterPolling() {
    clearInterval(_pollingTimer);
    _pollingTimer = null;
}

async function getEtat() {
    const res = await fetch("php/etat.php");
    if (res.status === 401) {
        location.href = "login.html";
        return;
    }
    if (!res.ok) throw new Error(await res.text());
    return res.json();
}

function _traiterEtat(etat) {
    if (!etat) return;
    if (etat.phase !== _phaseActuelle) {
        _phaseActuelle = etat.phase;
        on.phaseChange?.(etat);
    }
    if (etat.phase === "fin") {
        arreterPolling();
        on.fin?.(etat);
    }
}

// ============================================================
//  ACTIONS — envoi au serveur (POST JSON)
//  Pas besoin d'envoyer l'idJoueur, la session PHP s'en charge
// ============================================================
async function action(data) {
    const res = await fetch("php/action.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    });
    if (res.status === 401) {
        location.href = "login.html";
        return;
    }
    const json = await res.json();
    if (!res.ok) throw new Error(json.erreur ?? "Erreur serveur");
    return json;
}

// ── API publique ───────────────────────────────────────────
export const rejoindre = () => action({ action: "rejoindre" });
export const demarrer = () => action({ action: "demarrer" });
export const pret = () => action({ action: "pret" });
export const cupidon = (idA, idB) => action({ action: "cupidon", idA, idB });
export const voyante = (idCible) => action({ action: "voyante", idCible });
export const loupVote = (idCible) => action({ action: "loupVote", idCible });
export const sorciere = (utiliserVie, idCibleMort = null) =>
    action({ action: "sorciere", utiliserVie, idCibleMort });
export const demarrerVote = () => action({ action: "demarrerVote" });
export const vote = (idCible) => action({ action: "vote", idCible });
export const chasseurTire = (idCible) =>
    action({ action: "chasseurTire", idCible });
export const finNuit = () => action({ action: "finNuit" });

// ── Endpoints séparés ─────────────────────────────────────
async function requete(url, data = null) {
    const options = data
        ? {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(data),
          }
        : { method: "GET" };
    const res = await fetch(url, options);
    if (res.status === 401) {
        location.href = "login.html";
        return;
    }
    const json = await res.json();
    if (!res.ok) throw new Error(json.erreur ?? "Erreur serveur");
    return json;
}

export const creerPartie = (roles, joueurMax) =>
    requete("php/creategame.php", { roles, joueurMax });
export const reset = () => requete("php/reset.php", {});
