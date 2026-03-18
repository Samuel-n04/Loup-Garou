function flip(carte) {
    if (carte.dataset.animating === "true") return;
    carte.dataset.animating = "true";

    const inner = carte.querySelector(".carte-inner");
    const estFlipped = carte.classList.contains("flipped");
    const arrivee = estFlipped ? 0 : 180;

    inner.style.transition = "transform 0.6s cubic-bezier(0.15, 0.85, 0.4, 1)";
    inner.style.transform = `rotateY(${arrivee + 720}deg)`;

    inner.addEventListener("transitionend", function handler() {
        inner.removeEventListener("transitionend", handler);
        inner.style.transition = "none";
        inner.style.transform = `rotateY(${arrivee}deg)`;
        estFlipped
            ? carte.classList.remove("flipped")
            : carte.classList.add("flipped");
        carte.dataset.animating = "false";
    });
}
