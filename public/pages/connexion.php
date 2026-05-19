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
<main style="display:flex; align-items:flex-start; justify-content:center; padding:40px 20px; box-sizing:border-box;">
    <!-- login-wrapper : grille 2 colonnes (gauche déco / droite formulaire) -->
    <div class="login-wrapper">

        <!-- ── PANNEAU GAUCHE (décoratif) ────────────────────────────────────
             Gradient navy affiché uniquement sur desktop (colonne de gauche).
             Contient le logo inline + un texte d'accroche pour rassurer
             l'utilisateur revenant sur le site. -->
        <div class="login-left">
            <!-- Logo textuel inline : <span> orange pour "Share", blanc pour "Time" -->
            <div style="font-family:'Poppins',sans-serif; font-size:1.5rem; font-weight:800; margin-bottom:24px;">
                <span style="color:var(--orange);">Share</span><span style="color:white;">Time</span>
            </div>
            <h1 style="font-size:2rem; color:white; margin-bottom:16px; line-height:1.2;">Bon retour parmi nous !</h1>
            <p style="color:rgba(255,255,255,0.65); line-height:1.7;">
                Connecte-toi pour accéder à tes activités, gérer tes inscriptions
                et découvrir de nouveaux événements près de chez toi.
            </p>
        </div>

        <!-- ── PANNEAU DROIT (formulaire) ────────────────────────────────────
             Fond blanc, contient les champs email/password, le lien de récupération
             et les messages flash/erreur. -->
        <div class="login-right">
            <h2 style="color:var(--gray-900); margin-bottom:6px; font-size:1.6rem;">Se connecter</h2>
            <p style="color:var(--gray-500); margin-bottom:28px; font-size:0.95rem;">
                Entrez vos identifiants pour accéder à votre compte.
            </p>

            <!-- Message de succès : affiché après inscription, réinitialisation de mdp, etc. -->
            <?php if (!empty($flash)): ?>
                <div class="alert-success"><?= htmlspecialchars($flash) ?></div>
            <?php endif; ?>

            <!-- Message d'erreur : identifiants invalides, compte banni, rate limit dépassé -->
            <?php if (!empty($error)): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Formulaire de connexion : POST vers le même handler (page=connexion) -->
            <form method="POST" action="/sharetime/public/?page=connexion" style="display:flex; flex-direction:column; gap:18px;">
                <!-- Token CSRF : protège contre la soumission depuis un autre site -->
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <!-- Champ email -->
                <div class="form-group">
                    <label>Adresse e-mail</label>
                    <input type="email" name="email" placeholder="ton@email.com" required>
                </div>

                <!-- Champ mot de passe + lien "Mot de passe oublié ?" aligné à droite du label -->
                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label style="margin:0;">Mot de passe</label>
                        <!-- Lien de récupération : hover orange via JS inline (pas de classe dédiée) -->
                        <a href="/sharetime/public/?page=mot_de_passe_oublie"
                           style="font-size:0.82rem; color:var(--gray-400); text-decoration:none;"
                           onmouseover="this.style.color='var(--orange)'" onmouseout="this.style.color='var(--gray-400)'">
                            Mot de passe oublié ?
                        </a>
                    </div>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>

                <!-- Bouton de soumission : reprend le style .auth-btn défini dans le <style> ci-dessous -->
                <button type="submit" class="auth-btn">Se connecter</button>
            </form>

            <!-- Lien vers l'inscription pour les nouveaux utilisateurs -->
            <p style="margin-top:22px; text-align:center; color:var(--gray-500); font-size:0.95rem;">
                Pas encore de compte ?
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
    width: 100%;
    max-width: 900px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    background: white;
    border-radius: 24px;
    overflow: hidden;                                  /* coupe le gradient aux coins arrondis */
    box-shadow: 0 18px 45px rgba(0,0,0,0.08);
    border: 1px solid var(--gray-200);
}
/* Panneau gauche : gradient navy diagonal pour contraste avec le blanc du droit */
.login-left {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: 60px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
/* Panneau droit : juste du padding, fond blanc hérité du wrapper */
.login-right { padding: 50px 40px; }

/* Groupe de champ : label + input en colonne avec gap fixe */
.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { font-weight: 600; color: var(--gray-700); font-size: 0.95rem; }
.form-group input {
    padding: 14px 16px;
    border: 1px solid var(--gray-300);
    border-radius: 12px;
    font-size: 0.95rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s;
}
/* Focus ring bleu-navy : accessible et cohérent avec la charte visuelle */
.form-group input:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(30,58,110,0.1); }

/* Bouton de soumission commun aux deux pages auth (connexion + inscription) */
.auth-btn {
    padding: 15px;
    background: var(--navy);
    color: white;
    border: none;
    border-radius: 14px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    margin-top: 4px;
    transition: background 0.2s;
}
.auth-btn:hover { background: var(--navy-light); }

/* Bannières d'alerte : rouge pour erreur, vert pour succès */
.alert-error {
    background: #FEE2E2; color: #DC2626;
    padding: 12px 16px; border-radius: 10px;
    margin-bottom: 20px; font-weight: 500; font-size: 0.95rem;
}
.alert-success {
    background: #D1FAE5; color: #065F46;
    padding: 12px 16px; border-radius: 10px;
    margin-bottom: 20px; font-weight: 500; font-size: 0.95rem;
}

@media (max-width: 700px) {
    .login-wrapper { grid-template-columns: 1fr; box-shadow: none; border-radius: 16px; }
    .login-left { display: none; }
    .login-right { padding: 32px 24px; }
}
</style>
