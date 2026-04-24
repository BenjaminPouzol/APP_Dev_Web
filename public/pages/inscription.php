<main style="min-height:70vh; display:flex; align-items:center; justify-content:center; padding:40px 20px;">
    <div class="register-wrapper">

        <!-- Côté gauche -->
        <div class="register-left">
            <div style="font-family:'Poppins',sans-serif; font-size:1.5rem; font-weight:800; margin-bottom:20px;">
                <span style="color:var(--orange);">Share</span><span style="color:white;">Time</span>
            </div>
            <h1 style="font-size:2rem; color:white; margin-bottom:16px; line-height:1.2;">
                Rejoins la communauté
            </h1>
            <p style="color:rgba(255,255,255,0.65); line-height:1.7;">
                Découvre des activités près de chez toi, partage tes passions et rencontre des gens qui ont les mêmes centres d'intérêt.
            </p>
            <ul style="list-style:none; padding:0; margin-top:24px; display:flex; flex-direction:column; gap:10px;">
                <li style="color:rgba(255,255,255,0.8); font-size:0.92rem;">✔ Trouve des activités adaptées à tes envies</li>
                <li style="color:rgba(255,255,255,0.8); font-size:0.92rem;">✔ Crée tes propres événements en quelques clics</li>
                <li style="color:rgba(255,255,255,0.8); font-size:0.92rem;">✔ Rejoins une communauté locale et conviviale</li>
            </ul>
        </div>

        <!-- Côté droit -->
        <div class="register-right">
            <h2 style="color:var(--gray-900); margin-bottom:6px; font-size:1.6rem;">Créer un compte</h2>
            <p style="color:var(--gray-500); margin-bottom:24px; font-size:0.92rem;">
                Remplis les informations ci-dessous pour commencer.
            </p>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="/sharetime/public/?page=inscription" style="display:flex; flex-direction:column; gap:16px;">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="firstname" placeholder="Ton prénom" required
                               value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="lastname" placeholder="Ton nom" required
                               value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Pseudo *</label>
                        <input type="text" name="username" placeholder="Choisis un pseudo" required
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Ville</label>
                        <input type="text" name="city" placeholder="Ex : Paris"
                               value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Adresse e-mail *</label>
                    <input type="email" name="email" placeholder="ton@email.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Mot de passe *</label>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmer *</label>
                        <input type="password" name="confirm-password" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date de naissance</label>
                        <input type="date" name="birthdate" value="<?= htmlspecialchars($_POST['birthdate'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Centre d'intérêt</label>
                        <select name="interest">
                            <option value="">Sélectionner</option>
                            <option value="sport">Sport</option>
                            <option value="culture">Culture</option>
                            <option value="musique">Musique</option>
                            <option value="food">Food</option>
                            <option value="voyage">Voyage</option>
                            <option value="bien-etre">Bien-être</option>
                        </select>
                    </div>
                </div>

                <label style="display:flex; align-items:flex-start; gap:10px; font-size:0.9rem; color:var(--gray-600); cursor:pointer;">
                    <input type="checkbox" name="terms" required style="margin-top:3px; flex-shrink:0;">
                    <span>
                        J'accepte les
                        <a href="/sharetime/public/?page=cgu" style="color:var(--navy); font-weight:600;">conditions générales d'utilisation</a>
                        et la politique de confidentialité.
                    </span>
                </label>

                <button type="submit" class="auth-btn">S'inscrire</button>
            </form>

            <p style="margin-top:20px; text-align:center; color:var(--gray-500); font-size:0.92rem;">
                Déjà un compte ?
                <a href="/sharetime/public/?page=connexion" style="color:var(--navy); font-weight:700;">Se connecter</a>
            </p>
        </div>
    </div>
</main>

<style>
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
.register-left {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    padding: 60px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.register-right { padding: 40px; overflow-y: auto; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-weight: 600; color: var(--gray-700); font-size: 0.9rem; }
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
.alert-error {
    background: #FEE2E2; color: #DC2626;
    padding: 12px 16px; border-radius: 10px;
    margin-bottom: 16px; font-weight: 500; font-size: 0.92rem;
}
@media (max-width: 900px) {
    .register-wrapper { grid-template-columns: 1fr; }
    .register-left { padding: 40px 30px; }
    .register-right { padding: 30px 25px; }
}
@media (max-width: 640px) {
    .form-row { grid-template-columns: 1fr; }
}
</style>
