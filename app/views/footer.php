<?php
/**
 * app/views/footer.php — Pied de page commun à toutes les pages
 *
 * Inclus en dernier par public/index.php, après le fichier de page.
 * Contient :
 *   - Footer avec liens de navigation (activités, compte, légal)
 *     Les liens "Compte" s'adaptent selon l'état de connexion ($_SESSION['user'])
 *   - Toast pour les flash messages (si $flash ou $flash_html est défini)
 *   - Scripts JS globaux :
 *       · Auto-dismiss du toast après 4,5 secondes
 *       · Désactivation du bouton submit après soumission (anti double-clic)
 *       · Validation dates (fin > début) pour les formulaires d'activité
 *       · Validation mots de passe (correspondance) pour inscription/reset
 */
?>
<style>
@media (max-width: 640px) {
    /* Sur mobile, la grille 4 colonnes passe en 2 colonnes ; la colonne brand occupe toute la largeur */
    .footer-grid { grid-template-columns: 1fr 1fr !important; } /* 2 colonnes égales sur mobile */
    .footer-brand { grid-column: 1 / -1; } /* la marque s'étend sur toutes les colonnes */
}
</style>
<footer style="background:#1E3A6E; color:white; padding:40px 0 20px;"> <!-- fond bleu marine, texte blanc -->
    <div class="container"> <!-- centrage horizontal du contenu sur le même gabarit que la navbar -->
        <!-- Grille à 4 colonnes : marque (large), Activités, Compte, Légal -->
        <div class="footer-grid" style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:36px; margin-bottom:32px;"> <!-- grille 4 colonnes : marque large (2fr) + 3 colonnes de liens (1fr chacune) -->

            <!-- Colonne 1 : identité de la marque + tagline -->
            <div class="footer-brand">
                <!-- Nom ShareTime avec les deux couleurs de marque : orange / blanc -->
                <div style="font-family:'Poppins',sans-serif; font-size:1.3rem; font-weight:800; margin-bottom:12px;">
                    <span style="color:#E8811A;">Share</span><span style="color:white;">Time</span>
                </div>
                <!-- Tagline courte, couleur atténuée pour ne pas concurrencer les titres -->
                <p style="font-size:0.83rem; color:rgba(255,255,255,0.45); line-height:1.6; max-width:260px;">
                    La plateforme de partage et d'organisation d'activités entre particuliers.
                </p>
            </div>

            <!-- Colonne 2 : liens vers les activités -->
            <div>
                <!-- Titre de colonne en petites majuscules pour hiérarchiser visuellement -->
                <h4 style="font-size:0.78rem; font-weight:600; color:rgba(255,255,255,0.65); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:14px;">Activités</h4>
                <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:8px;"> <!-- liste verticale sans puces avec espacement entre items -->
                    <!-- Lien vers le catalogue complet des activités -->
                    <li><a href="/sharetime/public/?page=activites" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Toutes les activités</a></li>
                    <!-- Lien vers le formulaire de création d'activité -->
                    <li><a href="/sharetime/public/?page=creer" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Créer une activité</a></li>
                </ul>
            </div>

            <!-- Colonne 3 : liens de compte — contenu adapté selon l'état de connexion -->
            <div>
                <h4 style="font-size:0.78rem; font-weight:600; color:rgba(255,255,255,0.65); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:14px;">Compte</h4>
                <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:8px;"> <!-- même style que la colonne Activités -->
                    <?php if (isset($_SESSION['user'])): ?> <!-- si l'utilisateur est connecté -->
                        <!-- Lien vers le profil personnel de l'utilisateur connecté -->
                        <li><a href="/sharetime/public/?page=profil" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Mon profil</a></li>
                        <!-- Lien de déconnexion (handler logout dans auth.php) -->
                        <li><a href="/sharetime/public/?page=logout" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Déconnexion</a></li>
                    <?php else: ?> <!-- si le visiteur n'est pas connecté -->
                        <!-- Lien vers le formulaire d'inscription -->
                        <li><a href="/sharetime/public/?page=inscription" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Inscription</a></li>
                        <!-- Lien vers le formulaire de connexion -->
                        <li><a href="/sharetime/public/?page=connexion" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Connexion</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Colonne 4 : liens légaux et d'aide -->
            <div>
                <h4 style="font-size:0.78rem; font-weight:600; color:rgba(255,255,255,0.65); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:14px;">Légal</h4>
                <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:8px;"> <!-- même style que les autres colonnes de liens -->
                    <!-- Lien vers la Foire Aux Questions -->
                    <li><a href="/sharetime/public/?page=faq" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">FAQ</a></li>
                    <!-- Lien vers le formulaire de contact -->
                    <li><a href="/sharetime/public/?page=contact" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Contact</a></li>
                    <!-- Lien vers les Conditions Générales d'Utilisation -->
                    <li><a href="/sharetime/public/?page=cgu" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">CGU</a></li>
                    <!-- Lien vers les mentions légales obligatoires (RGPD, éditeur, hébergeur) -->
                    <li><a href="/sharetime/public/?page=mentions" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Mentions légales</a></li>
                </ul>
            </div>
        </div>

        <!-- Barre de copyright : séparateur + année calculée dynamiquement -->
        <div style="border-top:1px solid rgba(255,255,255,0.08); padding-top:18px; font-size:0.78rem; color:rgba(255,255,255,0.25); text-align:center;"> <!-- séparateur fin + texte de copyright atténué (25% d'opacité) -->
            © <?= date('Y') ?> ShareTime — Tous droits réservés <!-- date('Y') retourne l'année courante (ex: 2025) -->
        </div>
    </div>
</footer>

<?php if (!empty($flash) || !empty($flash_html)): ?> <!-- affiche le toast uniquement si un message flash est présent -->
<!-- Toast flash : affiché seulement si un message a été défini en session par un handler POST -->
<div id="toast-container">
    <!-- Classe CSS dynamique selon le type : toast-success (vert), toast-error (rouge), toast-info (bleu) -->
    <div class="toast toast-<?= htmlspecialchars($flash_type ?? 'success') ?>" id="toast-msg"> <!-- classe CSS composée : toast + toast-success/error/info selon le type -->
        <!-- Icône adaptée au type : ✓ succès, ✕ erreur -->
        <div class="toast-icon"><?= ($flash_type ?? 'success') === 'error' ? '✕' : '✓' ?></div> <!-- le footer ne distingue pas 'info' : ✕ pour erreur, ✓ pour tout le reste -->
        <p class="toast-text">
            <?php if (!empty($flash_html)): ?> <!-- HTML brut autorisé uniquement pour les messages de dev (lien de vérification email) -->
                <?= $flash_html ?>
            <?php else: ?> <!-- texte brut : encodage htmlspecialchars obligatoire pour éviter le XSS -->
                <?= htmlspecialchars($flash) ?>
            <?php endif; ?>
        </p>
        <!-- Bouton de fermeture manuelle : retire le toast du DOM au clic -->
        <button class="toast-close" onclick="this.closest('.toast').remove()" aria-label="Fermer">×</button>
    </div>
</div>
<?php endif; ?>

<script>
(function() { // IIFE : isole toutes les variables pour éviter de polluer le scope global
    // ── Auto-dismiss du toast ──────────────────────────────────────────────────
    var toast = document.getElementById('toast-msg'); // récupère le toast par son ID
    if (toast) { // vérifie qu'un toast est bien présent sur la page
        setTimeout(function() {
            toast.style.animation = 'toast-out 0.3s ease forwards'; // déclenche l'animation de sortie CSS
            setTimeout(function() { if (toast.parentNode) toast.parentNode.remove(); }, 300); // supprime le conteneur après l'animation
        }, 4500); // disparition automatique après 4,5 secondes
    }

    // ── Anti double-clic : désactive le bouton submit après soumission ─────────
    document.addEventListener('submit', function(e) { // écoute toutes les soumissions de formulaire sur la page
        var btn = e.target.querySelector('[type="submit"]:not([data-no-loading])'); // cherche le bouton submit (sauf ceux marqués data-no-loading)
        if (btn) {
            btn.disabled = true;                          // empêche un deuxième clic pendant le traitement
            btn.dataset.originalText = btn.textContent;  // sauvegarde le texte original pour pouvoir le restaurer
            btn.textContent = 'Chargement…';             // affiche un indicateur visuel de chargement
        }
    });

    // ── Validation date de fin > date de début (formulaires d'activité) ────────
    var startInput = document.querySelector('[name="start_time"]'); // champ date/heure de début
    var endInput   = document.querySelector('[name="end_time"]');   // champ date/heure de fin
    if (startInput && endInput) { // n'exécute la validation que sur les pages ayant ces champs
        function validateDates() {
            if (startInput.value && endInput.value && endInput.value <= startInput.value) {
                // La date de fin est antérieure ou égale à la date de début : invalide
                endInput.setCustomValidity('La date de fin doit être postérieure à la date de début.');
            } else {
                endInput.setCustomValidity(''); // réinitialise l'erreur si les dates sont cohérentes
            }
        }
        startInput.addEventListener('change', validateDates); // revalide quand la date de début change
        endInput.addEventListener('change', validateDates);   // revalide quand la date de fin change
    }

    // ── Validation correspondance des mots de passe (inscription / reset) ──────
    var pass    = document.querySelector('[name="password"]');         // champ mot de passe principal
    var confirm = document.querySelector('[name="confirm-password"]'); // champ de confirmation
    if (pass && confirm) { // n'exécute la validation que sur les pages ayant ces deux champs
        function validatePasswords() {
            if (confirm.value && pass.value !== confirm.value) {
                // Les deux saisies ne correspondent pas : message d'erreur natif du navigateur
                confirm.setCustomValidity('Les mots de passe ne correspondent pas.');
            } else {
                confirm.setCustomValidity(''); // réinitialise l'erreur si les mots de passe correspondent
            }
        }
        pass.addEventListener('input', validatePasswords);    // revalide à chaque frappe dans le champ principal
        confirm.addEventListener('input', validatePasswords); // revalide à chaque frappe dans la confirmation
    }
})(); // IIFE : fonction auto-invoquée pour éviter de polluer le scope global
</script>
</body> <!-- fermeture de la balise body ouverte dans header.php -->
</html> <!-- fermeture de la balise html ouverte dans header.php -->
