<main class="container" style="padding:48px 0; max-width:620px; margin:auto;">

    <div style="text-align:center; margin-bottom:40px;">
        <p style="font-size:0.78rem; font-weight:600; color:var(--orange); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">
            Support
        </p>
        <h1 style="color:var(--navy); margin-bottom:10px;">Contactez-nous</h1>
        <p style="color:var(--gray-500);">Une question, un problème ou une suggestion ? Écrivez-nous.</p>
    </div>

    <?php if (!empty($success)): ?>
        <div style="background:#D1FAE5; color:#065F46; padding:20px 24px; border-radius:12px; text-align:center; font-weight:600; font-size:1.05rem;">
            ✅ <?= htmlspecialchars($success) ?>
        </div>
    <?php else: ?>

    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">

        <?php if (!empty($error)): ?>
            <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/sharetime/public/?page=contact"
              style="display:flex; flex-direction:column; gap:20px;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Nom *</label>
                    <input type="text" name="name" required
                           value="<?= htmlspecialchars($_POST['name'] ?? (isset($_SESSION['user']) ? $_SESSION['user']['prenom'] . ' ' . $_SESSION['user']['nom'] : '')) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Email *</label>
                    <input type="email" name="email" required
                           value="<?= htmlspecialchars($_POST['email'] ?? (isset($_SESSION['user']) ? $_SESSION['user']['email'] : '')) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Sujet</label>
                <input type="text" name="subject"
                       value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                       placeholder="Ex : Problème de connexion"
                       style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
            </div>

            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Message *</label>
                <textarea name="message" required rows="5" placeholder="Décrivez votre demande..."
                          style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; resize:vertical; box-sizing:border-box;"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-orange btn-lg">Envoyer le message</button>
        </form>
    </div>

    <?php endif; ?>
</main>

<style>
@media (max-width: 600px) {
    form > div:first-of-type { grid-template-columns: 1fr !important; }
}
</style>
