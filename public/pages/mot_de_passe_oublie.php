<?php
/**
 * public/pages/mot_de_passe_oublie.php — Demande de réinitialisation de mot de passe
 *
 * Variables disponibles (préparées par app/handlers/auth.php) :
 *   $success : message affiché à la place du formulaire après soumission valide
 *              (même message qu'un email n'existe pas pour éviter l'énumération)
 *   $error   : message d'erreur de validation (email invalide ou vide)
 *
 * Flux : l'utilisateur saisit son email → le handler génère un token → un email
 * est envoyé avec un lien vers ?page=reinitialiser_mdp&token=…  (valable 1h).
 */
?>
<main style="min-height:70vh; display:flex; align-items:center; justify-content:center; padding:40px 20px;">
    <div style="width:100%; max-width:460px;">

        <div style="text-align:center; margin-bottom:32px;">
            <div style="font-family:'Poppins',sans-serif; font-size:1.4rem; font-weight:800; margin-bottom:16px;">
                <span style="color:var(--orange);">Share</span><span style="color:var(--navy);">Time</span>
            </div>
            <div style="width:56px; height:56px; background:var(--orange-pale); border-radius:50%;
                        display:flex; align-items:center; justify-content:center;
                        font-size:1.5rem; margin:0 auto 16px;">🔑</div>
            <h1 style="color:var(--navy); font-size:1.5rem; margin-bottom:8px;">Mot de passe oublié</h1>
            <p style="color:var(--gray-500); font-size:0.95rem; line-height:1.6;">
                Saisissez votre adresse e-mail. Si un compte existe, vous recevrez un lien pour réinitialiser votre mot de passe.
            </p>
        </div>

        <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px; box-shadow:0 4px 24px rgba(0,0,0,0.06);">

            <?php if (!empty($success)): ?>
                <div style="background:#D1FAE5; color:#065F46; padding:16px 18px; border-radius:10px; text-align:center; font-weight:500; line-height:1.6;">
                    ✅ <?= htmlspecialchars($success) ?>
                </div>
                <p style="text-align:center; margin-top:20px; font-size:0.9rem; color:var(--gray-500);">
                    <a href="/sharetime/public/?page=connexion" style="color:var(--navy); font-weight:700;">← Retour à la connexion</a>
                </p>
            <?php else: ?>

                <?php if (!empty($error)): ?>
                    <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500; font-size:0.95rem;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/sharetime/public/?page=mot_de_passe_oublie"
                      style="display:flex; flex-direction:column; gap:18px;">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="form-group">
                        <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                            Adresse e-mail
                        </label>
                        <input type="email" name="email" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="ton@email.com"
                               style="width:100%; padding:14px 16px; border:1.5px solid var(--gray-300); border-radius:12px;
                                      font-size:0.95rem; font-family:inherit; box-sizing:border-box; outline:none; transition:border-color 0.2s;"
                               onfocus="this.style.borderColor='var(--navy)'" onblur="this.style.borderColor='var(--gray-300)'">
                    </div>

                    <button type="submit"
                            style="padding:15px; background:var(--navy); color:white; border:none; border-radius:14px;
                                   font-size:1rem; font-weight:700; cursor:pointer; font-family:inherit; transition:background 0.2s;"
                            onmouseover="this.style.background='var(--navy-light)'" onmouseout="this.style.background='var(--navy)'">
                        Envoyer le lien de réinitialisation
                    </button>
                </form>

                <p style="text-align:center; margin-top:20px; font-size:0.9rem; color:var(--gray-500);">
                    Vous vous souvenez de votre mot de passe ?
                    <a href="/sharetime/public/?page=connexion" style="color:var(--navy); font-weight:700;">Se connecter</a>
                </p>

            <?php endif; ?>
        </div>
    </div>
</main>
