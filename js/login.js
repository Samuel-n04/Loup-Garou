document.getElementById('btnConnexion')
    .addEventListener('click', async (e) => {
        e.preventDefault();

        const mail = document.getElementById('mail').value.trim();
        const mdp = document.getElementById('motDePasse').value;

        if (!mail || !mdp) {
            document.getElementById('erreur').textContent = 'Remplis tous les champs.';
            return;
        }

        const res = await fetch('../php/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mail, mdp })
        });
        const data = await res.json();

        if (data.status === 'ok') window.location.href = 'index.html';
        else document.getElementById('erreur').textContent = data.error;
    });