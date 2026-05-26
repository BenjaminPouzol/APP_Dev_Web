<?php
/**
 * public/pages/connexion.php — Page de connexion
 *
 * Variables disponibles (préparées par index.php / handlers/auth.php) :
 *   $error : message d'erreur (identifiants invalides, compte banni, trop de tentatives…)
 *   $flash : message de succès (ex : "Compte créé, confirmez votre email")
 *
 * La logique POST (vérification identifiants, rate limiting, session) est dans
 * app/handlers/auth.php. Cette page ne fait aucun traitement — elle affiche
 * le formulaire et les éventuels messages.
 */
?>
<!-- ── CONTENEUR PRINCIPAL ────────────────────────────────────────────────────
     min-height:70vh pour que la card soit centrée verticalement même si peu
     de contenu. flex+center assure le centrage horizontal et vertical. -->
<!-- Balise main : zone de contenu principale de la page, centrée horizontalement et verticalement -->
<main style="display:flex; align-items:flex-start; justify-content:center; padding:40px 20px; box-sizing:border-box;">
    <!-- login-wrapper : grille 2 colonnes (gauche déco / droite formulaire) -->
    <!-- Conteneur global de la carte de connexion en deux panneaux -->
    <div class="login-wrapper">

        <!-- ── PANNEAU GAUCHE (décoratif) ────────────────────────────────────
             Gradient navy affiché uniquement sur desktop (colonne de gauche).
             Contient le logo inline + un texte d'accroche pour rassurer
             l'utilisateur revenant sur le site. -->
        <!-- Panneau gauche avec fond dégradé navy, visible uniquement sur grand écran -->
        <div class="login-left">
            <!-- Logo textuel inline : <span> orange pour "Share", blanc pour "Time" -->
            <!-- Conteneur du logo avec police Poppins et taille imposante -->
            <div style="font-family:'Poppins',sans-serif; font-size:1.5rem; font-weight:800; margin-bottom:24px;">
                <!-- Première partie du logo colorée en orange via la variable CSS -->
                <span style="color:var(--orange);">Share</span><span style="color:white;">Time</span>
            </div>
            <!-- Titre d'accroche affiché dans le panneau gauche pour accueillir l'utilisateur -->
            <h1 style="font-size:2rem; color:white; margin-bottom:16px; line-height:1.2;">Bon retour parmi nous !</h1>
            <!-- Texte descriptif en blanc semi-transparent pour guider l'utilisateur -->
            <p style="color:rgba(255,255,255,0.65); line-height:1.7;">
                Connecte-toi pour accéder à tes activités, gérer tes inscriptions
                et découvrir de nouveaux événements près de chez toi.
            </p>
        </div>

        <!-- ── PANNEAU DROIT (formulaire) ────────────────────────────────────
             Fond blanc, contient les champs email/password, le lien de récupération
             et les messages flash/erreur. -->
        <!-- Panneau droit blanc contenant le formulaire de connexion -->
        <div class="login-right">
            <!-- Titre principal du formulaire de connexion -->
            <h2 style="color:var(--gray-900); margin-bottom:6px; font-size:1.6rem;">Se connecter</h2>
            <!-- Sous-titre explicatif pour guider l'utilisateur sur l'action à effectuer -->
            <p style="color:var(--gray-500); margin-bottom:28px; font-size:0.95rem;">
                Entrez vos identifiants pour accéder à votre compte.
            </p>

            <!-- Message de succès : affiché après inscription, réinitialisation de mdp, etc. -->
            <!-- Vérifie si une variable $flash non vide existe pour afficher un message de succès -->
            <?php if (!empty($flash)): ?>
                <!-- Affiche le message flash en échappant les caractères spéciaux pour éviter les injections XSS -->
                <div class="alert-success"><?= htmlspecialchars($flash) ?></div>
            <?php endif; ?>

            <!-- Message d'erreur : identifiants invalides, compte banni, rate limit dépassé -->
            <!-- Vérifie si une variable $error non vide existe pour afficher un message d'erreur -->
            <?php if (!empty($error)): ?>
                <!-- Affiche le message d'erreur en échappant les caractères HTML pour sécuriser l'affichage -->
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Formulaire de connexion : POST vers le même handler (page=connexion) -->
            <!-- Formulaire envoyé en méthode POST vers l'URL de la page de connexion -->
            <form method="POST" action="/sharetime/public/?page=connexion" style="display:flex; flex-direction:column; gap:18px;">
                <!-- Token CSRF : protège contre la soumission depuis un autre site -->
                <!-- Champ caché contenant le jeton CSRF généré par la fonction csrf_token() pour sécuriser le formulaire -->
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <!-- Champ email -->
                <!-- Groupe de champ pour l'adresse e-mail avec label et input associé -->
                <div class="form-group">
                    <!-- Label visible par l'utilisateur pour identifier le champ e-mail -->
                    <label>Adresse e-mail</label>
                    <!-- Champ de saisie de type email avec placeholder et validation HTML5 obligatoire -->
                    <input type="email" name="email" placeholder="ton@email.com" required>
                </div>

                <!-- Champ mot de passe + lien "Mot de passe oublié ?" aligné à droite du label -->
                <!-- Groupe de champ pour le mot de passe avec label et lien de récupération -->
                <div class="form-group">
                    <!-- Ligne flex pour aligner le label à gauche et le lien de récupération à droite -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <!-- Label du champ mot de passe sans marge verticale car géré par le conteneur flex -->
                        <label style="margin:0;">Mot de passe</label>
                        <!-- Lien de récupération : hover orange via JS inline (pas de classe dédiée) -->
                        <!-- Lien vers la page de réinitialisation du mot de passe, couleur orange au survol -->
                        <a href="/sharetime/public/?page=mot_de_passe_oublie"
                           style="font-size:0.82rem; color:var(--gray-400); text-decoration:none;"
                           onmouseover="this.style.color='var(--orange)'" onmouseout="this.style.color='var(--gray-400)'">
                            Mot de passe oublié ?
                        </a>
                    </div>
                    <!-- Champ de saisie de type password qui masque les caractères saisis -->
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>

                <!-- Bouton de soumission : reprend le style .auth-btn défini dans le <style> ci-dessous -->
                <!-- Bouton principal de soumission du formulaire de connexion -->
                <button type="submit" class="auth-btn">Se connecter</button>
            </form>

            <!-- Lien vers l'inscription pour les nouveaux utilisateurs -->
            <!-- Paragraphe d'invitation à s'inscrire pour les utilisateurs sans compte -->
            <p style="margin-top:22px; text-align:center; color:var(--gray-500); font-size:0.95rem;">
                Pas encore de compte ?
                <!-- Lien vers la page d'inscription avec style navy et gras pour le mettre en valeur -->
                <a href="/sharetime/public/?page=inscription" style="color:var(--navy); font-weight:700;">S'inscrire</a>
            </p>
        </div>
    </div>
</main>

<!-- ── STYLES LOCAUX ──────────────────────────────────────────────────────────
     Styles spécifiques à cette page uniquement. Utilise les variables CSS
     globales (--navy, --orange, --gray-*) définis dans style.css.
     Le bloc responsive est en bas pour surcharger proprement les layouts. -->
<style>
/* Grille 2 colonnes : panneau décoratif gauche + formulaire droit */
.login-wrapper {
    width: 100%; /* Occupe toute la largeur disponible dans la limite du max-width */
    max-width: 900px; /* Largeur maximale de la carte pour ne pas s'étirer sur grand écran */
    display: grid; /* Utilise CSS Grid pour diviser en colonnes */
    grid-template-columns: 1fr 1fr; /* Deux colonnes de largeur égale */
    background: white; /* Fond blanc pour la carte globale */
    border-radius: 24px; /* Coins très arrondis pour un look moderne */
    overflow: hidden;                                  /* coupe le gradient aux coins arrondis */
    box-shadow: 0 18px 45px rgba(0,0,0,0.08); /* Ombre portée douce pour effet de carte flottante */
    border: 1px solid var(--gray-200); /* Bordure subtile pour délimiter la carte sur fond blanc */
}
/* Panneau gauche : gradient navy diagonal pour contraste avec le blanc du droit */
.login-left {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); /* Dégradé diagonal du bleu foncé vers le bleu clair */
    padding: 60px 50px; /* Grand espacement intérieur pour aérer le contenu */
    display: flex; /* Flexbox pour organiser les éléments en colonne */
    flex-direction: column; /* Empile logo, titre et texte verticalement */
    justify-content: center; /* Centre le contenu verticalement dans le panneau */
}
/* Panneau droit : juste du padding, fond blanc hérité du wrapper */
.login-right { padding: 50px 40px; } /* Espacement intérieur confortable autour du formulaire */

/* Groupe de champ : label + input en colonne avec gap fixe */
.form-group { display: flex; flex-direction: column; gap: 8px; } /* Empile label et input avec 8px d'espace entre eux */
.form-group label { font-weight: 600; color: var(--gray-700); font-size: 0.95rem; } /* Label en semi-gras et couleur grise foncée */
.form-group input {
    padding: 14px 16px; /* Espacement intérieur généreux pour une zone de clic confortable */
    border: 1px solid var(--gray-300); /* Bordure fine grise par défaut */
    border-radius: 12px; /* Coins arrondis pour harmoniser avec le design global */
    font-size: 0.95rem; /* Taille de police légèrement réduite mais lisible */
    font-family: inherit; /* Hérite la police de la page au lieu de la police système par défaut */
    outline: none; /* Supprime le contour par défaut du navigateur (remplacé par box-shadow) */
    transition: border-color 0.2s; /* Transition douce sur la couleur de bordure au focus */
}
/* Focus ring bleu-navy : accessible et cohérent avec la charte visuelle */
.form-group input:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(30,58,110,0.1); } /* Bordure navy + halo bleu translucide quand le champ est actif */

/* Bouton de soumission commun aux deux pages auth (connexion + inscription) */
.auth-btn {
    padding: 15px; /* Espacement intérieur pour agrandir la zone cliquable */
    background: var(--navy); /* Fond bleu navy correspondant à la charte */
    color: white; /* Texte blanc pour contraste sur fond navy */
    border: none; /* Supprime la bordure par défaut des boutons */
    border-radius: 14px; /* Coins très arrondis pour un style pill moderne */
    font-size: 1rem; /* Taille de police normale, identique au texte courant */
    font-weight: 700; /* Texte en gras pour accentuer l'action principale */
    cursor: pointer; /* Curseur main pour indiquer que le bouton est cliquable */
    font-family: inherit; /* Hérite la police de la page */
    margin-top: 4px; /* Légère marge au-dessus pour espacer du champ précédent */
    transition: background 0.2s; /* Transition douce sur le fond au survol */
}
.auth-btn:hover { background: var(--navy-light); } /* Fond légèrement plus clair au survol pour un effet interactif */

/* Bannières d'alerte : rouge pour erreur, vert pour succès */
.alert-error {
    background: #FEE2E2; color: #DC2626; /* Fond rouge pâle et texte rouge foncé pour signaler l'erreur */
    padding: 12px 16px; border-radius: 10px; /* Espacement intérieur et coins arrondis */
    margin-bottom: 20px; font-weight: 500; font-size: 0.95rem; /* Marge en bas, texte semi-gras */
}
.alert-success {
    background: #D1FAE5; color: #065F46; /* Fond vert pâle et texte vert foncé pour signaler le succès */
    padding: 12px 16px; border-radius: 10px; /* Espacement intérieur et coins arrondis */
    margin-bottom: 20px; font-weight: 500; font-size: 0.95rem; /* Marge en bas, texte semi-gras */
}

/* Règle responsive : sur mobile on passe en colonne et on cache le panneau décoratif */
@media (max-width: 700px) {
    .login-wrapper { grid-template-columns: 1fr; box-shadow: none; border-radius: 16px; } /* Une seule colonne, supprime l'ombre */
    .login-left { display: none; } /* Cache le panneau gauche décoratif sur petit écran */
    .login-right { padding: 32px 24px; } /* Réduit le padding sur mobile pour gagner de l'espace */
}
</style>
