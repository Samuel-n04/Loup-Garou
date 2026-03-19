import * as API from "./api.js";

// ── État local ────────────────────────────────────────────
let etat              = null;
let monRole           = null;
let cibleSelectionnee = null;
let ciblesAmants      = [];
let _dernierTs        = 0;

// ── Positions en cercle ───────────────────────────────────
function positionCercle(index, total, cx, cy, rx, ry) {
    const angle = (270 + (index / total) * 360) * (Math.PI / 180);
    return {
        x: cx + rx * Math.cos(angle),
        y: cy + ry * Math.sin(angle),
    };
}

// ── Rendu des cartes ──────────────────────────────────────
function rendreCartes(joueurs) {
    const arene = document.getElementById("arene");
    const cx    = arene.offsetWidth  / 2;
    const cy    = arene.offsetHeight / 2;
    const rx    = Math.min(cx, cy) * 0.55;
    const ry    = Math.min(cx, cy) * 0.48;

    document.querySelectorAll(".carte-joueur").forEach(e => e.remove());

    const moi      = joueurs.findIndex(j => j.id === etat.monPseudo);
    const ordonnes = [
        ...joueurs.slice(moi),
        ...joueurs.slice(0, moi),
    ];

    ordonnes.forEach((joueur, i) => {
        const estMoi = joueur.id === etat.monPseudo;
        const pos    = positionCercle(i, ordonnes.length, cx, cy, rx, ry);

        const div = document.createElement("div");
        div.className = "carte-joueur"
            + (estMoi ? " moi" : "")
            + (!joueur.vivant ? " mort" : "");
        div.dataset.id = joueur.id;
        div.style.left = pos.x + "px";
        div.style.top  = pos.y + "px";

        const imgDiv = document.createElement("div");
        imgDiv.className = "carte-img";

        if (estMoi && monRole) {
            const img = document.createElement("img");
            img.src = `sprite/${roleToSprite(monRole)}`;
            img.alt = monRole;
            imgDiv.appendChild(img);
        } else {
            const dos = document.createElement("div");
            dos.className = "dos";
            dos.textContent = "🂠";
            imgDiv.appendChild(dos);
        }

        const badge = document.createElement("div");
        badge.className = "vote-badge";
        badge.textContent = "✗";
        imgDiv.appendChild(badge);

        const pseudo = document.createElement("div");
        pseudo.className = "pseudo";
        pseudo.textContent = joueur.nom;

        div.appendChild(imgDiv);
        div.appendChild(pseudo);
        arene.appendChild(div);

        if (!estMoi && joueur.vivant) {
            div.addEventListener("click", () => selectionnerCible(joueur.id, div));
        }
    });
}

function roleToSprite(role) {
    const map = {
        "loup-garou":   "loupGarou.png",
        "villageois":   "villageois.png",
        "voyante":      "voyante.png",
        "cupidon":      "cupidon.png",
        "chasseur":     "chasseur.png",
        "sorciere":     "sorciere.png",
        "petite-fille": "petiteFille.png",
    };
    return map[role] ?? "backCard.png";
}

// ── Sélection de cible ────────────────────────────────────
function selectionnerCible(id, div) {
    if (etat?.phase === "nuit-cupidon" && monRole === "cupidon") {
        const idx = ciblesAmants.indexOf(id);
        if (idx !== -1) {
            ciblesAmants.splice(idx, 1);
            div.classList.remove("cible-vote");
        } else if (ciblesAmants.length < 2) {
            ciblesAmants.push(id);
            div.classList.add("cible-vote");
        }
        const btn = document.querySelector("#actions-zone .btn-action");
        if (btn) btn.textContent = `Lier les amants (${ciblesAmants.length}/2)`;
        return;
    }

    document.querySelectorAll(".carte-joueur").forEach(c => c.classList.remove("cible-vote"));
    cibleSelectionnee = id;
    div.classList.add("cible-vote");
}

function rendreSelectionnable(actif) {
    document.querySelectorAll(".carte-joueur:not(.moi):not(.mort)").forEach(c => {
        c.classList.toggle("selectionnable", actif);
    });
}

// ── Narrateur ─────────────────────────────────────────────
let narrTimer = null;
function afficherNarrateur(texte, duree = 5000) {
    const overlay = document.getElementById("narrateur-overlay");
    document.getElementById("narr-texte").textContent = texte;
    overlay.classList.add("visible");
    ajouterMessageChat("Narrateur", texte, "narrateur");
    clearTimeout(narrTimer);
    narrTimer = setTimeout(() => overlay.classList.remove("visible"), duree);
}

// ── Chat ──────────────────────────────────────────────────
function ajouterMessageChat(auteur, texte, type = "") {
    const zone = document.getElementById("chat-messages");
    const msg  = document.createElement("div");
    msg.className = "msg " + type;
    msg.innerHTML = `<div class="msg-auteur">${auteur}</div><div class="msg-texte">${texte}</div>`;
    zone.appendChild(msg);
    zone.scrollTop = zone.scrollHeight;
}

document.getElementById("chat-send").addEventListener("click", envoyerMessage);
document.getElementById("chat-input").addEventListener("keydown", e => {
    if (e.key === "Enter") envoyerMessage();
});

async function envoyerMessage() {
    const input = document.getElementById("chat-input");
    const texte = input.value.trim();
    if (!texte) return;
    input.value = "";
    try {
        await API.chat(texte);
    } catch (e) {
        console.error("Erreur chat :", e);
    }
}

// ── Phase ─────────────────────────────────────────────────
const MESSAGES_PHASE = {
    "distribution":  "Les rôles ont été distribués. Chacun découvre sa destinée…",
    "nuit-cupidon":  "Cupidon se réveille et tend son arc vers deux âmes…",
    "nuit-voyante":  "La Voyante ouvre les yeux dans la nuit et scrute les ombres…",
    "nuit-loups":    "Les Loups-Garous se réveillent et choisissent leur proie…",
    "nuit-sorciere": "La Sorcière consulte ses potions à la lueur de la lune…",
    "jour":          "L'aube se lève sur le village. Les habitants découvrent les victimes de la nuit…",
    "vote":          "Le village se réunit. Il est temps de voter !",
    "chasseur":      "Le Chasseur rend son dernier souffle… mais il peut encore tirer !",
    "fin":           null,
};

function mettreAJourPhase(nouvelEtat) {
    const phase = nouvelEtat.phase;
    const tour  = nouvelEtat.tour ?? 1;

    const estNuit = phase.startsWith("nuit") || phase === "distribution";
    document.body.className = estNuit ? "nuit" : "jour";
    document.getElementById("phase-icon").textContent = estNuit ? "🌙" : "☀️";

    let label = estNuit ? `Nuit ${tour}` : `Jour ${tour}`;
    if (phase === "vote") label = `Vote — Jour ${tour}`;
    if (phase === "fin")  label = "Fin de partie";
    document.getElementById("phase-label").textContent = label;

    const msg = MESSAGES_PHASE[phase];
    if (msg) afficherNarrateur(msg);

    rendreActionsZone(nouvelEtat);
    rendrePanneauRole(nouvelEtat);

    const selectionActive = (phase === "vote")
        || (phase === "nuit-loups"    && monRole === "loup-garou")
        || (phase === "nuit-voyante"  && monRole === "voyante")
        || (phase === "nuit-cupidon"  && monRole === "cupidon")
        || (phase === "chasseur"      && monRole === "chasseur")
        || (phase === "nuit-sorciere" && monRole === "sorciere");
    rendreSelectionnable(selectionActive);
}

// ── Actions zone ──────────────────────────────────────────
function rendreActionsZone(e) {
    const zone = document.getElementById("actions-zone");
    zone.innerHTML = "";

    if (e.phase === "distribution") {
        zone.appendChild(creerBouton("J'ai vu mon rôle ✓", async () => {
            await API.pret();
        }));
    }

    if (e.phase === "nuit-cupidon" && monRole === "cupidon") {
        ciblesAmants = [];
        zone.appendChild(creerBouton("Lier les amants (0/2)", async () => {
            if (ciblesAmants.length !== 2) return;
            await API.cupidon(ciblesAmants[0], ciblesAmants[1]);
            ciblesAmants = [];
            document.querySelectorAll(".carte-joueur").forEach(c => c.classList.remove("cible-vote"));
        }));
    }

    if (e.phase === "nuit-voyante" && monRole === "voyante") {
        zone.appendChild(creerBouton("Inspecter ce joueur", async () => {
            if (!cibleSelectionnee) return;
            await API.voyante(cibleSelectionnee);
            cibleSelectionnee = null;
        }));
    }

    if (e.phase === "nuit-loups" && monRole === "loup-garou") {
        zone.appendChild(creerBouton("Confirmer ma cible", async () => {
            if (!cibleSelectionnee) return;
            await API.loupVote(cibleSelectionnee);
            cibleSelectionnee = null;
        }));
    }

    if (e.phase === "jour" && e.estHote) {
        zone.appendChild(creerBouton("Lancer le vote", async () => {
            await API.demarrerVote();
        }));
    }

    if (e.phase === "vote") {
        zone.appendChild(creerBouton("Confirmer mon vote", async () => {
            if (!cibleSelectionnee) return;
            await API.vote(cibleSelectionnee);
            cibleSelectionnee = null;
        }));
    }

    if (e.phase === "chasseur" && monRole === "chasseur") {
        zone.appendChild(creerBouton("Tirer sur ce joueur", async () => {
            if (!cibleSelectionnee) return;
            await API.chasseurTire(cibleSelectionnee);
            cibleSelectionnee = null;
        }, "danger"));
    }
}

// ── Panneau rôle spécial ──────────────────────────────────
function rendrePanneauRole(e) {
    const panneau = document.getElementById("panneau-role");

    if (e.resultatVoyante && monRole === "voyante") {
        afficherPanneau(
            "Révélation de la Voyante",
            `Ce joueur est : ${e.resultatVoyante}`,
            [{ label: "Compris", fn: () => panneau.classList.remove("visible") }]
        );
        return;
    }

    if (e.phase === "nuit-sorciere" && monRole === "sorciere") {
        const btns = [];
        if (e.potionVie && e.victime) {
            btns.push({ label: `💚 Sauver ${e.victime}`, fn: async () => {
                await API.sorciere(true, null);
                panneau.classList.remove("visible");
            }});
        }
        if (e.potionMort) {
            btns.push({ label: "☠️ Empoisonner…", fn: async () => {
                if (!cibleSelectionnee) {
                    alert("Clique d'abord sur un joueur à empoisonner.");
                    panneau.classList.remove("visible");
                    return;
                }
                await API.sorciere(false, cibleSelectionnee);
                cibleSelectionnee = null;
                panneau.classList.remove("visible");
            }});
        }
        btns.push({ label: "Passer", fn: async () => {
            await API.sorciere(false, null);
            panneau.classList.remove("visible");
        }});
        const desc = e.victime
            ? `La victime de cette nuit est : ${e.victime}`
            : "Personne n'a été attaqué cette nuit.";
        afficherPanneau("Vos potions, Sorcière", desc, btns);
        return;
    }

    if (e.phase === "nuit-cupidon" && monRole === "cupidon") {
        panneau.classList.remove("visible");
        return;
    }

    if (e.phase === "fin") {
        const camp = { villageois: "Les Villageois", loups: "Les Loups-Garous", amants: "Les Amants" };
        afficherPanneau(
            "Partie terminée",
            `Victoire de : ${camp[e.vainqueur] ?? e.vainqueur}`,
            [{ label: "Retour au lobby", fn: () => location.href = "lobby.html" }]
        );
        return;
    }

    panneau.classList.remove("visible");
}

function afficherPanneau(titre, desc, btns) {
    document.getElementById("panneau-titre").textContent = titre;
    document.getElementById("panneau-desc").textContent  = desc;
    const zone = document.getElementById("panneau-btns");
    zone.innerHTML = "";
    btns.forEach(b => zone.appendChild(creerBouton(b.label, b.fn, b.danger ? "danger" : "")));
    document.getElementById("panneau-role").classList.add("visible");
}

function creerBouton(label, fn, extra = "") {
    const btn = document.createElement("button");
    btn.className = "btn-action " + extra;
    btn.textContent = label;
    btn.addEventListener("click", fn);
    return btn;
}

// ── Quitter la partie ─────────────────────────────────────
document.getElementById("btnQuitter").addEventListener("click", async () => {
    if (!confirm("Quitter la partie ?")) return;
    try {
        await API.quitter();
        API.arreterPolling();
        location.href = "lobby.html";
    } catch (e) {
        console.error("Erreur quitter :", e);
        location.href = "lobby.html";
    }
});

// ── Polling ───────────────────────────────────────────────
API.on.phaseChange = (nouvelEtat) => {
    etat    = nouvelEtat;
    monRole = nouvelEtat.monRole;

    if (nouvelEtat.messages?.length) {
        _dernierTs = nouvelEtat.messages.at(-1).ts;
        nouvelEtat.messages.forEach(m => {
            const type = m.auteur === etat.monPseudo ? "moi" : "";
            ajouterMessageChat(m.auteur, m.texte, type);
        });
    }

    document.getElementById("mon-role-badge").textContent =
        monRole ? `Rôle : ${monRole}` : "";

    rendreCartes(nouvelEtat.joueurs);
    mettreAJourPhase(nouvelEtat);
};

API.on.fin = (nouvelEtat) => {
    etat = nouvelEtat;
    rendreCartes(nouvelEtat.joueurs);
    mettreAJourPhase(nouvelEtat);
};

API.demarrerPolling(1500);

window.addEventListener("resize", () => {
    if (etat) rendreCartes(etat.joueurs);
});
