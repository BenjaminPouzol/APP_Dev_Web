<?php
/**
 * public/pages/inscription.php — Page d'inscription
 *
 * Variables disponibles (préparées par handlers/auth.php) :
 *   $error : message d'erreur (email existant, pseudo pris, mots de passe non concordants…)
 *
 * La logique POST (validation, création compte, email de vérification) est dans
 * app/handlers/auth.php. Cette page n'affiche que le formulaire et les erreurs.
 *
 * Les champs sont pré-remplis depuis $_POST en cas d'erreur pour éviter à
 * l'utilisateur de tout ressaisir (sauf les mots de passe, volontairement vides).
 */
?>
<!-- ── CONTENEUR PRINCIPAL ────────────────────────────────────────────────────
     Même structure que connexion.php : min-height + flex pour centrage vertical. -->
<!-- Balise main centrée horizontalement et verticalement avec du padding autour de la card -->
<main style="display:flex; align-items:flex-start; justify-content:center; padding:40px 20px; box-sizing:border-box;">
    <!-- register-wrapper : grille 2 colonnes (gauche déco / droite formulaire) -->
    <!-- Conteneur global de la carte d'inscription en deux panneaux côte à côte -->
    <div class="register-wrapper">

        <!-- ── PANNEAU GAUCHE (décoratif) ────────────────────────────────────
             Gradient navy avec arguments de vente (3 bullets point) pour
             convaincre le visiteur de finaliser son inscription. -->
        <!-- Panneau gauche avec fond dégradé navy et arguments marketing -->
        <div class="register-left">
            <!-- Logo textuel inline -->
            <!-- Conteneur du logo avec police Poppins et typographie imposante -->
            <div style="font-family:'Poppins',sans-serif; font-size:1.5rem; font-weight:800; margin-bottom:20px;">
                <!-- Première partie du logo en orange via la variable CSS --orange -->
                <span style="color:var(--orange);">Share</span><span style="color:white;">Time</span>
            </div>
            <!-- Titre d'accroche invitant le visiteur à rejoindre la communauté -->
            <h1 style="font-size:2rem; color:white; margin-bottom:16px; line-height:1.2;">
                Rejoins la communauté
            </h1>
            <!-- Texte descriptif en blanc semi-transparent pour présenter la plateforme -->
            <p style="color:rgba(255,255,255,0.65); line-height:1.7;">
                Découvre des activités près de chez toi, partage tes passions et rencontre des gens qui ont les mêmes centres d'intérêt.
            </p>
            <!-- Liste des bénéfices clés : accroches marketing courtes -->
            <!-- Liste sans puce affichant les trois avantages principaux de la plateforme -->
            <ul style="list-style:none; padding:0; margin-top:24px; display:flex; flex-direction:column; gap:10px;">
                <!-- Bénéfice 1 : aide à trouver des activités personnalisées -->
                <li style="color:rgba(255,255,255,0.8); font-size:0.92rem;">✔ Trouve des activités adaptées à tes envies</li>
                <!-- Bénéfice 2 : possibilité de créer ses propres événements -->
                <li style="color:rgba(255,255,255,0.8); font-size:0.92rem;">✔ Crée tes propres événements en quelques clics</li>
                <!-- Bénéfice 3 : aspect communautaire et local de la plateforme -->
                <li style="color:rgba(255,255,255,0.8); font-size:0.92rem;">✔ Rejoins une communauté locale et conviviale</li>
            </ul>
        </div>

        <!-- ── PANNEAU DROIT (formulaire) ────────────────────────────────────
             Le formulaire est plus long que la connexion, d'où overflow-y:auto
             pour scroller sur les petits écrans sans agrandir la card. -->
        <!-- Panneau droit blanc contenant le formulaire d'inscription complet -->
        <div class="register-right">
            <!-- Titre principal du panneau formulaire -->
            <h2 style="color:var(--gray-900); margin-bottom:6px; font-size:1.6rem;">Créer un compte</h2>
            <!-- Sous-titre guidant l'utilisateur sur l'action attendue -->
            <p style="color:var(--gray-500); margin-bottom:24px; font-size:0.92rem;">
                Remplis les informations ci-dessous pour commencer.
            </p>

            <!-- Erreur de validation côté serveur (email pris, mdp trop court, etc.) -->
            <!-- Vérifie si une variable $error non vide a été définie par le handler -->
            <?php if (!empty($error)): ?>
                <!-- Affiche l'erreur en échappant les caractères HTML pour prévenir les injections XSS -->
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Formulaire d'inscription : POST vers handlers/auth.php (page=inscription) -->
            <!-- Formulaire soumis en POST vers l'URL d'inscription, organisé en colonne avec 16px d'espacement -->
            <form method="POST" action="/sharetime/public/?page=inscription" autocomplete="off" style="display:flex; flex-direction:column; gap:16px;">
                <!-- Token CSRF obligatoire sur tous les formulaires POST -->
                <!-- Champ caché avec le jeton CSRF pour protéger le formulaire contre les attaques cross-site -->
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <!-- Ligne 1 : prénom + nom (2 colonnes) -->
                <!-- Conteneur en deux colonnes pour les champs prénom et nom côte à côte -->
                <div class="form-row">
                    <!-- Groupe de champ pour le prénom -->
                    <div class="form-group">
                        <!-- Label obligatoire signalé par l'astérisque -->
                        <label>Prénom *</label>
                        <!-- value pré-rempli depuis $_POST pour conserver la saisie en cas d'erreur -->
                        <!-- Champ texte obligatoire pré-rempli depuis $_POST si erreur, valeur vide sinon -->
                        <input type="text" name="firstname" placeholder="Ton prénom" required
                               value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>">
                    </div>
                    <!-- Groupe de champ pour le nom de famille -->
                    <div class="form-group">
                        <!-- Label obligatoire signalé par l'astérisque -->
                        <label>Nom *</label>
                        <!-- Champ texte obligatoire pré-rempli depuis $_POST si erreur -->
                        <input type="text" name="lastname" placeholder="Ton nom" required
                               value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>">
                    </div>
                </div>

                <!-- Ligne 2 : pseudo + ville (2 colonnes) -->
                <!-- Conteneur en deux colonnes pour les champs pseudo et ville -->
                <div class="form-row">
                    <!-- Groupe de champ pour le pseudo affiché publiquement -->
                    <div class="form-group">
                        <!-- Label obligatoire : le pseudo est requis pour s'afficher sur la plateforme -->
                        <label>Pseudo *</label>
                        <!-- Champ texte obligatoire pour le pseudo, pré-rempli depuis $_POST si erreur -->
                        <input type="text" name="username" placeholder="Choisis un pseudo" required
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <!-- Ville optionnelle : non marquée * dans le label -->
                        <!-- Label sans astérisque car la ville n'est pas obligatoire à l'inscription -->
                        <label>Ville</label>
                        <!-- Champ texte optionnel pour la ville, pré-rempli depuis $_POST si erreur -->
                        <input type="text" name="city" placeholder="Ex : Paris"
                               value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                    </div>
                </div>

                <!-- Email : pleine largeur (1 colonne) -->
                <!-- Groupe de champ email sur toute la largeur du formulaire -->
                <div class="form-group">
                    <!-- Label obligatoire pour le champ e-mail -->
                    <label>Adresse e-mail *</label>
                    <!-- Champ de type email avec validation HTML5 et pré-remplissage depuis $_POST -->
                    <input type="email" name="email" placeholder="ton@email.com" required
                           autocomplete="off"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <!-- Ligne 3 : mot de passe + confirmation (2 colonnes). -->
                <!-- Conteneur en deux colonnes pour le mot de passe et sa confirmation -->
                <div class="form-row">
                    <!-- Groupe de champ pour la saisie du mot de passe -->
                    <div class="form-group">
                        <!-- Label obligatoire pour le mot de passe -->
                        <label>Mot de passe *</label>
                        <!-- Champ password qui masque les caractères, avec id pour le JS de validation -->
                        <input type="password" name="password" id="reg-password" placeholder="••••••••" required autocomplete="new-password">
                    </div>
                    <!-- Groupe de champ pour la confirmation du mot de passe -->
                    <div class="form-group">
                        <!-- Label obligatoire pour la confirmation du mot de passe -->
                        <label>Confirmer *</label>
                        <!-- Champ de confirmation du mot de passe avec id pour la comparaison JS -->
                        <input type="password" name="confirm-password" id="reg-confirm" placeholder="••••••••" required autocomplete="new-password">
                        <!-- Paragraphe caché par défaut affichant le résultat de la correspondance des mots de passe -->
                        <p id="match-msg" style="font-size:0.78rem;margin:4px 0 0;display:none;"></p>
                    </div>
                </div>
                <!-- Checklist de contraintes affichée dès la saisie -->
                <!-- Bloc de règles de sécurité du mot de passe, caché par défaut puis révélé par JS -->
                <div id="pwd-rules" style="display:none;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:10px;padding:12px 16px;display:none;gap:6px;flex-direction:column;">
                    <!-- Titre de la section règles avec style discret -->
                    <p style="font-size:0.78rem;font-weight:600;color:var(--gray-600);margin-bottom:4px;">Votre mot de passe doit contenir :</p>
                    <!-- Règle longueur minimale : au moins 8 caractères -->
                    <p id="rule-len"  class="pwd-rule">✗ Au moins 8 caractères</p>
                    <!-- Règle majuscule : au moins une lettre en majuscule -->
                    <p id="rule-up"   class="pwd-rule">✗ Une lettre majuscule</p>
                    <!-- Règle minuscule : au moins une lettre en minuscule -->
                    <p id="rule-low"  class="pwd-rule">✗ Une lettre minuscule</p>
                    <!-- Règle chiffre : au moins un chiffre -->
                    <p id="rule-num"  class="pwd-rule">✗ Un chiffre</p>
                </div>

                <!-- Date de naissance (optionnelle, stockée dans users.date_naissance) -->
                <!-- Groupe de champ pour la date de naissance, optionnelle et stockée en base -->
                <div class="form-group">
                    <!-- Label sans astérisque car le champ date de naissance est facultatif -->
                    <label>Date de naissance</label>
                    <!-- Champ de type date avec pré-remplissage depuis $_POST en cas d'erreur -->
                    <input type="date" name="birthdate" value="<?= htmlspecialchars($_POST['birthdate'] ?? '') ?>">
                </div>

                <!-- Case CGU obligatoire : required empêche la soumission si non cochée.
                     Le lien ouvre les CGU dans un nouvel onglet implicitement (même domaine). -->
                <!-- Label cliquable contenant la case à cocher et le texte des conditions -->
                <label style="display:flex; align-items:flex-start; gap:10px; font-size:0.9rem; color:var(--gray-600); cursor:pointer;">
                    <!-- Case à cocher obligatoire : la soumission est bloquée si elle n'est pas cochée -->
                    <input type="checkbox" name="terms" required style="margin-top:3px; flex-shrink:0;">
                    <!-- Texte explicatif des CGU avec lien vers la page dédiée -->
                    <span>
                        J'accepte les
                        <!-- Lien vers la page des conditions générales d'utilisation -->
                        <a href="/sharetime/public/?page=cgu" style="color:var(--navy); font-weight:600;">conditions générales d'utilisation</a>
                        et la politique de confidentialité.
                    </span>
                </label>

                <!-- Bouton de soumission : même classe .auth-btn que connexion.php -->
                <!-- Bouton principal de soumission du formulaire d'inscription -->
                <button type="submit" class="auth-btn">S'inscrire</button>
            </form>

            <!-- Lien de retour vers la connexion pour les utilisateurs déjà inscrits -->
            <!-- Paragraphe centré invitant les utilisateurs existants à se connecter -->
            <p style="margin-top:20px; text-align:center; color:var(--gray-500); font-size:0.92rem;">
                Déjà un compte ?
                <!-- Lien vers la page de connexion en navy gras pour le mettre en valeur -->
                <a href="/sharetime/public/?page=connexion" style="color:var(--navy); font-weight:700;">Se connecter</a>
            </p>
        </div>
    </div>
</main>

<!-- ── STYLES LOCAUX ──────────────────────────────────────────────────────────
     Similaires à connexion.php mais max-width plus large (1000px vs 900px)
     car le formulaire d'inscription est plus dense. -->
<style>
/* Grille 2 colonnes — même principe que .login-wrapper mais plus large */
.register-wrapper {
    width: 100%; /* Occupe toute la largeur disponible jusqu'au max-width */
    max-width: 1000px; /* Largeur maximale plus grande que la connexion car le formulaire est plus dense */
    display: grid; /* Utilise CSS Grid pour la mise en page en deux panneaux */
    grid-template-columns: 1fr 1fr; /* Deux colonnes égales : décoratif à gauche, formulaire à droite */
    background: white; /* Fond blanc de la carte */
    border-radius: 24px; /* Coins très arrondis pour le design moderne */
    overflow: hidden; /* Masque le débordement pour respecter les coins arrondis */
    box-shadow: 0 18px 45px rgba(0,0,0,0.08); /* Ombre douce pour l'effet de carte flottante */
    border: 1px solid var(--gray-200); /* Bordure subtile pour délimiter la carte */
}
/* Panneau gauche décoratif : même gradient que connexion */
.register-left {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); /* Dégradé diagonal du bleu foncé vers le bleu clair */
    padding: 60px 50px; /* Grand espacement intérieur pour aérer le contenu marketing */
    display: flex; /* Flexbox pour organiser les éléments en colonne */
    flex-direction: column; /* Empile les éléments verticalement */
    justify-content: center; /* Centre verticalement le contenu dans le panneau */
}
/* overflow-y:auto permet de scroller le formulaire sans agrandir la card sur mobile */
.register-right { padding: 40px; overflow-y: auto; } /* Padding autour du formulaire, défilement vertical si contenu trop long */

/* .form-row : mise en page 2 colonnes pour les paires de champs (prénom/nom, pseudo/ville…) */
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; } /* Deux colonnes égales pour les paires de champs */
.form-group { display: flex; flex-direction: column; gap: 6px; } /* Empile label et input avec 6px d'espace */
.form-group label { font-weight: 600; color: var(--gray-700); font-size: 0.9rem; } /* Label en semi-gras et couleur grise foncée */
/* Style commun input et select dans les formulaires d'auth */
.form-group input,
.form-group select {
    padding: 12px 14px; /* Espacement intérieur pour une zone de clic confortable */
    border: 1px solid var(--gray-300); /* Bordure fine grise par défaut */
    border-radius: 10px; /* Coins arrondis pour harmoniser avec le design */
    font-size: 0.92rem; /* Taille de police légèrement réduite mais lisible */
    font-family: inherit; /* Hérite la police de la page au lieu de la police système */
    outline: none; /* Supprime le contour par défaut navigateur (remplacé par box-shadow) */
    transition: border-color 0.2s; /* Transition douce sur la bordure au focus */
    background: white; /* Fond blanc explicite pour les selects qui peuvent hériter d'autres fonds */
}
.form-group input:focus,
.form-group select:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(30,58,110,0.1); } /* Bordure navy et halo bleu translucide quand le champ est actif */

/* Bouton de soumission : partagé avec connexion.php via la même classe */
.auth-btn {
    padding: 14px; /* Espacement intérieur pour agrandir la zone cliquable */
    background: var(--navy); /* Fond bleu navy correspondant à la charte graphique */
    color: white; /* Texte blanc pour contraste sur fond navy */
    border: none; /* Supprime la bordure par défaut des boutons HTML */
    border-radius: 12px; /* Coins arrondis pour le style moderne */
    font-size: 0.98rem; /* Taille de police proche du texte normal */
    font-weight: 700; /* Texte en gras pour accentuer l'action principale */
    cursor: pointer; /* Curseur main pour indiquer la cliquabilité */
    font-family: inherit; /* Hérite la police de la page */
    margin-top: 4px; /* Légère marge au-dessus pour espacer du dernier champ */
    transition: background 0.2s; /* Transition douce sur le fond au survol */
}
.auth-btn:hover { background: var(--navy-light); } /* Fond légèrement plus clair au survol pour l'effet interactif */

/* Bannière d'erreur : uniquement rouge ici (pas de succès sur cette page) */
.alert-error {
    background: #FEE2E2; color: #DC2626; /* Fond rouge pâle et texte rouge foncé pour l'erreur */
    padding: 12px 16px; border-radius: 10px; /* Espacement intérieur et coins arrondis */
    margin-bottom: 16px; font-weight: 500; font-size: 0.92rem; /* Marge en bas et texte semi-gras */
}

/* Checklist règles mot de passe */
.pwd-rule { font-size:0.8rem; margin:0; color:var(--gray-500); transition:color 0.15s; } /* Style de base gris avec transition pour l'animation ok/nok */
.pwd-rule.ok  { color:#16A34A; } /* Règle validée : texte vert */
.pwd-rule.nok { color:#EF4444; } /* Règle non validée : texte rouge */

/* Responsive 900px : une seule colonne, cache le panneau décoratif */
@media (max-width: 900px) {
    .register-wrapper { grid-template-columns: 1fr; box-shadow: none; border-radius: 16px; } /* Une seule colonne, supprime l'ombre sur mobile */
    .register-left { display: none; } /* Cache le panneau gauche décoratif sur tablette et mobile */
    .register-right { padding: 32px 24px; } /* Réduit le padding pour gagner de l'espace sur petit écran */
}
/* Responsive 640px : champs pleine largeur et padding minimal */
@media (max-width: 640px) {
    .form-row { grid-template-columns: 1fr; } /* Les paires de champs passent en colonne unique sur très petit écran */
    .register-right { padding: 24px 16px; } /* Padding encore réduit sur smartphone */
}
</style>

<script>
(function () {
    // Récupère l'élément input du champ mot de passe principal par son id
    var pwd     = document.getElementById('reg-password');
    // Récupère l'élément input du champ de confirmation du mot de passe
    var confirm = document.getElementById('reg-confirm');
    // Récupère le conteneur de la checklist des règles du mot de passe
    var rules   = document.getElementById('pwd-rules');
    // Récupère le paragraphe affichant le message de correspondance des mots de passe
    var matchMsg = document.getElementById('match-msg');
    // Arrête le script si le champ mot de passe n'existe pas dans le DOM
    if (!pwd) return;

    // Fonction utilitaire qui met à jour l'icône et la classe CSS d'une règle de mot de passe
    function checkRule(id, ok) {
        // Récupère l'élément de règle correspondant à l'identifiant donné
        var el = document.getElementById(id);
        // Extrait le texte de la règle en supprimant le préfixe ✓ ou ✗ existant
        var label = el.textContent.replace(/^[✓✗] /, '');
        // Remplace le préfixe par ✓ si la règle est respectée, ✗ sinon, et met à jour le texte
        el.textContent = (ok ? '✓ ' : '✗ ') + label;
        // Applique la classe CSS 'ok' (vert) ou 'nok' (rouge) selon le résultat de la règle
        el.className = 'pwd-rule ' + (ok ? 'ok' : 'nok');
    }

    // Écoute l'événement 'input' sur le champ mot de passe pour valider en temps réel
    pwd.addEventListener('input', function () {
        // Récupère la valeur actuelle saisie dans le champ mot de passe
        var v = this.value;
        // Affiche le bloc de règles en flex si du texte a été saisi, sinon le masque
        rules.style.display = v ? 'flex' : 'none';
        // Vérifie que le mot de passe fait au moins 8 caractères
        checkRule('rule-len', v.length >= 8);
        // Vérifie que le mot de passe contient au moins une lettre majuscule
        checkRule('rule-up',  /[A-Z]/.test(v));
        // Vérifie que le mot de passe contient au moins une lettre minuscule
        checkRule('rule-low', /[a-z]/.test(v));
        // Vérifie que le mot de passe contient au moins un chiffre
        checkRule('rule-num', /[0-9]/.test(v));
        // Si un texte est déjà saisi dans la confirmation, met à jour le message de correspondance
        if (confirm.value) checkMatch();
    });

    // Fonction qui compare le mot de passe et sa confirmation puis affiche le résultat
    function checkMatch() {
        // Retourne vrai si les deux champs sont identiques, faux sinon
        var ok = pwd.value === confirm.value;
        // Affiche le message seulement si la confirmation n'est pas vide
        matchMsg.style.display = confirm.value ? 'block' : 'none';
        // Affiche un message de succès ou d'erreur selon la correspondance des mots de passe
        matchMsg.textContent = ok ? '✓ Les mots de passe correspondent' : '✗ Les mots de passe ne correspondent pas';
        // Colorise le message en vert si les mots de passe correspondent, en rouge sinon
        matchMsg.style.color = ok ? '#16A34A' : '#EF4444';
    }

    // Écoute l'événement 'input' sur le champ de confirmation pour vérifier la correspondance
    confirm.addEventListener('input', checkMatch);
})(); // Exécute immédiatement la fonction anonyme pour éviter de polluer l'espace global
</script>
