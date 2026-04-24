<main style="min-height:70vh; display:flex; align-items:center; justify-content:center; padding:40px 20px;">
    <div class="login-wrapper">

        <!-- Côté gauche -->
        <div class="login-left">
            <div style="font-family:'Poppins',sans-serif; font-size:1.5rem; font-weight:800; margin-bottom:24px;">
                <span style="color:var(--orange);">Share</span><span style="color:white;">Time</span>
            </div>
            <h1 style="font-size:2rem; color:white; margin-bottom:16px; line-height:1.2;">Bon retour parmi nous !</h1>
            <p style="color:rgba(255,255,255,0.65); line-height:1.7;">
                Connecte-toi pour accéder à tes activités, gérer tes inscriptions
                et découvrir de nouveaux événements près de chez toi.
            </p>
        </div>

        <!-- Côté droit -->
        <div class="login-right">
            <h2 style="color:var(--gray-900); margin-bottom:6px; font-size:1.6rem;">Se connecter</h2>
            <p style="color:var(--gray-500); margin-bottom:28px; font-size:0.95rem;">
                Entrez vos identifiants pour accéder à votre compte.
            </p>

            <?php if (!empty($flash)): ?>
                <div class="alert-success"><?= htmlspecialchars($flash) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="/sharetime/public/?page=connexion" style="display:flex; flex-direction:column; gap:18px;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="form-group">
                    <label>Adresse e-mail</label>
                    <input type="email" name="email" placeholder="ton@email.com" required>
                </div>

                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit" class="auth-btn">Se connecter</button>
            </form>

            <p style="margin-top:22px; text-align:center; color:var(--gray-500); font-size:0.95rem;">
                Pas encore de compte ?
                <a href="/sharetime/public/?page=inscription" style="color:var(--navy); font-weight:700;">S'inscrire</a>
            </p>
        </div>
    </div>
</main>

<style>
.login-wrapper {
    width: 100%;
    max-width: 900px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    background: white;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 18px 45px rgba(0,0,0,0.08);
    border: 1px solid var(--gray-200);
}
.login-left {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: 60px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.login-right { padding: 50px 40px; }
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
.form-group input:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(30,58,110,0.1); }
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
    .login-wrapper { grid-template-columns: 1fr; }
    .login-left { padding: 40px 30px; }
    .login-right { padding: 35px 25px; }
}
</style>
