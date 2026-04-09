import * as API from "./api.js";

// ---------------- Configuration -----------------
const ROLE_SPRITES = {
    "loup-garou": "loupGarou.png",
    villageois: "villageois.png",
    voyante: "voyante.png",
    sorciere: "sorciere.png",
    chasseur: "chasseur.png",
    cupidon: "cupidon.png",
    "petite-fille": "petite-fille.png",
};

// ---------------- State -----------------
let etatActuel = null;
let monPseudo = null;
let selections = []; // For roles like Cupidon that require 2 targets

// ---------------- Cooldown timer -----------------
// When a player must act but doesn't, the server auto-advances after COOLDOWN_SECONDES.
// The client shows a countdown and sends a "tick" action when it reaches zero.
let _cooldownPhaseTs   = null; // phaseDebutTs of the running interval (detects phase changes)
let _cooldownFin       = null; // Unix ms when the cooldown expires
let _cooldownInterval  = null; // setInterval handle
let _tickEnCours       = false; // prevents concurrent tick() calls

function mettreAJourCooldown(etat) {
    const phasesSansAction = ["attente", "fin"];
    if (!etat.phaseDebutTs || !etat.cooldownSecondes || phasesSansAction.includes(etat.phase)) {
        _stopperCooldown();
        return;
    }

    const finMs = (etat.phaseDebutTs + etat.cooldownSecondes) * 1000;

    if (_cooldownPhaseTs !== etat.phaseDebutTs) {
        // Phase changed or first tick — restart the interval.
        _stopperCooldown();
        _cooldownPhaseTs = etat.phaseDebutTs;
        _cooldownFin     = finMs;
        _afficherCooldown();
        _cooldownInterval = setInterval(_afficherCooldown, 1000);
    } else {
        _cooldownFin = finMs; // keep in sync with server
    }
}

function _stopperCooldown() {
    if (_cooldownInterval) {
        clearInterval(_cooldownInterval);
        _cooldownInterval = null;
    }
    _cooldownPhaseTs = null;
    _cooldownFin     = null;
    const el = document.getElementById("cooldown-timer");
    if (el) el.textContent = "";
}

async function _afficherCooldown() {
    const el = document.getElementById("cooldown-timer");
    if (!el || !_cooldownFin) return;

    const restant = Math.ceil((_cooldownFin - Date.now()) / 1000);

    if (restant <= 0) {
        el.textContent = "Phase en cours d'auto-avancement…";
        clearInterval(_cooldownInterval);
        _cooldownInterval = null;
        if (!_tickEnCours) {
            _tickEnCours = true;
            try {
                await API.tick();
            } catch (e) {
                console.error("[cooldown] tick error:", e);
            } finally {
                _tickEnCours = false;
            }
        }
    } else {
        el.textContent = `⏱ Auto-avance dans ${restant}s`;
    }
}

// ---------------- Utils -----------------
function escapeHTML(str) {
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

/**
 * Simple scale animation using the Web Animations API.
 * Used for quick feedback on badges and card selections.
 */
function animateScale(el) {
    if (!el) return;
    el.animate(
        [
            { transform: "scale(1)" },
            { transform: "scale(1.2)" },
            { transform: "scale(1)" },
        ],
        {
            duration: 200,
            easing: "ease-out",
        },
    );
}

/**
 * Flips a card with the same animation as the help page:
 * an extra 720° spin plus a 180° flip, then snaps cleanly.
 */
function flipCarte(el) {
    if (el.classList.contains("flipped") || el.dataset.animFlip === "1") return;
    el.dataset.animFlip = "1";

    const inner = el.querySelector(".carte-inner");
    inner.style.transition = "transform 0.6s cubic-bezier(0.15, 0.85, 0.4, 1)";
    inner.style.transform = "rotateY(900deg)"; // 180° flip + 720° decorative spin

    inner.addEventListener("transitionend", function handler() {
        inner.removeEventListener("transitionend", handler);
        inner.style.transition = "none";
        inner.style.transform = "rotateY(180deg)";
        el.classList.add("flipped");
        el.dataset.animFlip = "0";
    }, { once: true });
}

function roleToSprite(role) {
    return ROLE_SPRITES[role] || "backCard.png";
}

// Returns true if it is the local player's turn to act in the current phase
function estMonTour(etat) {
    if (!etat.vivant) {
        return etat.phase === "chasseur" && etat.monRole === "chasseur";
    }
    switch (etat.phase) {
        case "nuit-cupidon":       return etat.monRole === "cupidon";
        case "nuit-petite-fille":  return etat.monRole === "petite-fille";
        case "nuit-voyante":       return etat.monRole === "voyante";
        case "nuit-loups":         return etat.monRole === "loup-garou";
        case "nuit-sorciere":      return etat.monRole === "sorciere";
        case "vote":               return true;
        case "chasseur":           return etat.monRole === "chasseur";
        default:                   return false;
    }
}

// Returns true if player j is a valid clickable target in the current phase
function cardCibleValide(etat, j) {
    if (j.id === monPseudo || !j.vivant) return false;
    switch (etat.phase) {
        case "nuit-cupidon":      return etat.monRole === "cupidon";
        case "nuit-voyante":      return etat.monRole === "voyante";
        case "nuit-petite-fille": return false; // binary choice: spy or pass, no targeting
        case "nuit-loups":        return etat.monRole === "loup-garou";
        case "nuit-sorciere":     return etat.monRole === "sorciere" && !!etat.potionMort;
        case "vote":              return !!etat.vivant;
        case "chasseur":          return etat.monRole === "chasseur";
        default:                  return false;
    }
}

// ---------------- Initialization -----------------
async function initialiser() {
    const code = API.getCode();
    if (!code) {
        location.href = "index.php";
        return;
    }

    API.on.phaseChange = (etat) => traiterMiseAJour(etat);
    API.demarrerPolling(2000);

    try {
        const res = await fetch(`php/etat.php?code=${code}`);
        const etat = await res.json();
        if (etat.erreur) throw new Error(etat.erreur);
        monPseudo = etat.monPseudo;
        traiterMiseAJour(etat);
    } catch (e) {
        console.error("Initialization error:", e);
    }

    setupChat();
    setupButtons();
}

function setupChat() {
    const chatInput = document.getElementById("chat-input");
    const chatBtn = document.getElementById("chat-send");

    const envoyerMessage = async () => {
        const texte = chatInput.value.trim();
        if (!texte) return;
        try {
            await API.chat(texte);
            chatInput.value = "";
        } catch (e) {
            console.error(e);
        }
    };

    chatBtn.onclick = envoyerMessage;
    chatInput.onkeypress = (e) => {
        if (e.key === "Enter") envoyerMessage();
    };
}

function setupButtons() {
    window.onresize = function () {
        if (etatActuel) renderJoueurs(etatActuel);
    };

    document.getElementById("btnQuitter").onclick = async () => {
        if (!confirm("Quitter la partie ?")) return;
        await API.quitter();
        API.clearCode();
        location.href = "index.php";
    };
}

// ---------------- Core Logic -----------------
function traiterMiseAJour(etat) {
    const anciennePhase = etatActuel?.phase;
    etatActuel = etat;

    // Day/Night background
    const estNuit =
        etat.phase.startsWith("nuit") || etat.phase === "distribution";
    document.body.className = estNuit ? "nuit" : "jour";

    // Top bar
    document.getElementById("phase-label").textContent =
        `${tr(etat.phase)} - Tour ${etat.tour}`;
    document.getElementById("phase-icon").textContent = estNuit ? "Nuit" : "Jour";
    document.getElementById("mon-role-badge").textContent =
        `Rôle : ${etat.monRole || "..."}`;

    // Reset selections on phase change
    if (anciennePhase !== etat.phase) {
        selections = [];
    }

    // Restore a vote already cast (persists across polls so the UI stays consistent)
    if (selections.length === 0) {
        if (etat.phase === "vote" && etat.votesJour?.[monPseudo]) {
            selections = [etat.votesJour[monPseudo]];
        } else if (etat.phase === "nuit-loups" && etat.votesLoups?.[monPseudo]) {
            selections = [etat.votesLoups[monPseudo]];
        }
    }

    // Narrator overlay
    const narrOverlay = document.getElementById("narrateur-overlay");
    const msg = genererMessageNarrateur(etat);
    if (msg) {
        document.getElementById("narr-texte").textContent = msg;
        narrOverlay.classList.add("visible");
    } else {
        narrOverlay.classList.remove("visible");
    }

    mettreAJourCooldown(etat);
    renderJoueurs(etat);
    renderChat(etat.messages);
    renderActions(etat);
    renderSidebar(etat);
}

function renderSidebar(etat) {
    const container = document.getElementById("joueurs-container");
    if (!container) return;

    container.innerHTML = etat.joueurs
        .map((j) => {
            const cls = j.vivant ? "" : "mort";
            const roleStr =
                !j.vivant || etat.phase === "fin"
                    ? ` (${escapeHTML(tr(j.role))})`
                    : "";
            const suffixe = j.id === monPseudo ? " (Moi)" : "";
            return `<div class="ligne-joueur ${cls}"><span>${escapeHTML(j.nom)}${suffixe}${roleStr}</span></div>`;
        })
        .join("");
}

function renderJoueurs(etat) {
    const arene = document.getElementById("arene");
    const centerX = arene.clientWidth / 2;
    const centerY = arene.clientHeight / 2;
    const radius = Math.min(centerX * 0.72, centerY * 0.72, 320);

    // Deal order: other players first, local player's card last
    const autresJoueurs = etat.joueurs.filter((j) => j.id !== monPseudo);
    const dealOrder = [...autresJoueurs, ...etat.joueurs.filter((j) => j.id === monPseudo)];

    const idsActuels = etat.joueurs.map((j) => j.id);
    document.querySelectorAll(".carte-joueur").forEach((el) => {
        if (!idsActuels.includes(el.dataset.id)) el.remove();
    });

    // Equal spacing around the full circle: local player at the bottom (π/2)
    const N = etat.joueurs.length;
    const localIndex = etat.joueurs.findIndex((j) => j.id === monPseudo);

    etat.joueurs.forEach((j, absoluteIndex) => {
        let el = document.querySelector(`.carte-joueur[data-id="${j.id}"]`);
        let estNouveau = false;

        if (!el) {
            estNouveau = true;
            el = document.createElement("div");
            el.className = "carte-container carte-joueur";
            el.dataset.id = j.id;
            el.style.left = `${centerX}px`;
            el.style.top = `${centerY}px`;
            el.style.opacity = "0";

            el.innerHTML = `
                <div class="pseudo"></div>
                <div class="carte-inner">
                    <div class="face front"><img src="sprite/backCard.png"></div>
                    <div class="face back"><img src="sprite/backCard.png"></div>
                </div>
                <div class="vote-badge">0</div>
            `;

            el.onclick = () => cliquerJoueur(j.id);
            arene.appendChild(el);
        }

        // --- TARGET POSITION ---
        // Distribute all players equally around the circle; local player anchored at the bottom
        const relativeIndex = (absoluteIndex - localIndex + N) % N;
        const angle = Math.PI / 2 + relativeIndex * (2 * Math.PI / N);
        const targetX = centerX + Math.cos(angle) * radius;
        const targetY = centerY + Math.sin(angle) * radius;

        if (estNouveau) {
            const dealIndex = dealOrder.findIndex((x) => x.id === j.id);
            const rotation = dealIndex % 2 === 0 ? 22 : -22;

            setTimeout(() => {
                el.style.opacity = "1";
                el.style.left = `${targetX}px`;
                el.style.top = `${targetY}px`;

                // Deal animation: card flies out from the center and lands in place
                const inner = el.querySelector(".carte-inner");
                const anim = inner.animate(
                    [
                        { transform: `scale(0.08) rotate(${rotation}deg)`, opacity: 0 },
                        { transform: "scale(1.1) rotate(0deg)", opacity: 1, offset: 0.65 },
                        { transform: "scale(1) rotate(0deg)", opacity: 1 },
                    ],
                    { duration: 520, easing: "ease-out" },
                );
                // Cancel the animation object so it doesn't block the CSS flip
                anim.onfinish = () => anim.cancel();
            }, 130 * dealIndex);
        } else {
            el.style.left = `${targetX}px`;
            el.style.top = `${targetY}px`;
        }

        // --- SYSTEMATIC UPDATE ---
        const pseudoEl = el.querySelector(".pseudo");
        pseudoEl.textContent = j.nom + (j.id === monPseudo ? " (Moi)" : "");

        el.classList.toggle("mort", !j.vivant);
        el.classList.toggle("selected", selections.includes(j.id));
        el.classList.toggle("ma-carte", j.id === monPseudo);

        // --- PHASE-BASED STATE CLASSES ---
        const monTour = estMonTour(etat);
        const valide = monTour && cardCibleValide(etat, j);
        // Invalid = alive player who is not me and not a valid target
        const invalide = monTour && !valide && j.id !== monPseudo && j.vivant;

        // Ally: revealed lover, or wolf who already voted (identified by votesLoups keys)
        const estAmant = etat.monAmant === j.id;
        const estLoupVu = etat.monRole === "loup-garou"
            && etat.phase === "nuit-loups"
            && etat.votesLoups
            && j.id in etat.votesLoups;
        const estLoupRevele = etat.monRole === "petite-fille"
            && etat.resultatEspionnage?.loups?.includes(j.id);

        // Victim: the wolf target this night, visible to the witch
        const estVictime = etat.monRole === "sorciere" && etat.victime === j.id;

        el.classList.toggle("cible-valide", valide && !selections.includes(j.id));
        el.classList.toggle("cible-invalide", invalide);
        el.classList.toggle("est-allie", estAmant || estLoupVu || estLoupRevele);
        el.classList.toggle("est-victime", estVictime);

        // Card reveal logic
        const estCibleVoyante =
            etat.monRole === "voyante" && etat.cibleVoyante === j.id;
        const estMonAmant = etat.monAmant === j.id;
        const estLoupDecouvert =
            etat.monRole === "petite-fille" &&
            etat.resultatEspionnage?.loups?.includes(j.id);
        const doitEtreRevele =
            !j.vivant ||
            j.id === monPseudo ||
            etat.phase === "fin" ||
            estCibleVoyante ||
            estMonAmant ||
            estLoupDecouvert;

        if (doitEtreRevele && !el.classList.contains("flipped") && el.dataset.animFlip !== "1") {
            const roleImg = el.querySelector(".back img");
            const roleAReveler = estCibleVoyante
                ? etat.resultatVoyante || j.role
                : (j.id === monPseudo ? etat.monRole : j.role);
            roleImg.src = `sprite/${roleToSprite(roleAReveler)}`;
            flipCarte(el);
        }

        // Vote badges
        const badge = el.querySelector(".vote-badge");
        let nbVotes = 0;
        if (etat.votesLoups && etat.monRole === "loup-garou") {
            nbVotes = Object.values(etat.votesLoups).filter(
                (cid) => cid === j.id,
            ).length;
        } else if (etat.phase === "vote" && etat.votesJour) {
            nbVotes = Object.values(etat.votesJour).filter(
                (cid) => cid === j.id,
            ).length;
        }

        if (nbVotes > 0) {
            badge.textContent = nbVotes;
            badge.style.display = "flex";
            animateScale(badge);
        } else {
            badge.style.display = "none";
        }
    });
}

function cliquerJoueur(id) {
    if (!etatActuel) return;
    // Cannot target yourself
    if (id === monPseudo) return;
    // Only a dead hunter can interact during the hunter phase
    const joueurVivant = etatActuel.vivant ?? true;
    if (!joueurVivant && !(etatActuel.phase === "chasseur" && etatActuel.monRole === "chasseur")) return;
    // Non-interactive phase: ignore click
    if (!estMonTour(etatActuel)) return;

    const joueur = etatActuel.joueurs.find((j) => j.id === id);
    if (!joueur || (!joueur.vivant && etatActuel.phase !== "chasseur")) return;

    if (
        etatActuel.phase === "nuit-cupidon" &&
        etatActuel.monRole === "cupidon"
    ) {
        if (selections.includes(id)) {
            selections = selections.filter((sid) => sid !== id);
        } else if (selections.length < 2) {
            selections.push(id);
        }
    } else {
        selections = [id];
    }
    traiterMiseAJour(etatActuel); // Refresh UI
}

function renderActions(etat) {
    const zone = document.getElementById("actions-zone");
    zone.innerHTML = "";

    if (etat.phase === "distribution") {
        addBtn("Je suis prêt", () => API.pret());
        return;
    }

    if (etat.phase === "jour" && etat.estHote) {
        addBtn("Lancer le vote du village", () => API.demarrerVote());
    }

    if (!etat.vivant && etat.phase !== "chasseur") return;

    const cible = selections[0];

    switch (etat.phase) {
        case "nuit-cupidon":
            if (etat.monRole === "cupidon" && selections.length === 2) {
                addBtn("Lier ces deux cœurs", () =>
                    API.cupidon(selections[0], selections[1]),
                );
            }
            break;
        case "nuit-voyante":
            if (etat.monRole === "voyante" && cible && cible !== monPseudo) {
                addBtn(`Observer ${getName(cible)}`, () => API.voyante(cible));
            }
            break;
        case "nuit-petite-fille":
            if (etat.monRole === "petite-fille") {
                addBtn("Espionner (risque 1/3)", () => API.petiteFilleEspionne());
                addBtn("Ne pas espionner", () => API.petiteFillePasser());
            }
            break;
        case "nuit-loups":
            if (etat.monRole === "loup-garou" && cible && cible !== monPseudo) {
                addBtn(`Dévorer ${getName(cible)}`, () => API.loupVote(cible));
            }
            break;
        case "nuit-sorciere":
            if (etat.monRole === "sorciere") {
                if (etat.victime && etat.potionVie) {
                    addBtn(`Sauver ${getName(etat.victime)}`, () =>
                        API.sorciere(true),
                    );
                }
                if (cible && cible !== monPseudo && etat.potionMort) {
                    addBtn(
                        `Empoisonner ${getName(cible)}`,
                        () => API.sorciere(false, cible),
                        "danger",
                    );
                }
                addBtn("Ne rien faire", () => API.sorciere(false));
            }
            break;
        case "vote":
            if (cible && cible !== monPseudo) {
                addBtn(`Voter contre ${getName(cible)}`, () => API.vote(cible));
            }
            break;
        case "chasseur":
            if (etat.monRole === "chasseur" && cible && cible !== monPseudo) {
                addBtn(
                    `Abattre ${getName(cible)}`,
                    () => API.chasseurTire(cible),
                    "danger",
                );
            }
            break;
    }
}

function addBtn(text, onclick, cls = "") {
    const btn = document.createElement("button");
    btn.className = "btn-action " + cls;
    btn.textContent = text;
    btn.onclick = onclick;
    document.getElementById("actions-zone").appendChild(btn);
    animateScale(btn);
}

function getName(id) {
    return etatActuel.joueurs.find((j) => j.id === id)?.nom || id;
}

function tr(phase) {
    const trans = {
        attente: "Attente",
        distribution: "Distribution",
        "nuit-cupidon": "Cupidon",
        "nuit-voyante": "Voyante",
        "nuit-petite-fille": "Petite Fille",
        "nuit-loups": "Loups-Garous",
        "nuit-sorciere": "Sorcière",
        jour: "Réveil",
        vote: "Vote du village",
        chasseur: "Chasseur",
        fin: "Fin de partie",
        "loup-garou": "Loup-Garou",
        villageois: "Villageois",
        voyante: "Voyante",
        sorciere: "Sorcière",
        cupidon: "Cupidon",
        "petite-fille": "Petite-fille",
    };
    return trans[phase] || phase;
}

function genererMessageNarrateur(etat) {
    const monTour = (role) => etat.monRole === role && etat.vivant;

    switch (etat.phase) {
        case "distribution":
            return "Les rôles sont distribués. Gardez votre secret...";

        case "nuit-cupidon":
            // The narrator calls the role out loud — everyone hears it.
            // Only Cupidon sees the action prompt.
            return monTour("cupidon")
                ? "Cupidon, réveille-toi ! Désigne deux âmes que tu vas lier pour toujours."
                : "Cupidon, réveille-toi !";

        case "nuit-voyante":
            return monTour("voyante")
                ? "Voyante, ouvre les yeux ! Sonde l'âme d'un habitant du village."
                : "Voyante, ouvre les yeux !";

        case "nuit-petite-fille":
            return monTour("petite-fille")
                ? "Petite Fille, tu peux entrouvrir les yeux... Oses-tu espionner les Loups ? (1 chance sur 3 d'être repérée)"
                : "Petite Fille, tu peux entrouvrir les yeux...";

        case "nuit-loups":
            if (monTour("loup-garou")) {
                return etat.alerteEspionnage
                    ? "Loups-Garous, réveillez-vous ! La Petite Fille vous observe... Désignez votre proie avec prudence."
                    : "Loups-Garous, réveillez-vous ! Désignez votre prochaine victime.";
            }
            return "Loups-Garous, réveillez-vous !";

        case "nuit-sorciere":
            return monTour("sorciere")
                ? "Sorcière, réveille-toi ! Utiliseras-tu tes potions cette nuit ?"
                : "Sorcière, réveille-toi !";

        case "jour":
            return "Le soleil se lève sur un village meurtri...";

        case "vote":
            return "Le village doit voter pour éliminer un suspect.";

        case "chasseur":
            // The hunter IS dead during this phase, so monTour() (which checks etat.vivant)
            // would always return false for him — check the role directly instead.
            return etat.monRole === "chasseur"
                ? "Chasseur, dans ton dernier souffle, désigne ta cible !"
                : "Le Chasseur va rendre son dernier souffle...";

        case "fin": {
            const v = etat.vainqueur;
            let res = "Fin de la partie. ";
            if (v === "loups")
                res += "Les Loups-Garous ont dévoré tout le village !";
            else if (v === "amants")
                res += "L'amour a triomphé ! Les amants ont survécu.";
            else if (v === "villageois")
                res += "Le Village a exterminé tous les Loups-Garous !";
            else
                res += "La partie a été annulée.";
            return res;
        }

        default:
            return null;
    }
}

function renderChat(messages) {
    if (!messages) return;
    const container = document.getElementById("chat-messages");
    const lastTs = parseInt(container.lastElementChild?.dataset.ts || 0);

    messages.forEach((m) => {
        if (m.ts <= lastTs) return;
        const div = document.createElement("div");
        div.className = "msg" + (m.auteur === monPseudo ? " moi" : "");
        div.dataset.ts = m.ts;

        const auteurEl = document.createElement("span");
        auteurEl.className = "msg-auteur";
        auteurEl.textContent = m.auteur;

        const texteEl = document.createElement("span");
        texteEl.className = "msg-texte";
        texteEl.textContent = m.texte;

        div.appendChild(auteurEl);
        div.appendChild(texteEl);
        container.appendChild(div);
    });
    if (messages.length > 0) container.scrollTop = container.scrollHeight;
}

window.onload = initialiser;
