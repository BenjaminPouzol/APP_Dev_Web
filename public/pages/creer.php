<?php if (!isset($_SESSION['user'])): ?>
    <main class="container" style="padding:40px 0; text-align:center;">
        <p>Vous devez être connecté pour créer une activité.</p>
        <a href="/sharetime/public/?page=connexion" class="btn btn-orange">Se connecter</a>
    </main>
<?php else: ?>

<main class="container" style="padding:40px 0; max-width:700px; margin:auto;">
    <h1 style="margin-bottom:24px; color:#1E3A6E;">Créer une activité</h1>

    <?php if (!empty($error)): ?>
        <p style="color:red; font-weight:bold;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="/sharetime/public/?page=creer" style="display:flex; flex-direction:column; gap:16px;">

        <div>
            <label style="font-weight:600;">Titre *</label>
            <input type="text" name="title" required placeholder="Ex : Randonnée en forêt"
                style="width:100%; padding:12px; border:1px solid #D1D5DB; border-radius:8px; margin-top:6px;">
        </div>

        <div>
            <label style="font-weight:600;">Description *</label>
            <textarea name="description" required rows="4" placeholder="Décris ton activité..."
                style="width:100%; padding:12px; border:1px solid #D1D5DB; border-radius:8px; margin-top:6px;"></textarea>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
                <label style="font-weight:600;">Lieu *</label>
                <input type="text" name="location" required placeholder="Ex : Forêt de Fontainebleau"
                    style="width:100%; padding:12px; border:1px solid #D1D5DB; border-radius:8px; margin-top:6px;">
            </div>
            <div>
                <label style="font-weight:600;">Ville *</label>
                <input type="text" name="city" required placeholder="Ex : Paris"
                    style="width:100%; padding:12px; border:1px solid #D1D5DB; border-radius:8px; margin-top:6px;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
                <label style="font-weight:600;">Date et heure de début *</label>
                <input type="datetime-local" name="start_time" required
                    style="width:100%; padding:12px; border:1px solid #D1D5DB; border-radius:8px; margin-top:6px;">
            </div>
            <div>
                <label style="font-weight:600;">Date et heure de fin *</label>
                <input type="datetime-local" name="end_time" required
                    style="width:100%; padding:12px; border:1px solid #D1D5DB; border-radius:8px; margin-top:6px;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
                <label style="font-weight:600;">Nombre de participants max *</label>
                <input type="number" name="max_participants" required min="2" placeholder="Ex : 10"
                    style="width:100%; padding:12px; border:1px solid #D1D5DB; border-radius:8px; margin-top:6px;">
            </div>
            <div>
                <label style="font-weight:600;">Visibilité *</label>
                <select name="visibility" required
                    style="width:100%; padding:12px; border:1px solid #D1D5DB; border-radius:8px; margin-top:6px;">
                    <option value="public">Publique</option>
                    <option value="private">Privée</option>
                </select>
            </div>
        </div>

        <button type="submit" style="
            padding:14px;
            background:#FF7A00;
            color:white;
            border:none;
            border-radius:10px;
            font-size:1rem;
            font-weight:700;
            cursor:pointer;
            margin-top:8px;
        ">Créer l'activité</button>

    </form>
</main>

<?php endif; ?>