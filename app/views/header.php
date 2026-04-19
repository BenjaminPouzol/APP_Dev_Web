<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShareTime — Partageons l'instant</title>
    <link rel="stylesheet" href="/sharetime/public/css/style.css">
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
                <a href="/sharetime/public/?page=connexion#inscription" class="btn btn-orange">S'inscrire</a>
            <?php endif; ?>
        </div>
    </div>
</nav>