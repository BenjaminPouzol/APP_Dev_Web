<?php
/**
 * public/pages/reinitialiser_mdp.php — Formulaire de réinitialisation de mot de passe
 *
 * Variables disponibles :
 *   $error : message d'erreur (token invalide, mots de passe non conformes)
 *            $error est défini par le handler POST dans app/handlers/auth.php
 *
 * Cette page fait deux choses :
 *   1. En GET  : valide le token depuis la table password_resets (requête SQL directe ici,
 *                exception au pattern MVC car la validation du token est nécessaire
 *                avant même d'afficher le formulaire et avant d'instancier un modèle).
 *   2. En POST : le handler auth.php prend le relais pour changer le mot de passe en BDD
 *                et marquer le token comme utilisé (used = 1).
 *
 * Si le token est absent, expiré (> 1h) ou déjà utilisé, un message d'erreur est affiché
 * avec un lien vers la page de demande pour relancer le processus.
 *
 * Le JavaScript en fin de page valide en temps réel les contraintes du mot de passe
 * (longueur, majuscule, minuscule, chiffre) et vérifie la correspondance
 * des deux champs avant toute soumission.
 */

// Récupère le token depuis l'URL (GET) ou depuis le formulaire POST (re-soumission après erreur)
$reset_token = trim($_GET['token'] ?? $_POST['token'] ?? '');

// Vérification du token uniquement en GET : en POST, le handler auth.php revalide lui-même
$is_reset_token_valid = false;
$reset_email_address  = '';  // email associé au token, affiché pour rassurer l'utilisateur
if ($reset_token && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Vérifie que le token existe, n'a pas encore été utilisé (used=0) et n'est pas expiré
    $reset_token_stmt = $pdo->prepare("
        SELECT * FROM password_resets
        WHERE token = :token AND used = 0 AND expires_at > NOW()
    ");
    $reset_token_stmt->execute(['token' => $reset_token]);
    $reset_token_row = $reset_token_stmt->fetch();
    if ($reset_token_row) {
        // Token valide → on peut afficher le formulaire
        $is_reset_token_valid = true;
        $reset_email_address  = $reset_token_row['email'];
    }
}
?>

<main style="display:flex; align-items:flex-start; justify-content:center; padding:40px 20px; box-sizing:border-box;">
    <div style="width:100%; max-width:460px;">

        <!-- ── EN-TÊTE VISUEL ──────────────────────────────────────────────────
             Logo ShareTime + icône cadenas + titre de la page.
             Même structure que connexion.php pour la cohérence de la charte. -->
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

            <!-- Erreur retournée par le handler POST (ex : mots de passe différents, token invalide côté serveur) -->
            <?php if (!empty($error)): ?>
                <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500; font-size:0.95rem;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($reset_token) || (!$is_reset_token_valid && $_SERVER['REQUEST_METHOD'] === 'GET')): ?>

                <!-- ── ÉTAT INVALIDE : token absent ou expiré ──────────────────────────
                     Affiché si aucun token valide n'est présent en GET.
                     Les liens expirent après 1 heure (délai configuré dans handlers/auth.php). -->
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

                <!-- Indication de l'email concerné pour rassurer l'utilisateur qu'il est sur le bon compte -->
                <?php if ($is_reset_token_valid): ?>
                    <p style="color:var(--gray-500); font-size:0.88rem; margin-bottom:20px; text-align:center;">
                        Réinitialisation pour <strong><?= htmlspecialchars($reset_email_address) ?></strong>
                    </p>
                <?php endif; ?>

                <form method="POST" action="/sharetime/public/?page=reinitialiser_mdp"
                      style="display:flex; flex-direction:column; gap:18px;">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <!-- Token re-transmis en hidden pour que le handler POST puisse le valider et l'invalider -->
                    <input type="hidden" name="token" value="<?= htmlspecialchars($reset_token) ?>">

                    <!-- ── Champ nouveau mot de passe ──────────────────────────────────
                         La checklist des contraintes (#rst-rules) apparaît à la première saisie
                         et se met à jour en temps réel via JavaScript. -->
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

                    <!-- Checklist des contraintes : masquée tant que l'utilisateur n'a pas commencé à saisir -->
                    <div id="rst-rules" style="display:none;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:10px;padding:12px 16px;gap:5px;flex-direction:column;">
                        <p style="font-size:0.78rem;font-weight:600;color:var(--gray-600);margin-bottom:4px;">Votre mot de passe doit contenir :</p>
                        <!-- Chaque règle est mise à jour dynamiquement en ✓ vert / ✗ rouge par le JS -->
                        <p id="rst-rule-len" style="font-size:0.8rem;margin:0;color:var(--gray-500);">✗ Au moins 8 caractères</p>
                        <p id="rst-rule-up"  style="font-size:0.8rem;margin:0;color:var(--gray-500);">✗ Une lettre majuscule</p>
                        <p id="rst-rule-low" style="font-size:0.8rem;margin:0;color:var(--gray-500);">✗ Une lettre minuscule</p>
                        <p id="rst-rule-num" style="font-size:0.8rem;margin:0;color:var(--gray-500);">✗ Un chiffre</p>
                    </div>

                    <!-- ── Champ confirmation du mot de passe ──────────────────────────
                         Le JS compare ce champ avec le premier à chaque saisie
                         et affiche un message de correspondance (#rst-match). -->
                    <div>
                        <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                            Confirmer le mot de passe
                        </label>
                        <input type="password" name="confirm" id="rst-confirm" required minlength="8"
                               placeholder="Répétez le mot de passe"
                               style="width:100%; padding:14px 16px; border:1.5px solid var(--gray-300); border-radius:12px;
                                      font-size:0.95rem; font-family:inherit; box-sizing:border-box; outline:none; transition:border-color 0.2s;"
                               onfocus="this.style.borderColor='var(--navy)'" onblur="this.style.borderColor='var(--gray-300)'">
                        <!-- Message de correspondance / non-correspondance mis à jour en JS -->
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

        <!-- Lien de retour vers la connexion -->
        <p style="text-align:center; margin-top:20px; font-size:0.9rem; color:var(--gray-500);">
            <a href="/sharetime/public/?page=connexion" style="color:var(--navy); font-weight:700;">← Retour à la connexion</a>
        </p>
    </div>
</main>

<script>
(function () {
    // Références aux éléments DOM de la page de réinitialisation
    var password_input_el   = document.getElementById('rst-password');
    var confirm_input_el    = document.getElementById('rst-confirm');
    var password_rules_el   = document.getElementById('rst-rules');   // conteneur des règles
    var match_feedback_el   = document.getElementById('rst-match');    // message de correspondance

    // Si le champ n'existe pas (token invalide → formulaire non affiché), on arrête
    if (!password_input_el) return;

    // Met à jour l'icône et la couleur d'une règle (✓ vert si respectée, ✗ rouge sinon)
    function updatePasswordRule(rule_element_id, is_rule_satisfied) {
        var rule_el = document.getElementById(rule_element_id);
        // Supprime le préfixe ✓/✗ existant avant de le remplacer
        var rule_text = rule_el.textContent.replace(/^[✓✗] /, '');
        rule_el.textContent = (is_rule_satisfied ? '✓ ' : '✗ ') + rule_text;
        rule_el.style.color = is_rule_satisfied ? '#16A34A' : '#EF4444';
    }

    // À chaque saisie dans le champ mot de passe : afficher et mettre à jour la checklist
    password_input_el.addEventListener('input', function () {
        var password_value = this.value;
        // La checklist est masquée tant que le champ est vide
        password_rules_el.style.display = password_value ? 'flex' : 'none';
        // Mise à jour de chaque règle en temps réel
        updatePasswordRule('rst-rule-len', password_value.length >= 8);
        updatePasswordRule('rst-rule-up',  /[A-Z]/.test(password_value));
        updatePasswordRule('rst-rule-low', /[a-z]/.test(password_value));
        updatePasswordRule('rst-rule-num', /[0-9]/.test(password_value));
        // Met aussi à jour le feedback de correspondance si le champ confirme est déjà rempli
        if (confirm_input_el && confirm_input_el.value) verifyPasswordsMatch();
    });

    // Vérifie que les deux champs sont identiques et affiche le message de retour
    function verifyPasswordsMatch() {
        if (!confirm_input_el) return;
        var passwords_match = password_input_el.value === confirm_input_el.value;
        match_feedback_el.style.display = confirm_input_el.value ? 'block' : 'none';
        match_feedback_el.textContent = passwords_match
            ? '✓ Les mots de passe correspondent'
            : '✗ Les mots de passe ne correspondent pas';
        match_feedback_el.style.color = passwords_match ? '#16A34A' : '#EF4444';
    }

    // Déclenche la vérification à chaque frappe dans le champ de confirmation
    if (confirm_input_el) confirm_input_el.addEventListener('input', verifyPasswordsMatch);
})();
</script>
