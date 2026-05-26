<?php
/**
 * public/pages/contact.php — Formulaire de contact
 *
 * Variables disponibles (préparées par index.php / handler user.php) :
 *   $success : message de confirmation après envoi réussi (masque le formulaire)
 *   $error   : message d'erreur de validation (affiché dans le formulaire)
 *
 * Si un utilisateur est connecté, nom et email sont préremplis depuis $_SESSION['user'].
 * Le handler POST est dans app/handlers/user.php (page = 'contact').
 * Le message est stocké en base (contact_messages) et un email est envoyé à l'admin.
 */
?>
<!-- Conteneur principal centré avec une largeur maximale de 620px -->
<main class="container" style="padding:48px 0; max-width:620px; margin:auto;">

    <!-- Bloc d'en-tête : label "Support", titre principal et sous-titre descriptif -->
    <div style="text-align:center; margin-bottom:40px;">
        <p style="font-size:0.78rem; font-weight:600; color:var(--orange); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">
            Support
        </p>
        <h1 style="color:var(--navy); margin-bottom:10px;">Contactez-nous</h1>
        <p style="color:var(--gray-500);">Une question, un problème ou une suggestion ? Écrivez-nous.</p>
    </div>

    <?php if (!empty($success)): // Affiche le bandeau de succès à la place du formulaire si l'envoi a réussi ?>
        <!-- Bandeau de confirmation vert affiché après un envoi valide -->
        <div style="background:#D1FAE5; color:#065F46; padding:20px 24px; border-radius:12px; text-align:center; font-weight:600; font-size:1.05rem;">
            ✅ <?= htmlspecialchars($success) // Affiche le message de succès en protégeant contre les injections XSS ?>
        </div>
    <?php else: // Sinon, affiche le formulaire de contact ?>

    <!-- Carte blanche contenant le formulaire de contact -->
    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">

        <?php if (!empty($error)): // Affiche l'erreur si la validation a échoué ?>
            <!-- Bandeau d'erreur rouge affiché en haut du formulaire -->
            <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500;">
                <?= htmlspecialchars($error) // Affiche le message d'erreur en protégeant contre les injections XSS ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire de contact soumis en POST vers la même page -->
        <form method="post" action="/sharetime/public/?page=contact"
              style="display:flex; flex-direction:column; gap:20px;">
            <!-- Jeton CSRF caché pour sécuriser le formulaire contre les attaques Cross-Site Request Forgery -->
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- Ligne à deux colonnes : champ Nom à gauche, champ Email à droite -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Nom *</label>
                    <!-- Prérempli avec la valeur POST soumise, ou avec prénom+nom de session si connecté -->
                    <input type="text" name="name" required
                           value="<?= htmlspecialchars($_POST['name'] ?? (isset($_SESSION['user']) ? $_SESSION['user']['prenom'] . ' ' . $_SESSION['user']['nom'] : '')) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Email *</label>
                    <!-- Prérempli avec la valeur POST soumise, ou avec l'email de session si connecté -->
                    <input type="email" name="email" required
                           value="<?= htmlspecialchars($_POST['email'] ?? (isset($_SESSION['user']) ? $_SESSION['user']['email'] : '')) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <!-- Champ sujet facultatif avec un exemple de valeur dans le placeholder -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Sujet</label>
                <!-- Conserve la valeur saisie après soumission en cas de rechargement avec erreur -->
                <input type="text" name="subject"
                       value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                       placeholder="Ex : Problème de connexion"
                       style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
            </div>

            <!-- Champ message obligatoire avec 5 lignes visibles et redimensionnement vertical -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Message *</label>
                <!-- Conserve le texte saisi en cas d'erreur de validation -->
                <textarea name="message" required rows="5" placeholder="Décrivez votre demande..."
                          style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; resize:vertical; box-sizing:border-box;"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>

            <!-- Bouton d'envoi du formulaire avec le style primaire orange -->
            <button type="submit" class="btn btn-orange btn-lg">Envoyer le message</button>
        </form>
    </div>

    <?php endif; // Fin de la condition succès / formulaire ?>
</main>

<style>
/* Sur mobile (< 600px), les colonnes Nom/Email passent en une seule colonne */
@media (max-width: 600px) {
    form > div:first-of-type { grid-template-columns: 1fr !important; }
}
</style>
