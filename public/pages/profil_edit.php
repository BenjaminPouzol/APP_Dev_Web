<?php
/**
 * public/pages/profil_edit.php — Formulaire d'édition du profil
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $profile : données actuelles de l'utilisateur connecté (chargées par User::getById)
 *   $error   : message d'erreur (optionnel — vient de handlers/user.php)
 *
 * La logique POST (validation pseudo, upload photo, suppression ancienne photo,
 * mise à jour BDD et session) est dans app/handlers/user.php (bloc $page === 'profil_edit').
 *
 * Seuls pseudo, ville, bio et photo_profil sont modifiables ici.
 * L'email, le nom et le prénom ne sont pas éditables par l'utilisateur lui-même.
 */
?>
<!-- ── CONTENEUR PRINCIPAL ─────────────────────────────────────────────────── -->
<main class="container" style="padding:40px 0; max-width:600px; margin:auto;">

    <!-- Lien de retour au profil sans soumission -->
    <a href="/sharetime/public/?page=profil"
       style="color:var(--gray-500); font-size:0.9rem; display:inline-flex; align-items:center; gap:6px; margin-bottom:24px; text-decoration:none;">
        ← Retour au profil
    </a>

    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">
        <h1 style="color:var(--navy); margin-bottom:24px; font-size:1.5rem;">Modifier le profil</h1>

        <!-- Erreur de validation (pseudo vide, fichier trop grand, mauvais format…) -->
        <?php if (!empty($error)): ?>
            <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- enctype multipart/form-data obligatoire pour l'upload de photo de profil -->
        <form method="post" action="/sharetime/public/?page=profil_edit"
              enctype="multipart/form-data"
              style="display:flex; flex-direction:column; gap:20px;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- ── Photo de profil ──────────────────────────────────────────
                 Affiche la photo actuelle si elle existe, avec une indication
                 que l'import d'une nouvelle photo la remplace. -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Photo de profil
                    <span style="font-size:0.78rem; font-weight:400; color:var(--gray-400);">(JPG, PNG, WebP · max 2 Mo)</span>
                </label>
                <?php if (!empty($profile['photo_profil'])): ?>
                <!-- Miniature circulaire de la photo actuelle -->
                <div style="margin-bottom:10px; display:flex; align-items:center; gap:12px;">
                    <img src="/sharetime/public/uploads/profils/<?= htmlspecialchars($profile['photo_profil']) ?>"
                         style="width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid var(--gray-200);">
                    <span style="font-size:0.82rem; color:var(--gray-500);">Photo actuelle — importer une nouvelle pour la remplacer</span>
                </div>
                <?php endif; ?>
                <!-- Input file : accept= filtre navigateur, validation réelle côté serveur via upload_image() -->
                <input type="file" name="photo_profil" accept="image/jpeg,image/png,image/gif,image/webp"
                       style="font-family:inherit; font-size:0.9rem; color:var(--gray-700);">
            </div>

            <!-- ── Pseudo (obligatoire) ─────────────────────────────────────
                 Pré-rempli depuis $_POST (en cas d'erreur) ou depuis $profile. -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Pseudo *
                </label>
                <input type="text" name="pseudo" required
                       value="<?= htmlspecialchars($_POST['pseudo'] ?? $profile['pseudo'] ?? '') ?>"
                       placeholder="Votre pseudo"
                       style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
            </div>

            <!-- ── Ville (optionnelle) ──────────────────────────────────── -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Ville
                </label>
                <input type="text" name="ville"
                       value="<?= htmlspecialchars($_POST['ville'] ?? $profile['ville'] ?? '') ?>"
                       placeholder="Ex : Paris"
                       style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
            </div>

            <!-- ── Bio (optionnelle) ───────────────────────────────────────
                 Textarea : nl2br dans la page profil — pas de formatage HTML autorisé. -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Bio
                </label>
                <textarea name="bio" rows="4" placeholder="Décris-toi en quelques mots..."
                          style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; resize:vertical; box-sizing:border-box;"><?= htmlspecialchars($_POST['bio'] ?? $profile['bio'] ?? '') ?></textarea>
            </div>

            <!-- ── Boutons d'action ─────────────────────────────────────── -->
            <div style="display:flex; gap:12px;">
                <button type="submit" class="btn btn-orange btn-lg">Enregistrer</button>
                <!-- Annuler : retourne au profil sans sauvegarder -->
                <a href="/sharetime/public/?page=profil" class="btn btn-outline-navy btn-lg">Annuler</a>
            </div>
        </form>
    </div>
</main>
