<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShareTime - Création de compte</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            margin: 0;
            font-family: Inter, sans-serif;
            background: linear-gradient(180deg, #F9FAFB 0%, #FFFFFF 100%);
            color: #1F2937;
        }

        .register-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .register-container {
            width: 100%;
            max-width: 1000px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #FFFFFF;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.08);
            border: 1px solid #E5E7EB;
        }

        .register-left {
            background: linear-gradient(135deg, #F3F4F6 0%, #E5E7EB 100%);
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .register-left .logo {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 20px;
            color: #111827;
        }

        .register-left .logo span {
            color: #6B7280;
        }

        .register-left h1 {
            font-size: 2.2rem;
            line-height: 1.2;
            margin-bottom: 16px;
            color: #111827;
        }

        .register-left p {
            font-size: 1rem;
            line-height: 1.7;
            color: #6B7280;
            max-width: 420px;
        }

        .register-features {
            margin-top: 30px;
            padding: 0;
            list-style: none;
        }

        .register-features li {
            margin-bottom: 14px;
            color: #4B5563;
            font-size: 0.95rem;
        }

        .register-right {
            padding: 50px 40px;
            background: #FFFFFF;
        }

        .register-right h2 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.9rem;
            color: #111827;
        }

        .register-subtitle {
            margin-bottom: 30px;
            color: #6B7280;
            font-size: 0.98rem;
        }

        .register-form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            color: #374151;
        }

        .form-group input,
        .form-group select {
            padding: 14px 16px;
            border: 1px solid #D1D5DB;
            border-radius: 12px;
            font-size: 0.95rem;
            color: #111827;
            background: #FFFFFF;
            transition: all 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #6B7280;
            box-shadow: 0 0 0 4px rgba(107, 114, 128, 0.12);
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 4px;
            font-size: 0.92rem;
            color: #6B7280;
        }

        .checkbox-group input {
            margin-top: 3px;
        }

        .checkbox-group a {
            color: #374151;
            text-decoration: none;
            font-weight: 600;
        }

        .checkbox-group a:hover {
            text-decoration: underline;
        }

        .register-btn {
            margin-top: 8px;
            border: none;
            border-radius: 14px;
            padding: 15px 20px;
            background: #111827;
            color: #FFFFFF;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .register-btn:hover {
            background: #374151;
            transform: translateY(-1px);
        }

        .error-message {
            background: #FEE2E2;
            color: #DC2626;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .success-message {
            background: #D1FAE5;
            color: #065F46;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .register-footer {
            margin-top: 22px;
            text-align: center;
            color: #6B7280;
            font-size: 0.95rem;
        }

        .register-footer a {
            color: #111827;
            font-weight: 700;
            text-decoration: none;
        }

        .register-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 900px) {
            .register-container {
                grid-template-columns: 1fr;
            }

            .register-left {
                padding: 40px 30px;
            }

            .register-right {
                padding: 35px 25px;
            }
        }

        @media (max-width: 640px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .register-left h1 {
                font-size: 1.8rem;
            }

            .register-right h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <main class="register-page">
        <section class="register-container">
            <div class="register-left">
                <div class="logo">Share<span>Time</span></div>
                <h1>Crée ton compte et rejoins la communauté</h1>
                <p>
                    Découvre des activités près de chez toi, partage tes passions
                    et rencontre des personnes qui ont les mêmes centres d'intérêt.
                </p>
                <ul class="register-features">
                    <li>✔ Trouve facilement des activités adaptées à tes envies</li>
                    <li>✔ Crée tes propres événements en quelques clics</li>
                    <li>✔ Rejoins une communauté locale conviviale et active</li>
                </ul>
            </div>

            <div class="register-right">
                <h2>Créer un compte</h2>
                <p class="register-subtitle">
                    Remplis les informations ci-dessous pour commencer l'aventure ShareTime.
                </p>

                <?php if (!empty($error)): ?>
                    <p class="error-message"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <p class="success-message"><?= htmlspecialchars($success) ?></p>
                <?php endif; ?>

                <form class="register-form" action="/sharetime/public/index.php?page=inscription" method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstname">Prénom</label>
                            <input type="text" id="firstname" name="firstname" placeholder="Ton prénom" required>
                        </div>
                        <div class="form-group">
                            <label for="lastname">Nom</label>
                            <input type="text" id="lastname" name="lastname" placeholder="Ton nom" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Pseudo</label>
                            <input type="text" id="username" name="username" placeholder="Choisis un pseudo" required>
                        </div>
                        <div class="form-group">
                            <label for="city">Ville</label>
                            <input type="text" id="city" name="city" placeholder="Ex : Paris" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Adresse e-mail</label>
                        <input type="email" id="email" name="email" placeholder="ton@email.com" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Mot de passe</label>
                            <input type="password" id="password" name="password" placeholder="••••••••" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm-password">Confirmer le mot de passe</label>
                            <input type="password" id="confirm-password" name="confirm-password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="birthdate">Date de naissance</label>
                            <input type="date" id="birthdate" name="birthdate" required>
                        </div>
                        <div class="form-group">
                            <label for="interest">Centre d'intérêt principal</label>
                            <select id="interest" name="interest" required>
                                <option value="">Sélectionne une catégorie</option>
                                <option value="sport">Sport</option>
                                <option value="culture">Culture</option>
                                <option value="musique">Musique</option>
                                <option value="food">Food</option>
                                <option value="voyage">Voyage</option>
                                <option value="bien-etre">Bien-être</option>
                            </select>
                        </div>
                    </div>

                    <label class="checkbox-group">
                        <input type="checkbox" name="terms" required>
                        <span>
                            J'accepte les <a href="#">conditions générales d'utilisation</a>
                            et la politique de confidentialité.
                        </span>
                    </label>

                    <button type="submit" class="register-btn">S'inscrire</button>
                </form>

                <p class="register-footer">
                    Tu as déjà un compte ?
                    <a href="/sharetime/public/index.php?page=connexion">Se connecter</a>
                </p>
            </div>
        </section>
    </main>
</body>
</html>