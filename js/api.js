// ============================================================
//  api.js — Couche réseau côté client
//  Le code de partie est stocké en sessionStorage
// ============================================================

let _phaseActuelle = null;
let _pollingTimer = null;
let _dernierTs = 0;

// ── Code de partie ────────────────────────────────────────
export function setCode(code) {
    sessionStorage.setItem("codePartie", code);
}

export function getCode() {
    return sessionStorage.getItem("codePartie") ?? "";
}

export function clearCode() {
    sessionStorage.removeItem("codePartie");
}

export function setDernierTs(ts) {
    if (ts > _dernierTs) {
        _dernierTs = ts;
    }
}

// ── Callbacks ─────────────────────────────────────────────
export const on = {
    phaseChange: null,
    fin: null,
};

// ── Polling ───────────────────────────────────────────────
export function demarrerPolling(intervalMs = 1500) {
    if (_pollingTimer) return;
    _pollingTimer = setInterval(async () => {
        try {
            const etat = await getEtat();
            if (etat) _traiterEtat(etat);
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
    const code = getCode();
    if (!code) return null;
    const res = await fetch(`php/etat.php?code=${code}&depuis=${_dernierTs}`);
    if (res.status === 401) {
        location.href = "login.html";
        return null;
    }
    if (!res.ok) throw new Error(await res.text());
    return res.json();
}

function _traiterEtat(etat) {
    if (!etat || !etat.phase) return;

    if (etat.messages && etat.messages.length > 0) {
        const lastMsgTs = Math.max(...etat.messages.map(m => m.ts));
        setDernierTs(lastMsgTs);
    }

    on.phaseChange?.(etat);
    _phaseActuelle = etat.phase;
    
    if (etat.phase === "fin") {
        arreterPolling();
        on.fin?.(etat);
    }
}

// ── Actions ───────────────────────────────────────────────
async function action(data) {
    const res = await fetch("php/action.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ...data, code: getCode() }),
    });
    if (res.status === 401) {
        location.href = "login.html";
        return;
    }
    const json = await res.json();
    if (!res.ok) throw new Error(json.erreur ?? "Erreur serveur");
    return json;
}

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

// ── API publique ──────────────────────────────────────────
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
export const petiteFilleEspionne = () => action({ action: "petiteFilleEspionne" });
export const petiteFillePasser   = () => action({ action: "petiteFillePasser" });
export const finNuit = () => action({ action: "finNuit" });
export const chat = (texte) => action({ action: "chat", texte });
export const quitter = () => action({ action: "quitter" });

// ── Endpoints séparés ─────────────────────────────────────
export const creerPartie = (roles, joueurMax, estPublique) =>
    requete("php/creategame.php", { roles, joueurMax, public: estPublique });

export const reset = (codeSpecifique = null) =>
    requete("php/reset.php", { code: codeSpecifique || getCode() });

export const listerParties = () => requete("php/parties.php");

export const rejoindreParCode = (code) =>
    requete("php/action.php", { action: "rejoindre", code });
