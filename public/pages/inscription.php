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
<main style="min-height:70vh; display:flex; align-items:center; justify-content:center; padding:40px 20px;">
    <!-- register-wrapper : grille 2 colonnes (gauche déco / droite formulaire) -->
    <div class="register-wrapper">

        <!-- ── PANNEAU GAUCHE (décoratif) ────────────────────────────────────
             Gradient navy avec arguments de vente (3 bullets point) pour
             convaincre le visiteur de finaliser son inscription. -->
        <div class="register-left">
            <!-- Logo textuel inline -->
            <div style="font-family:'Poppins',sans-serif; font-size:1.5rem; font-weight:800; margin-bottom:20px;">
                <span style="color:var(--orange);">Share</span><span style="color:white;">Time</span>
            </div>
            <h1 style="font-size:2rem; color:white; margin-bottom:16px; line-height:1.2;">
                Rejoins la communauté
            </h1>
            <p style="color:rgba(255,255,255,0.65); line-height:1.7;">
                Découvre des activités près de chez toi, partage tes passions et rencontre des gens qui ont les mêmes centres d'intérêt.
            </p>
            <!-- Liste des bénéfices clés : accroches marketing courtes -->
            <ul style="list-style:none; padding:0; margin-top:24px; display:flex; flex-direction:column; gap:10px;">
                <li style="color:rgba(255,255,255,0.8); font-size:0.92rem;">✔ Trouve des activités adaptées à tes envies</li>
                <li style="color:rgba(255,255,255,0.8); font-size:0.92rem;">✔ Crée tes propres événements en quelques clics</li>
                <li style="color:rgba(255,255,255,0.8); font-size:0.92rem;">✔ Rejoins une communauté locale et conviviale</li>
            </ul>
        </div>

        <!-- ── PANNEAU DROIT (formulaire) ────────────────────────────────────
             Le formulaire est plus long que la connexion, d'où overflow-y:auto
             pour scroller sur les petits écrans sans agrandir la card. -->
        <div class="register-right">
            <h2 style="color:var(--gray-900); margin-bottom:6px; font-size:1.6rem;">Créer un compte</h2>
            <p style="color:var(--gray-500); margin-bottom:24px; font-size:0.92rem;">
                Remplis les informations ci-dessous pour commencer.
            </p>

            <!-- Erreur de validation côté serveur (email pris, mdp trop court, etc.) -->
            <?php if (!empty($error)): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Formulaire d'inscription : POST vers handlers/auth.php (page=inscription) -->
            <form method="POST" action="/sharetime/public/?page=inscription" style="display:flex; flex-direction:column; gap:16px;">
                <!-- Token CSRF obligatoire sur tous les formulaires POST -->
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <!-- Ligne 1 : prénom + nom (2 colonnes) -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Prénom *</label>
                        <!-- value pré-rempli depuis $_POST pour conserver la saisie en cas d'erreur -->
                        <input type="text" name="firstname" placeholder="Ton prénom" required
                               value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="lastname" placeholder="Ton nom" required
                               value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>">
                    </div>
                </div>

                <!-- Ligne 2 : pseudo + ville (2 colonnes) -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Pseudo *</label>
                        <input type="text" name="username" placeholder="Choisis un pseudo" required
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <!-- Ville optionnelle : non marquée * dans le label -->
                        <label>Ville</label>
                        <input type="text" name="city" placeholder="Ex : Paris"
                               value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                    </div>
                </div>

                <!-- Email : pleine largeur (1 colonne) -->
                <div class="form-group">
                    <label>Adresse e-mail *</label>
                    <input type="email" name="email" placeholder="ton@email.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <!-- Ligne 3 : mot de passe + confirmation (2 colonnes).
                     Les champs password ne sont jamais pré-remplis pour des raisons de sécurité. -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Mot de passe *</label>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmer *</label>
                        <!-- Le footer.php ajoute une validation JS côté client pour comparer les deux champs -->
                        <input type="password" name="confirm-password" placeholder="••••••••" required>
                    </div>
                </div>

                <!-- Date de naissance (optionnelle, stockée dans users.date_naissance) -->
                <div class="form-group">
                    <label>Date de naissance</label>
                    <input type="date" name="birthdate" value="<?= htmlspecialchars($_POST['birthdate'] ?? '') ?>">
                </div>

                <!-- Case CGU obligatoire : required empêche la soumission si non cochée.
                     Le lien ouvre les CGU dans un nouvel onglet implicitement (même domaine). -->
                <label style="display:flex; align-items:flex-start; gap:10px; font-size:0.9rem; color:var(--gray-600); cursor:pointer;">
                    <input type="checkbox" name="terms" required style="margin-top:3px; flex-shrink:0;">
                    <span>
                        J'accepte les
                        <a href="/sharetime/public/?page=cgu" style="color:var(--navy); font-weight:600;">conditions générales d'utilisation</a>
                        et la politique de confidentialité.
                    </span>
                </label>

                <!-- Bouton de soumission : même classe .auth-btn que connexion.php -->
                <button type="submit" class="auth-btn">S'inscrire</button>
            </form>

            <!-- Lien de retour vers la connexion pour les utilisateurs déjà inscrits -->
            <p style="margin-top:20px; text-align:center; color:var(--gray-500); font-size:0.92rem;">
                Déjà un compte ?
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
    width: 100%;
    max-width: 1000px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    background: white;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 18px 45px rgba(0,0,0,0.08);
    border: 1px solid var(--gray-200);
}
/* Panneau gauche décoratif : même gradient que connexion */
.register-left {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: 60px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
/* overflow-y:auto permet de scroller le formulaire sans agrandir la card sur mobile */
.register-right { padding: 40px; overflow-y: auto; }

/* .form-row : mise en page 2 colonnes pour les paires de champs (prénom/nom, pseudo/ville…) */
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-weight: 600; color: var(--gray-700); font-size: 0.9rem; }
/* Style commun input et select dans les formulaires d'auth */
.form-group input,
.form-group select {
    padding: 12px 14px;
    border: 1px solid var(--gray-300);
    border-radius: 10px;
    font-size: 0.92rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s;
    background: white;
}
.form-group input:focus,
.form-group select:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(30,58,110,0.1); }

/* Bouton de soumission : partagé avec connexion.php via la même classe */
.auth-btn {
    padding: 14px;
    background: var(--navy);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 0.98rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    margin-top: 4px;
    transition: background 0.2s;
}
.auth-btn:hover { background: var(--navy-light); }

/* Bannière d'erreur : uniquement rouge ici (pas de succès sur cette page) */
.alert-error {
    background: #FEE2E2; color: #DC2626;
    padding: 12px 16px; border-radius: 10px;
    margin-bottom: 16px; font-weight: 500; font-size: 0.92rem;
}

/* Responsive : passage en 1 colonne à 900px (tablette) */
@media (max-width: 900px) {
    .register-wrapper { grid-template-columns: 1fr; }
    .register-left { padding: 40px 30px; }
    .register-right { padding: 30px 25px; }
}
/* Les .form-row passent aussi en 1 colonne sur petit mobile */
@media (max-width: 640px) {
    .form-row { grid-template-columns: 1fr; }
}
</style>
