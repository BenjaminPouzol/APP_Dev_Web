<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShareTime — Partageons l'instant</title>
    <link rel="stylesheet" href="/sharetime/public/css/style.css">
    <style>
        footer {
            background-color: #303C6C;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .footer-column {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
        }
        .footer-column > div {
            flex: 0 0 200px;
            text-align: left;
        }
        .footer-column p {
            color: #ffffff;
            margin: 0 0 10px 0;
        }
        .footer-column h3 {
            font-size: 1.2rem;
            color: #E88600;
            margin: 0 0 10px 0;
        }
        .logo-Share {
            font-size: 2rem;
            font-weight: 800;
            color: #E88600;
        }
        .logo-Time {
            font-size: 2rem;
            font-weight: 800;
            color: #ffffff;
        }
        footer a {
            text-decoration: none;
            color: inherit;
        }
        footer a:hover {
            text-decoration: underline;
        }
        html, body {
    height: 100%;
    margin: 0;
}

body {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

main {
    flex: 1;
}

footer {
    margin-top: auto;
}
    </style>
</head>
<body>

<!-- ============================================================
    NAVBAR
    ============================================================ -->
<nav class="navbar">
    <div class="container">
        <a href="/sharetime/public/">
            <div class="navbar-logo-text">
                <span class="share">Share</span><span class="time">Time</span>
            </div>
        </a>

        <ul class="navbar-links">
            <li><a href="/sharetime/public/" class="active">Accueil</a></li>
            <li><a href="/sharetime/public/?page=activites">Activités</a></li>
            <li><a href="/sharetime/public/?page=faq">FAQ</a></li>
            <li><a href="/sharetime/public/?page=contact">Contact</a></li>
        </ul>

        <div class="navbar-actions">
            <?php if (isset($_SESSION['user'])): ?>
                <span style="margin-right: 15px;">
                    Bonjour <?= htmlspecialchars($_SESSION['user']['prenom']) ?>
                </span>
                <a href="/sharetime/public/?page=logout" class="btn btn-outline">Déconnexion</a>
            <?php else: ?>
                <a href="/sharetime/public/?page=connexion" class="btn btn-outline">Se connecter</a>
                <a href="/sharetime/public/?page=inscription" class="btn btn-orange">S'inscrire</a>
            <?php endif; ?>
        </div>
    </div>
</nav>