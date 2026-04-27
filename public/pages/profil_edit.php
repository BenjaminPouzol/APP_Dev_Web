<main class="container" style="padding:40px 0; max-width:600px; margin:auto;">

    <a href="/sharetime/public/?page=profil"
       style="color:var(--gray-500); font-size:0.9rem; display:inline-flex; align-items:center; gap:6px; margin-bottom:24px; text-decoration:none;">
        ← Retour au profil
    </a>

    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">
        <h1 style="color:var(--navy); margin-bottom:24px; font-size:1.5rem;">Modifier le profil</h1>

        <?php if (!empty($error)): ?>
            <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/sharetime/public/?page=profil_edit"
              enctype="multipart/form-data"
              style="display:flex; flex-direction:column; gap:20px;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- Photo de profil -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Photo de profil
                    <span style="font-size:0.78rem; font-weight:400; color:var(--gray-400);">(JPG, PNG, WebP · max 2 Mo)</span>
                </label>
                <?php if (!empty($profile['photo_profil'])): ?>
                <div style="margin-bottom:10px; display:flex; align-items:center; gap:12px;">
                    <img src="/sharetime/public/uploads/profils/<?= htmlspecialchars($profile['photo_profil']) ?>"
                         style="width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid var(--gray-200);">
                    <span style="font-size:0.82rem; color:var(--gray-500);">Photo actuelle — importer une nouvelle pour la remplacer</span>
                </div>
                <?php endif; ?>
                <input type="file" name="photo_profil" accept="image/jpeg,image/png,image/gif,image/webp"
                       style="font-family:inherit; font-size:0.9rem; color:var(--gray-700);">
            </div>

            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Pseudo *
                </label>
                <input type="text" name="pseudo" required
                       value="<?= htmlspecialchars($_POST['pseudo'] ?? $profile['pseudo'] ?? '') ?>"
                       placeholder="Votre pseudo"
                       style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
            </div>

            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Ville
                </label>
                <input type="text" name="ville"
                       value="<?= htmlspecialchars($_POST['ville'] ?? $profile['ville'] ?? '') ?>"
                       placeholder="Ex : Paris"
                       style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
            </div>

            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Bio
                </label>
                <textarea name="bio" rows="4" placeholder="Décris-toi en quelques mots..."
                          style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; resize:vertical; box-sizing:border-box;"><?= htmlspecialchars($_POST['bio'] ?? $profile['bio'] ?? '') ?></textarea>
            </div>

            <div style="display:flex; gap:12px;">
                <button type="submit" class="btn btn-orange btn-lg">Enregistrer</button>
                <a href="/sharetime/public/?page=profil" class="btn btn-outline-navy btn-lg">Annuler</a>
            </div>
        </form>
    </div>
</main>
