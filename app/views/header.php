<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShareTime — Partageons l'instant</title>
    <link rel="stylesheet" href="/sharetime/public/css/style.css">
    <style>
        html, body { height: 100%; margin: 0; }
        body { display: flex; flex-direction: column; min-height: 100vh; }
        main { flex: 1; }

        /* ── Hamburger ── */
        .navbar-burger {
            display: none;
            background: none;
            border: 1.5px solid rgba(255,255,255,0.35);
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 6px 11px;
            border-radius: 8px;
            line-height: 1;
            margin-left: auto;
            transition: background 0.15s;
        }
        .navbar-burger:hover { background: rgba(255,255,255,0.08); }

        /* Éléments réservés au menu mobile — cachés sur desktop */
        .mobile-only { display: none; }

        @media (max-width: 768px) {
            .mobile-only { display: block; }
            .navbar-burger { display: block; }

            .navbar-links {
                display: none;
                position: fixed;
                top: 64px;
                left: 0;
                right: 0;
                background: var(--navy);
                flex-direction: column;
                padding: 8px 0 16px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.25);
                z-index: 99;
                border-top: 1px solid rgba(255,255,255,0.08);
            }
            .navbar-links.open { display: flex; }
            .navbar-links li { width: 100%; }
            .navbar-links a {
                height: auto !important;
                padding: 13px 24px !important;
                border-bottom: none !important;
                border-left: 3px solid transparent;
            }
            .navbar-links a.active,
            .navbar-links a:hover { border-left-color: var(--orange); background: rgba(255,255,255,0.05); }

            .navbar-actions { display: none; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="container">
        <a href="/sharetime/public/">
            <div class="navbar-logo-text">
                <span class="share">Share</span><span class="time">Time</span>
            </div>
        </a>

        <ul class="navbar-links" id="navMenu">
            <li><a href="/sharetime/public/" <?= ($page === 'home') ? 'class="active"' : '' ?>>Accueil</a></li>
            <li><a href="/sharetime/public/?page=activites" <?= ($page === 'activites') ? 'class="active"' : '' ?>>Activités</a></li>
            <li><a href="/sharetime/public/?page=faq" <?= ($page === 'faq') ? 'class="active"' : '' ?>>FAQ</a></li>
            <li><a href="/sharetime/public/?page=contact" <?= ($page === 'contact') ? 'class="active"' : '' ?>>Contact</a></li>
            <?php if (isset($_SESSION['user'])): ?>
                <?php if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'owner'])): ?>
                <li class="mobile-only" style="border-top: 1px solid rgba(255,255,255,0.08); margin-top:8px;">
                    <a href="/sharetime/public/?page=admin" style="color:var(--orange); font-weight:700;">⚙️ Administration</a>
                </li>
                <?php else: ?>
                <li class="mobile-only" style="border-top: 1px solid rgba(255,255,255,0.08); margin-top:8px;">
                    <a href="/sharetime/public/?page=creer" style="color:var(--orange); font-weight:700;">+ Créer une activité</a>
                </li>
                <?php endif; ?>
                <li class="mobile-only">
                    <a href="/sharetime/public/?page=profil">Mon profil</a>
                </li>
                <li class="mobile-only">
                    <a href="/sharetime/public/?page=logout">Déconnexion</a>
                </li>
            <?php else: ?>
                <li class="mobile-only" style="border-top: 1px solid rgba(255,255,255,0.08); margin-top:8px;">
                    <a href="/sharetime/public/?page=connexion" style="font-weight:600;">Se connecter</a>
                </li>
                <li class="mobile-only">
                    <a href="/sharetime/public/?page=inscription" style="color:var(--orange); font-weight:700;">S'inscrire</a>
                </li>
            <?php endif; ?>
        </ul>

        <div class="navbar-actions">
            <?php if (isset($_SESSION['user'])): ?>
                <?php if (in_array($_SESSION['user']['role'], ['admin', 'owner'])): ?>
                    <a href="/sharetime/public/?page=admin"
                       class="btn btn-sm"
                       style="background:var(--orange-pale);color:var(--orange);border:1.5px solid rgba(232,129,26,0.4);">
                        ⚙️ Admin
                    </a>
                <?php endif; ?>
                <a href="/sharetime/public/?page=creer" class="btn btn-orange btn-sm">+ Créer</a>
                <a href="/sharetime/public/?page=profil" class="btn btn-outline btn-sm">
                    <?= htmlspecialchars($_SESSION['user']['pseudo'] ?? $_SESSION['user']['prenom']) ?>
                </a>
                <a href="/sharetime/public/?page=logout" class="btn btn-outline btn-sm">Déconnexion</a>
            <?php else: ?>
                <a href="/sharetime/public/?page=connexion" class="btn btn-outline">Se connecter</a>
                <a href="/sharetime/public/?page=inscription" class="btn btn-orange">S'inscrire</a>
            <?php endif; ?>
        </div>

        <button class="navbar-burger" id="burgerBtn" aria-label="Menu" aria-expanded="false">☰</button>
    </div>
</nav>

<script>
(function() {
    var btn  = document.getElementById('burgerBtn');
    var menu = document.getElementById('navMenu');
    btn.addEventListener('click', function() {
        var open = menu.classList.toggle('open');
        btn.textContent   = open ? '✕' : '☰';
        btn.setAttribute('aria-expanded', open);
    });
    // Ferme le menu si on clique en dehors
    document.addEventListener('click', function(e) {
        if (!btn.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove('open');
            btn.textContent = '☰';
            btn.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>
