// ── Déconnexion ───────────────────────────────────────────
document
    .getElementById("btnDeconnexion")
    .addEventListener("click", async () => {
        await fetch("php/logout.php");
        location.href = "login.html";
    });

// ── Créer une partie → aller au lobby ─────────────────────
document.getElementById("btnCreer").addEventListener("click", () => {
    location.href = "lobby.html";
});

// ── Rejoindre avec un code (à implémenter plus tard) ──────
document.getElementById("btnRejoindre").addEventListener("click", () => {
    const code = document.getElementById("codeInput").value.trim();
    if (!code) return;
    // TODO: parties privées avec code
    alert("Parties privées pas encore disponibles.");
});

