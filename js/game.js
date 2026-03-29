import * as API from "./api.js";

// ── Vérifier qu'un code de partie est présent ─────────────
if (!API.getCode()) {
    location.href = "lobby.html";
}

// ── État local ────────────────────────────────────────────
let etat = null;
let monRole = null;
let cibleSelectionnee = null;
let ciblesAmants = [];
let modeSelection = "normal"; // normal | poison | cupidon
let _lastNarratedPhase = null;
let _lastVoyanteResult = null;
let _lastSorciereTour = -1;

// ── Positions en cercle ───────────────────────────────────
function positionCercle(index, total, cx, cy, rx, ry) {
    const angle = (90 + (index / total) * 360) * (Math.PI / 180);
    return {
        x: cx + rx * Math.cos(angle),
        y: cy + ry * Math.sin(angle),
    };
}

// ── Rendu des cartes ──────────────────────────────────────
function rendreCartes(joueurs) {
    if (!etat) return;

    const arene = document.getElementById("arene");
    const cx = arene.offsetWidth / 2;
    const cy = arene.offsetHeight / 2;
    const rx = Math.min(cx, cy) * 0.55;
    const ry = Math.min(cx, cy) * 0.48;

    const moiIndex = joueurs.findIndex((j) => j.id === etat.monPseudo);
    if (moiIndex === -1) return;

    const ordonnes = [
        ...joueurs.slice(moiIndex),
        ...joueurs.slice(0, moiIndex),
    ];

    const cartesExistantes = new Map();
    document.querySelectorAll(".carte-joueur").forEach((el) => {
        cartesExistantes.set(el.dataset.id, el);
    });

    ordonnes.forEach((joueur, i) => {
        const estMoi = joueur.id === etat.monPseudo;
        const pos = positionCercle(i, ordonnes.length, cx, cy, rx, ry);

        let div = cartesExistantes.get(joueur.id);

        // 🆕 Création si n'existe pas
        if (!div) {
            div = document.createElement("div");
            div.className = "carte-joueur";
            div.dataset.id = joueur.id;

            const imgDiv = document.createElement("div");
            imgDiv.className = "carte-img";

            const pseudo = document.createElement("div");
            pseudo.className = "pseudo";

            div.appendChild(imgDiv);
            div.appendChild(pseudo);
            arene.appendChild(div);

            // -- Animation de distribution --
            div.style.transition = "none"; // Annuler les transitions pour le positionnement initial
            div.style.left = cx + "px";
            div.style.top = cy + "px";
            div.style.transform = "scale(0)";

            // Event listener ajouté UNE SEULE FOIS
            div.addEventListener("click", () => {
                if (!joueur.vivant || estMoi) return;
                selectionnerCible(joueur.id, div);
            });
        }

        // 🆕 Mise à jour classes
        div.classList.toggle("moi", estMoi);
        div.classList.toggle("mort", !joueur.vivant);

        // 🆕 Position (et animation)
        requestAnimationFrame(() => {
            div.style.transition = ""; // Rétablir les transitions CSS
            div.style.left = pos.x + "px";
            div.style.top = pos.y + "px";
            div.style.transform = "scale(1)";
        });

        // 🆕 Image
        const imgDiv = div.querySelector(".carte-img");
        imgDiv.innerHTML = "";

        if (estMoi && monRole) {
            const img = document.createElement("img");
            img.src = `sprite/${roleToSprite(monRole)}`;
            img.alt = monRole;
            imgDiv.appendChild(img);
        } else {
            const dos = document.createElement("img");
            dos.src = "sprite/backCard.png";
            dos.alt = "dos";
            dos.style.width = "100%";
            dos.style.height = "100%";
            dos.style.objectFit = "cover";
            imgDiv.appendChild(dos);
        }

        // Badge vote
        const badge = document.createElement("div");
        badge.className = "vote-badge";
        badge.textContent = "✗";
        imgDiv.appendChild(badge);

        // 🆕 Pseudo
        div.querySelector(".pseudo").textContent = joueur.nom;
    });

    // 🆕 Supprimer les joueurs qui n'existent plus
    cartesExistantes.forEach((div, id) => {
        if (!joueurs.find((j) => j.id === id)) {
            div.remove();
        }
    });
}

function roleToSprite(role) {
    const map = {
        "loup-garou": "loupGarou.png",
        villageois: "villageois.png",
        voyante: "voyante.png",
        cupidon: "cupidon.png",
        chasseur: "chasseur.png",
        sorciere: "sorciere.png",
        "petite-fille": "petiteFille.png",
    };
    return map[role] ?? "backCard.png";
}

// ── Animation mort (flip comme help.js) ──────────────────
function flipMort(carteDiv, joueur) {
    const inner = carteDiv.querySelector(".carte-img");
    if (!inner || carteDiv.dataset.flipped === "true") return;
    carteDiv.dataset.flipped = "true";

    // Créer une structure front/back pour le flip 3D
    const front = document.createElement("div");
    front.className = "flip-face flip-front";
    const imgFront = document.createElement("img");
    imgFront.src = "sprite/backCard.png";
    imgFront.alt = "Dos de carte";
    front.appendChild(imgFront);

    const back = document.createElement("div");
    back.className = "flip-face flip-back";
    const imgBack = document.createElement("img");
    imgBack.src = `sprite/${roleToSprite(joueur.role)}`;
    imgBack.alt = joueur.role;
    back.appendChild(imgBack);

    // Vider l'intérieur de la carte et ajouter les nouvelles faces
    inner.innerHTML = "";
    inner.appendChild(front);
    inner.appendChild(back);

    // Appliquer le style pour la 3D
    inner.style.transformStyle = "preserve-3d";
    inner.style.position = "relative";
    
    // Forcer un reflow pour s'assurer que l'état initial est appliqué
    void inner.offsetWidth;

    // Lancer l'animation
    inner.style.transition = "transform 0.8s cubic-bezier(0.2, 1.2, 0.3, 1)";
    inner.style.transform = "rotateY(180deg)";

    // Ajouter la classe 'mort' après un court délai pour l'effet visuel
    setTimeout(() => {
        carteDiv.classList.add("mort");
    }, 300);
}

// Suivre les joueurs vivants pour détecter les morts
let _joueursVivants = {};

function detecterMorts(joueurs) {
    joueurs.forEach((j) => {
        if (_joueursVivants[j.id] === true && j.vivant === false) {
            // Ce joueur vient de mourir
            const carteDiv = document.querySelector(
                `.carte-joueur[data-id="${j.id}"]`,
            );
            if (carteDiv) flipMort(carteDiv, j);
        }
        _joueursVivants[j.id] = j.vivant;
    });
}

// ── Sélection de cible ────────────────────────────────────
function selectionnerCible(id, div) {
    if (!etat) return;

    // 🏹 Mode Cupidon
    if (etat.phase === "nuit-cupidon" && monRole === "cupidon") {
        modeSelection = "cupidon";

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

    // ☠️ Mode poison (sorcière)
    if (modeSelection === "poison") {
        modeSelection = "normal";

        rendreSelectionnable(false);
        document
            .querySelectorAll(".carte-joueur")
            .forEach((c) => c.classList.remove("cible-vote"));

        div.classList.add("cible-vote");

        (async () => {
            try {
                await API.sorciere(false, id);
            } catch (e) {
                afficherNarrateur("Erreur réseau (empoisonnement)");
            } finally {
                div.classList.remove("cible-vote");
            }
        })();

        return;
    }

    // 🎯 Mode normal
    document
        .querySelectorAll(".carte-joueur")
        .forEach((c) => c.classList.remove("cible-vote"));

    cibleSelectionnee = id;
    div.classList.add("cible-vote");
}
function rendreSelectionnable(actif) {
    document
        .querySelectorAll(".carte-joueur:not(.moi):not(.mort)")
        .forEach((c) => {
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
    const msg = document.createElement("div");
    msg.className = "msg " + type;
    msg.innerHTML = `<div class="msg-auteur">${auteur}</div><div class="msg-texte">${texte}</div>`;
    zone.appendChild(msg);
    zone.scrollTop = zone.scrollHeight;
}

document.getElementById("chat-send").addEventListener("click", envoyerMessage);
document.getElementById("chat-input").addEventListener("keydown", (e) => {
    if (e.key === "Enter") envoyerMessage();
});

async function envoyerMessage() {
    const input = document.getElementById("chat-input");
    const texte = input.value.trim();
    if (!texte) return;

    input.value = "";

    if (etat && etat.monPseudo) {
        ajouterMessageChat(etat.monPseudo, texte, "moi");
    }

    try {
        await API.chat(texte);
    } catch (e) {
        console.error("Erreur chat :", e);
    }
}

// ── Phase ─────────────────────────────────────────────────
const MESSAGES_PHASE = {
    distribution: "Les rôles ont été distribués. Chacun découvre sa destinée…",
    "nuit-cupidon": "Cupidon se réveille et tend son arc vers deux âmes…",
    "nuit-voyante":
        "La Voyante ouvre les yeux dans la nuit et scrute les ombres…",
    "nuit-loups": "Les Loups-Garous se réveillent et choisissent leur proie…",
    "nuit-sorciere": "La Sorcière consulte ses potions à la lueur de la lune…",
    jour: "L'aube se lève sur le village. Les habitants découvrent les victimes de la nuit…",
    vote: "Le village se réunit. Il est temps de voter !",
    chasseur:
        "Le Chasseur rend son dernier souffle… mais il peut encore tirer !",
    fin: null,
};

function mettreAJourPhase(nouvelEtat) {
    if (!etat) return;
    const phase = nouvelEtat.phase;
    const tour = nouvelEtat.tour ?? 1;

    const estNuit = phase.startsWith("nuit") || phase === "distribution";
    document.body.className = estNuit ? "nuit" : "jour";
    document.getElementById("phase-icon").textContent = estNuit ? "🌙" : "☀️";

    let label = estNuit ? `Nuit ${tour}` : `Jour ${tour}`;
    if (phase === "vote") label = `Vote — Jour ${tour}`;
    if (phase === "fin") label = "Fin de partie";
    document.getElementById("phase-label").textContent = label;

    if (phase !== _lastNarratedPhase) {
        const msg = MESSAGES_PHASE[phase];
        if (msg) {
            afficherNarrateur(msg);
        }
        _lastNarratedPhase = phase;
    }

    rendreActionsZone(nouvelEtat);
    rendrePanneauRole(nouvelEtat);

    const selectionActive =
        phase === "vote" ||
        (phase === "nuit-loups" && monRole === "loup-garou") ||
        (phase === "nuit-voyante" && monRole === "voyante") ||
        (phase === "nuit-cupidon" && monRole === "cupidon") ||
        (phase === "chasseur" && monRole === "chasseur") ||
        (phase === "nuit-sorciere" && monRole === "sorciere");
    rendreSelectionnable(selectionActive);
}

// ── Actions zone ──────────────────────────────────────────
function rendreActionsZone(e) {
    const zone = document.getElementById("actions-zone");
    zone.innerHTML = "";

    if (e.phase === "distribution") {
        zone.appendChild(
            creerBouton("J'ai vu mon rôle ✓", async () => {
                await API.pret();
            }),
        );
    }

    if (e.phase === "nuit-cupidon" && monRole === "cupidon") {
        ciblesAmants = [];
        zone.appendChild(
            creerBouton("Lier les amants (0/2)", async () => {
                if (ciblesAmants.length !== 2) return;
                await API.cupidon(ciblesAmants[0], ciblesAmants[1]);
                ciblesAmants = [];
                document
                    .querySelectorAll(".carte-joueur")
                    .forEach((c) => c.classList.remove("cible-vote"));
            }),
        );
    }

    if (e.phase === "nuit-voyante" && monRole === "voyante") {
        zone.appendChild(
            creerBouton("Inspecter ce joueur", async () => {
                if (!cibleSelectionnee) return;
                try {
                    await API.voyante(cibleSelectionnee);
                } catch (e) {
                    afficherNarrateur("Erreur voyante");
                }
                cibleSelectionnee = null;
            }),
        );
    }

    if (e.phase === "nuit-loups" && monRole === "loup-garou") {
        zone.appendChild(
            creerBouton("Confirmer ma cible", async () => {
                if (!cibleSelectionnee) return;
                try {
                    await API.loupVote(cibleSelectionnee);
                } catch (e) {
                    afficherNarrateur("Erreur vote des loups");
                }
                cibleSelectionnee = null;
            }),
        );
    }

    if (e.phase === "jour" && e.estHote) {
        zone.appendChild(
            creerBouton("Lancer le vote", async () => {
                await API.demarrerVote();
            }),
        );
    }

    if (e.phase === "vote") {
        zone.appendChild(
            creerBouton("Confirmer mon vote", async () => {
                if (!cibleSelectionnee) return;
                try {
                    await API.vote(cibleSelectionnee);
                } catch (e) {
                    console.error(e);
                    afficherNarrateur("Erreur réseau pendant le vote");
                }
                cibleSelectionnee = null;
            }),
        );
    }

    if (e.phase === "chasseur" && monRole === "chasseur") {
        zone.appendChild(
            creerBouton(
                "Tirer sur ce joueur",
                async () => {
                    if (!cibleSelectionnee) return;
                    try {
                        await API.chasseurTire(cibleSelectionnee);
                    } catch (e) {
                        afficherNarrateur("Erreur tir");
                    }
                    cibleSelectionnee = null;
                },
                "danger",
            ),
        );
    }
}

// ── Panneau rôle spécial ──────────────────────────────────
function rendrePanneauRole(e) {
    const panneau = document.getElementById("panneau-role");

    if (e.resultatVoyante && monRole === "voyante") {
        if (e.resultatVoyante !== _lastVoyanteResult) {
            _lastVoyanteResult = e.resultatVoyante;
            afficherPanneau(
                "Révélation de la Voyante",
                `Ce joueur est : ${e.resultatVoyante}`,
                [
                    {
                        label: "Compris",
                        fn: () => panneau.classList.remove("visible"),
                    },
                ],
            );
        }
        return;
    } else {
        _lastVoyanteResult = null;
    }

    if (e.phase === "nuit-sorciere" && monRole === "sorciere") {
        if (e.tour !== _lastSorciereTour) {
            _lastSorciereTour = e.tour;
            const btns = [];
            if (e.potionVie && e.victime) {
                btns.push({
                    label: `💚 Sauver ${e.victime}`,
                    fn: async () => {
                        try {
                            await API.sorciere(true, null);
                        } catch (e) {
                            afficherNarrateur("Erreur potion de vie");
                        }
                        panneau.classList.remove("visible");
                    },
                });
            }
            if (e.potionMort) {
                btns.push({
                    label: "☠️ Empoisonner…",
                    fn: () => {
                        panneau.classList.remove("visible");
                        modeSelection = "poison";
                        rendreSelectionnable(true);
                    },
                });
            }
            btns.push({
                label: "Passer",
                fn: async () => {
                    await API.sorciere(false, null);
                    panneau.classList.remove("visible");
                },
            });
            const desc = e.victime
                ? `La victime de cette nuit est : ${e.victime}`
                : "Personne n'a été attaqué cette nuit.";
            afficherPanneau("Vos potions, Sorcière", desc, btns);
        }
        return;
    } else {
        _lastSorciereTour = -1;
    }

    if (e.phase === "nuit-cupidon" && monRole === "cupidon") {
        panneau.classList.remove("visible");
        return;
    }

    if (e.phase === "fin") {
        const camp = {
            villageois: "Les Villageois",
            loups: "Les Loups-Garous",
            amants: "Les Amants",
            annule: "Partie annulée par l'hôte",
        };
        afficherPanneau(
            e.vainqueur === "annule" ? "Partie annulée" : "Partie terminée",
            camp[e.vainqueur] ?? e.vainqueur,
            [
                {
                    label: "Retour au lobby",
                    fn: () => {
                        API.clearCode();
                        API.arreterPolling();
                        location.href = "lobby.html";
                    },
                },
            ],
        );
        return;
    }

    panneau.classList.remove("visible");
}

function afficherPanneau(titre, desc, btns) {
    document.getElementById("panneau-titre").textContent = titre;
    document.getElementById("panneau-desc").textContent = desc;
    const zone = document.getElementById("panneau-btns");
    zone.innerHTML = "";
    btns.forEach((b) =>
        zone.appendChild(creerBouton(b.label, b.fn, b.danger ? "danger" : "")),
    );
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
        API.clearCode();
        API.arreterPolling();
        location.href = "lobby.html";
    } catch (e) {
        console.error("Erreur quitter :", e);
        API.clearCode();
        location.href = "lobby.html";
    }
});

// ── Polling ───────────────────────────────────────────────
API.on.phaseChange = (nouvelEtat) => {
    etat = nouvelEtat;
    monRole = nouvelEtat.monRole;

    if (nouvelEtat.messages?.length) {
        API.setDernierTs(nouvelEtat.messages.at(-1).ts);
        nouvelEtat.messages.forEach((m) => {
            if (m.auteur === etat.monPseudo) {
                const lastMsg = document.querySelector(
                    "#chat-messages > .msg:last-child",
                );
                if (lastMsg && lastMsg.classList.contains("moi")) {
                    const lastMsgText =
                        lastMsg.querySelector(".msg-texte").textContent;
                    if (lastMsgText === m.texte) {
                        return; // Don't add duplicate of optimistic message
                    }
                }
            }
            const type = m.auteur === etat.monPseudo ? "moi" : "";
            ajouterMessageChat(m.auteur, m.texte, type);
        });
    }

    document.getElementById("mon-role-badge").textContent = monRole
        ? `Rôle : ${monRole}`
        : "";

    detecterMorts(nouvelEtat.joueurs);
    rendreCartes(nouvelEtat.joueurs);
    mettreAJourPhase(nouvelEtat);
};

API.on.fin = (nouvelEtat) => {
    etat = nouvelEtat;
    detecterMorts(nouvelEtat.joueurs);
    rendreCartes(nouvelEtat.joueurs);
    mettreAJourPhase(nouvelEtat);
};

API.demarrerPolling(500);

window.addEventListener("resize", () => {
    if (etat) rendreCartes(etat.joueurs);
});
