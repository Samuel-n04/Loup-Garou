/* ════════════════════════════════════════
   MODALS — ouvrir / fermer
════════════════════════════════════════ */

function openModal(id) {
  document.getElementById(`modal-${id}`).classList.add('active');
}

function closeModal(id) {
  document.getElementById(`modal-${id}`).classList.remove('active');
}

// Boutons d'ouverture
document.getElementById('btn-open-create').addEventListener('click', () => openModal('create'));
document.getElementById('btn-open-join').addEventListener('click',   () => openModal('join'));

// Boutons de fermeture
document.getElementById('btn-close-create').addEventListener('click',  () => closeModal('create'));
document.getElementById('btn-cancel-create').addEventListener('click', () => closeModal('create'));
document.getElementById('btn-close-join').addEventListener('click',    () => closeModal('join'));
document.getElementById('btn-cancel-join').addEventListener('click',   () => closeModal('join'));

// Clic en dehors du modal
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('active');
  });
});

/* ════════════════════════════════════════
   TOGGLE PUBLIC / PRIVÉ
════════════════════════════════════════ */
document.getElementById('visibility-toggle')
  .querySelectorAll('.toggle-btn')
  .forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#visibility-toggle .toggle-btn')
        .forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });

/* ════════════════════════════════════════
   TOGGLE RÔLES SPÉCIAUX
════════════════════════════════════════ */
document.getElementById('roles-toggle')
  .querySelectorAll('.role-toggle')
  .forEach(btn => {
    btn.addEventListener('click', () => btn.classList.toggle('active'));
  });

/* ════════════════════════════════════════
   CRÉER UNE PARTIE
════════════════════════════════════════ */
document.getElementById('btn-create').addEventListener('click', async () => {
  const name       = document.getElementById('session-name').value.trim();
  const maxPlayers = document.getElementById('max-players').value;
  const visibility = document.querySelector('#visibility-toggle .toggle-btn.active').textContent;
  const roles      = [...document.querySelectorAll('#roles-toggle .role-toggle.active')]
                       .map(b => b.textContent.trim());

  if (!name) {
    alert('Donne un nom à ta session !');
    return;
  }

  // TODO : remplacer par fetch('php/create_game.php', ...) quand le PHP sera prêt
  console.log('Créer partie :', { name, maxPlayers, visibility, roles });
  // window.location.href = `lobby.html?game_id=...`;
});

/* ════════════════════════════════════════
   REJOINDRE UNE PARTIE
════════════════════════════════════════ */
document.getElementById('btn-join').addEventListener('click', async () => {
  const code = document.getElementById('join-code').value.trim().toUpperCase();

  if (!code) {
    alert('Entre un code de session !');
    return;
  }

  // TODO : remplacer par fetch('php/join_game.php', ...) quand le PHP sera prêt
  console.log('Rejoindre partie :', code);
  // window.location.href = `lobby.html?game_id=${code}`;
});
