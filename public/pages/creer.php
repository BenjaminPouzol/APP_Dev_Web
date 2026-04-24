<main class="container" style="padding:40px 0; max-width:700px; margin:auto;">
    <h1 style="margin-bottom:8px; color:var(--navy);">Créer une activité</h1>
    <p style="color:var(--gray-500); margin-bottom:32px;">Remplis le formulaire pour proposer ton activité à la communauté.</p>

    <?php if (!empty($error)): ?>
        <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">
        <form method="POST" action="/sharetime/public/?page=creer" style="display:flex; flex-direction:column; gap:20px;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Titre *</label>
                <input type="text" name="title" required placeholder="Ex : Randonnée en forêt"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                       style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
            </div>

            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Description *</label>
                <textarea name="description" required rows="4" placeholder="Décris ton activité, le programme, ce qu'il faut prévoir..."
                          style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; resize:vertical; box-sizing:border-box;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Lieu *</label>
                    <input type="text" name="location" required placeholder="Ex : Forêt de Fontainebleau"
                           value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Ville *</label>
                    <input type="text" name="city" required placeholder="Ex : Paris"
                           value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Date et heure de début *</label>
                    <input type="datetime-local" name="start_time" required
                           value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Date et heure de fin *</label>
                    <input type="datetime-local" name="end_time" required
                           value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Participants max *</label>
                    <input type="number" name="max_participants" required min="2" placeholder="Ex : 10"
                           value="<?= htmlspecialchars($_POST['max_participants'] ?? '') ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Visibilité *</label>
                    <select name="visibility"
                            style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box; background:white;">
                        <option value="publique" <?= ($_POST['visibility'] ?? '') === 'publique' ? 'selected' : '' ?>>Publique</option>
                        <option value="privee"   <?= ($_POST['visibility'] ?? '') === 'privee'   ? 'selected' : '' ?>>Privée</option>
                    </select>
                </div>
            </div>

            <div style="display:flex; gap:12px; margin-top:8px;">
                <button type="submit" class="btn btn-orange btn-lg">Créer l'activité</button>
                <a href="/sharetime/public/?page=activites" class="btn btn-outline-navy btn-lg">Annuler</a>
            </div>
        </form>
    </div>
</main>
