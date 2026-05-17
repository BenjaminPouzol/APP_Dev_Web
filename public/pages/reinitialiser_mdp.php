<?php
/**
 * public/pages/reinitialiser_mdp.php — Formulaire de réinitialisation de mot de passe
 *
 * Variables disponibles :
 *   $error : message d'erreur (token invalide, mots de passe non conformes)
 *            $error est défini par le handler POST dans app/handlers/auth.php
 *
 * Cette page fait deux choses :
 *   1. En GET  : valide le token depuis password_resets (requête SQL directe ici,
 *                exception au pattern car la validation du token est nécessaire
 *                avant même d'afficher le formulaire).
 *   2. En POST : le handler auth.php prend le relais pour changer le mot de passe.
 *
 * Si le token est absent ou expiré (> 1h ou déjà utilisé), un message d'erreur
 * est affiché avec un lien vers la page de demande.
 *
 * Le JavaScript en fin de page calcule la force du mot de passe en temps réel
 * et affiche une barre de progression colorée (rouge → vert).
 */

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

// Vérifier le token (seulement en GET, le POST est géré dans index.php)
$token_valid = false;
$token_email = '';
if ($token && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("
        SELECT * FROM password_resets
        WHERE token = :token AND used = 0 AND expires_at > NOW()
    ");
    $stmt->execute(['token' => $token]);
    $reset_row = $stmt->fetch();
    if ($reset_row) {
        $token_valid = true;
        $token_email = $reset_row['email'];
    }
}
?>

<main style="min-height:70vh; display:flex; align-items:center; justify-content:center; padding:40px 20px;">
    <div style="width:100%; max-width:460px;">

        <div style="text-align:center; margin-bottom:32px;">
            <div style="font-family:'Poppins',sans-serif; font-size:1.4rem; font-weight:800; margin-bottom:16px;">
                <span style="color:var(--orange);">Share</span><span style="color:var(--navy);">Time</span>
            </div>
            <div style="width:56px; height:56px; background:var(--orange-pale); border-radius:50%;
                        display:flex; align-items:center; justify-content:center;
                        font-size:1.5rem; margin:0 auto 16px;">🔒</div>
            <h1 style="color:var(--navy); font-size:1.5rem; margin-bottom:8px;">Nouveau mot de passe</h1>
        </div>

        <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px; box-shadow:0 4px 24px rgba(0,0,0,0.06);">

            <?php if (!empty($error)): ?>
                <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500; font-size:0.95rem;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($token) || (!$token_valid && $_SERVER['REQUEST_METHOD'] === 'GET')): ?>

                <!-- Token absent ou invalide -->
                <div style="text-align:center; padding:16px 0;">
                    <p style="color:var(--gray-600); margin-bottom:20px; line-height:1.6;">
                        Ce lien est <strong>invalide ou a expiré</strong>.<br>
                        Les liens de réinitialisation sont valables 1 heure.
                    </p>
                    <a href="/sharetime/public/?page=mot_de_passe_oublie" class="btn btn-orange">
                        Faire une nouvelle demande
                    </a>
                </div>

            <?php else: ?>

                <?php if ($token_valid): ?>
                    <p style="color:var(--gray-500); font-size:0.88rem; margin-bottom:20px; text-align:center;">
                        Réinitialisation pour <strong><?= htmlspecialchars($token_email) ?></strong>
                    </p>
                <?php endif; ?>

                <form method="POST" action="/sharetime/public/?page=reinitialiser_mdp"
                      style="display:flex; flex-direction:column; gap:18px;">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div>
                        <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                            Nouveau mot de passe
                        </label>
                        <input type="password" name="password" id="rst-password" required minlength="8"
                               placeholder="8 caractères minimum"
                               style="width:100%; padding:14px 16px; border:1.5px solid var(--gray-300); border-radius:12px;
                                      font-size:0.95rem; font-family:inherit; box-sizing:border-box; outline:none; transition:border-color 0.2s;"
                               onfocus="this.style.borderColor='var(--navy)'" onblur="this.style.borderColor='var(--gray-300)'">
                    </div>

                    <!-- Checklist de contraintes -->
                    <div id="rst-rules" style="display:none;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:10px;padding:12px 16px;gap:5px;flex-direction:column;">
                        <p style="font-size:0.78rem;font-weight:600;color:var(--gray-600);margin-bottom:4px;">Votre mot de passe doit contenir :</p>
                        <p id="rst-rule-len" style="font-size:0.8rem;margin:0;color:var(--gray-500);">✗ Au moins 8 caractères</p>
                        <p id="rst-rule-up"  style="font-size:0.8rem;margin:0;color:var(--gray-500);">✗ Une lettre majuscule</p>
                        <p id="rst-rule-low" style="font-size:0.8rem;margin:0;color:var(--gray-500);">✗ Une lettre minuscule</p>
                        <p id="rst-rule-num" style="font-size:0.8rem;margin:0;color:var(--gray-500);">✗ Un chiffre</p>
                    </div>

                    <div>
                        <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                            Confirmer le mot de passe
                        </label>
                        <input type="password" name="confirm" id="rst-confirm" required minlength="8"
                               placeholder="Répétez le mot de passe"
                               style="width:100%; padding:14px 16px; border:1.5px solid var(--gray-300); border-radius:12px;
                                      font-size:0.95rem; font-family:inherit; box-sizing:border-box; outline:none; transition:border-color 0.2s;"
                               onfocus="this.style.borderColor='var(--navy)'" onblur="this.style.borderColor='var(--gray-300)'">
                        <p id="rst-match" style="font-size:0.78rem;margin:4px 0 0;display:none;"></p>
                    </div>

                    <button type="submit"
                            style="padding:15px; background:var(--navy); color:white; border:none; border-radius:14px;
                                   font-size:1rem; font-weight:700; cursor:pointer; font-family:inherit; transition:background 0.2s;"
                            onmouseover="this.style.background='var(--navy-light)'" onmouseout="this.style.background='var(--navy)'">
                        Enregistrer le nouveau mot de passe
                    </button>
                </form>

            <?php endif; ?>

        </div>

        <p style="text-align:center; margin-top:20px; font-size:0.9rem; color:var(--gray-500);">
            <a href="/sharetime/public/?page=connexion" style="color:var(--navy); font-weight:700;">← Retour à la connexion</a>
        </p>
    </div>
</main>

<script>
(function () {
    var pwd     = document.getElementById('rst-password');
    var confirm = document.getElementById('rst-confirm');
    var rules   = document.getElementById('rst-rules');
    var matchEl = document.getElementById('rst-match');
    if (!pwd) return;

    function chk(id, ok) {
        var el = document.getElementById(id);
        var txt = el.textContent.replace(/^[✓✗] /, '');
        el.textContent = (ok ? '✓ ' : '✗ ') + txt;
        el.style.color = ok ? '#16A34A' : '#EF4444';
    }

    pwd.addEventListener('input', function () {
        var v = this.value;
        rules.style.display = v ? 'flex' : 'none';
        chk('rst-rule-len', v.length >= 8);
        chk('rst-rule-up',  /[A-Z]/.test(v));
        chk('rst-rule-low', /[a-z]/.test(v));
        chk('rst-rule-num', /[0-9]/.test(v));
        if (confirm && confirm.value) checkMatch();
    });

    function checkMatch() {
        if (!confirm) return;
        var ok = pwd.value === confirm.value;
        matchEl.style.display = confirm.value ? 'block' : 'none';
        matchEl.textContent = ok ? '✓ Les mots de passe correspondent' : '✗ Les mots de passe ne correspondent pas';
        matchEl.style.color = ok ? '#16A34A' : '#EF4444';
    }

    if (confirm) confirm.addEventListener('input', checkMatch);
})();
</script>
