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
<!-- Balise main avec largeur max de 600px centrée horizontalement et paddingh vertical -->
<main class="container" style="padding:40px 0; max-width:600px; margin:auto;">

    <!-- Lien de retour au profil sans soumission -->
    <!-- Lien de navigation permettant de revenir à la page profil sans soumettre de données -->
    <a href="/sharetime/public/?page=profil"
       style="color:var(--gray-500); font-size:0.9rem; display:inline-flex; align-items:center; gap:6px; margin-bottom:24px; text-decoration:none;">
        ← Retour au profil
    </a>

    <!-- Carte blanche avec bordure et coins arrondis contenant tout le formulaire d'édition -->
    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">
        <!-- Titre de la section affiché en haut de la carte d'édition -->
        <h1 style="color:var(--navy); margin-bottom:24px; font-size:1.5rem;">Modifier le profil</h1>

        <!-- Erreur de validation (pseudo vide, fichier trop grand, mauvais format…) -->
        <!-- Vérifie si une variable $error non vide a été transmise par le handler -->
        <?php if (!empty($error)): ?>
            <!-- Affiche le message d'erreur dans un bloc rouge en échappant les caractères HTML -->
            <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- enctype multipart/form-data obligatoire pour l'upload de photo de profil -->
        <!-- Formulaire en POST avec encodage multipart indispensable pour transmettre les fichiers -->
        <form method="post" action="/sharetime/public/?page=profil_edit"
              enctype="multipart/form-data"
              style="display:flex; flex-direction:column; gap:20px;">
            <!-- Champ caché contenant le jeton CSRF pour sécuriser la soumission du formulaire -->
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- ── Photo de profil ──────────────────────────────────────────
                 Affiche la photo actuelle si elle existe, avec une indication
                 que l'import d'une nouvelle photo la remplace. -->
            <!-- Groupe de champ pour l'upload de la photo de profil -->
            <div>
                <!-- Label de la section photo avec indication des formats et taille max acceptés -->
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Photo de profil
                    <!-- Indication entre parenthèses des formats acceptés et de la taille maximale -->
                    <span style="font-size:0.78rem; font-weight:400; color:var(--gray-400);">(JPG, PNG, WebP · max 2 Mo)</span>
                </label>
                <!-- Affiche la photo actuelle uniquement si le champ photo_profil n'est pas vide -->
                <?php if (!empty($profile['photo_profil'])): ?>
                <!-- Miniature circulaire de la photo actuelle -->
                <!-- Conteneur flex pour aligner l'image et le texte explicatif sur la même ligne -->
                <div style="margin-bottom:10px; display:flex; align-items:center; gap:12px;">
                    <!-- Image de profil actuelle affichée en miniature ronde de 60px -->
                    <img src="/sharetime/public/uploads/profils/<?= htmlspecialchars($profile['photo_profil']) ?>"
                         style="width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid var(--gray-200);">
                    <!-- Texte expliquant que l'import d'une nouvelle photo remplacera l'actuelle -->
                    <span style="font-size:0.82rem; color:var(--gray-500);">Photo actuelle — importer une nouvelle pour la remplacer</span>
                </div>
                <?php endif; ?>
                <!-- Input file : accept= filtre navigateur, validation réelle côté serveur via upload_image() -->
                <!-- Champ de sélection de fichier filtrant les images dans le sélecteur du navigateur -->
                <input type="file" name="photo_profil" accept="image/jpeg,image/png,image/gif,image/webp"
                       style="font-family:inherit; font-size:0.9rem; color:var(--gray-700);">
            </div>

            <!-- ── Pseudo (obligatoire) ─────────────────────────────────────
                 Pré-rempli depuis $_POST (en cas d'erreur) ou depuis $profile. -->
            <!-- Groupe de champ pour le pseudo affiché publiquement sur la plateforme -->
            <div>
                <!-- Label obligatoire signalé par l'astérisque pour le champ pseudo -->
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Pseudo *
                </label>
                <!-- Champ texte obligatoire prérempli depuis $_POST si erreur, sinon depuis $profile -->
                <input type="text" name="pseudo" required
                       value="<?= htmlspecialchars($_POST['pseudo'] ?? $profile['pseudo'] ?? '') ?>"
                       placeholder="Votre pseudo"
                       style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
            </div>

            <!-- ── Ville (optionnelle) ──────────────────────────────────── -->
            <!-- Groupe de champ pour la ville de l'utilisateur, champ facultatif -->
            <div>
                <!-- Label sans astérisque car la ville n'est pas obligatoire -->
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Ville
                </label>
                <!-- Champ texte optionnel prérempli depuis $_POST si erreur, sinon depuis $profile -->
                <input type="text" name="ville"
                       value="<?= htmlspecialchars($_POST['ville'] ?? $profile['ville'] ?? '') ?>"
                       placeholder="Ex : Paris"
                       style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
            </div>

            <!-- ── Bio (optionnelle) ───────────────────────────────────────
                 Textarea : nl2br dans la page profil — pas de formatage HTML autorisé. -->
            <!-- Groupe de champ pour la biographie libre de l'utilisateur -->
            <div>
                <!-- Label sans astérisque car la bio est facultative -->
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Bio
                </label>
                <!-- Zone de texte multiligne redimensionnable verticalement, préremplie depuis $_POST ou $profile -->
                <textarea name="bio" rows="4" placeholder="Décris-toi en quelques mots..."
                          style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; resize:vertical; box-sizing:border-box;"><?= htmlspecialchars($_POST['bio'] ?? $profile['bio'] ?? '') ?></textarea>
            </div>

            <!-- ── Boutons d'action ─────────────────────────────────────── -->
            <!-- Ligne contenant les deux boutons d'action espacés de 12px -->
            <div style="display:flex; gap:12px;">
                <!-- Bouton de soumission principal avec style orange pour l'action principale -->
                <button type="submit" class="btn btn-orange btn-lg">Enregistrer</button>
                <!-- Annuler : retourne au profil sans sauvegarder -->
                <!-- Lien stylisé en bouton qui ramène au profil sans enregistrer les modifications -->
                <a href="/sharetime/public/?page=profil" class="btn btn-outline-navy btn-lg">Annuler</a>
            </div>
        </form>
    </div>
</main>
