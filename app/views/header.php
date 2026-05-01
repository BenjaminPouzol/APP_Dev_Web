<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShareTime — Partageons l'instant</title>
    <!-- Feuille de style principale (variables CSS, composants, responsive) -->
    <link rel="stylesheet" href="/sharetime/public/css/style.css">
    <style>
        /* ── Mise en page pleine hauteur ──────────────────────────────────────
           body en flex colonne garantit que le footer reste en bas même si la
           page a peu de contenu (footer collé en bas = "sticky footer") */
        html, body { height: 100%; margin: 0; }
        body { display: flex; flex-direction: column; min-height: 100vh; }
        main { flex: 1; }  /* main prend tout l'espace disponible entre navbar et footer */

        /* ── Toast notifications ──────────────────────────────────────────────
           Les toasts sont positionnés en fixed en haut à droite, empilés
           verticalement (flex column). z-index 9999 les place au-dessus de tout. */
        #toast-container {
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            display: flex; flex-direction: column; gap: 10px;
            max-width: 340px; width: calc(100% - 40px);  /* responsive sur mobile */
        }
        .toast {
            background: white; border-radius: 10px; padding: 14px 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.13);
            display: flex; align-items: flex-start; gap: 10px;
            animation: toast-in 0.3s ease;
            border-left: 4px solid #059669;  /* vert = succès par défaut */
        }
        .toast-error { border-left-color: #DC2626; }  /* rouge = erreur */
        .toast-info  { border-left-color: #3B82F6; }  /* bleu = info */
        .toast-icon {
            width: 20px; height: 20px; border-radius: 50%; background: #059669;
            color: white; display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem; font-weight: 700; flex-shrink: 0;
        }
        .toast-error .toast-icon { background: #DC2626; }
        .toast-info  .toast-icon { background: #3B82F6; }
        .toast-text { flex: 1; margin: 0; font-size: 0.88rem; color: #374151; font-weight: 500; line-height: 1.4; }
        .toast-close {
            background: none; border: none; cursor: pointer; color: #9CA3AF;
            font-size: 1.2rem; padding: 0; line-height: 1; flex-shrink: 0;
        }
        .toast-close:hover { color: #6B7280; }
        /* Animation d'entrée : glisse depuis la droite */
        @keyframes toast-in  { from { opacity:0; transform:translateX(40px); } to { opacity:1; transform:translateX(0); } }
        /* Animation de sortie : repart vers la droite (déclenchée par JS) */
        @keyframes toast-out { from { opacity:1; transform:translateX(0); } to { opacity:0; transform:translateX(40px); } }

        /* ── Bouton hamburger (menu mobile) ───────────────────────────────────
           Caché sur desktop (display:none), affiché sur mobile via @media */
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
            margin-left: auto;  /* pousse le bouton à droite dans le flex container */
            transition: background 0.15s;
        }
        .navbar-burger:hover { background: rgba(255,255,255,0.08); }

        /* Éléments réservés au menu mobile — cachés sur desktop */
        .mobile-only { display: none; }

        @media (max-width: 768px) {
            /* Sur mobile, .mobile-only est affiché et .navbar-actions est masqué */
            .mobile-only { display: block; }
            .navbar-burger { display: block; }

            /* Le menu devient un drawer vertical positionné en fixed sous la navbar */
            .navbar-links {
                display: none;         /* masqué par défaut, affiché avec la classe .open */
                position: fixed;
                top: 64px;             /* hauteur de la navbar */
                left: 0;
                right: 0;
                background: var(--navy);
                flex-direction: column;
                padding: 8px 0 16px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.25);
                z-index: 99;
                border-top: 1px solid rgba(255,255,255,0.08);
            }
            .navbar-links.open { display: flex; }  /* JS ajoute/retire cette classe */
            .navbar-links li { width: 100%; }
            .navbar-links a {
                height: auto !important;
                padding: 13px 24px !important;
                border-bottom: none !important;
                border-left: 3px solid transparent;  /* espace réservé pour l'indicateur actif */
            }
            /* Indicateur de page active : bordure gauche orange */
            .navbar-links a.active,
            .navbar-links a:hover { border-left-color: var(--orange); background: rgba(255,255,255,0.05); }

            /* Les boutons desktop (navbar-actions) sont masqués, remplacés par les .mobile-only */
            .navbar-actions { display: none; }
        }
    </style>
</head>
<body>

<!-- ── NAVBAR ─────────────────────────────────────────────────────────────── -->
<!-- La navbar est commune à toutes les pages.
     Elle utilise $page (défini dans index.php) pour surligner le lien actif,
     $notif_count et $msg_count pour afficher les badges de compteurs. -->
<nav class="navbar">
    <div class="container">

        <!-- Logo : cliquable, retourne à l'accueil -->
        <a href="/sharetime/public/" class="navbar-logo">
            <img src="/sharetime/public/images/logo.png" alt="ShareTime logo">
            <div class="navbar-logo-text">
                <span class="share">Share</span><span class="time">Time</span>
            </div>
        </a>

        <!-- Liens de navigation principaux (desktop : affichés en ligne, mobile : masqués par CSS) -->
        <ul class="navbar-links" id="navMenu">
            <li><a href="/sharetime/public/" <?= ($page === 'home') ? 'class="active"' : '' ?>>Accueil</a></li>
            <li><a href="/sharetime/public/?page=activites" <?= ($page === 'activites') ? 'class="active"' : '' ?>>Activités</a></li>
            <li><a href="/sharetime/public/?page=faq"     <?= ($page === 'faq')      ? 'class="active"' : '' ?>>FAQ</a></li>
            <li><a href="/sharetime/public/?page=contact" <?= ($page === 'contact')  ? 'class="active"' : '' ?>>Contact</a></li>

            <?php if (isset($_SESSION['user'])): ?>
                <!-- Liens supplémentaires visibles uniquement dans le menu mobile -->
                <li class="mobile-only" style="border-top: 1px solid rgba(255,255,255,0.08); margin-top:8px;">
                    <a href="/sharetime/public/?page=creer" style="color:var(--orange); font-weight:700;">+ Créer une activité</a>
                </li>
                <?php if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'owner'])): ?>
                <li class="mobile-only">
                    <?php if ($_SESSION['user']['role'] === 'owner'): ?>
                        <a href="/sharetime/public/?page=owner" style="color:var(--orange); font-weight:600;">👑 Espace Propriétaire</a>
                    <?php else: ?>
                        <a href="/sharetime/public/?page=admin" style="color:var(--orange); font-weight:600;">⚙️ Administration</a>
                    <?php endif; ?>
                </li>
                <?php endif; ?>
                <!-- Badge notifications dans le menu mobile -->
                <li class="mobile-only">
                    <a href="/sharetime/public/?page=notifications">
                        🔔 Notifications<?php if (!empty($notif_count)): ?>
                        <span style="background:var(--orange);color:white;font-size:0.65rem;font-weight:700;
                                     padding:1px 6px;border-radius:99px;margin-left:6px;"><?= $notif_count > 9 ? '9+' : $notif_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <!-- Badge messages dans le menu mobile -->
                <li class="mobile-only">
                    <a href="/sharetime/public/?page=messages">
                        ✉️ Messages<?php if (!empty($msg_count)): ?>
                        <span style="background:#3B82F6;color:white;font-size:0.65rem;font-weight:700;
                                     padding:1px 6px;border-radius:99px;margin-left:6px;"><?= $msg_count > 9 ? '9+' : $msg_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="mobile-only"><a href="/sharetime/public/?page=profil">Mon profil</a></li>
                <li class="mobile-only"><a href="/sharetime/public/?page=logout">Déconnexion</a></li>
            <?php else: ?>
                <li class="mobile-only" style="border-top: 1px solid rgba(255,255,255,0.08); margin-top:8px;">
                    <a href="/sharetime/public/?page=connexion" style="font-weight:600;">Se connecter</a>
                </li>
                <li class="mobile-only">
                    <a href="/sharetime/public/?page=inscription" style="color:var(--orange); font-weight:700;">S'inscrire</a>
                </li>
            <?php endif; ?>
        </ul>

        <!-- Boutons d'action desktop (masqués sur mobile par CSS) -->
        <div class="navbar-actions">
            <?php if (isset($_SESSION['user'])): ?>
                <!-- Bouton panel admin/owner selon le rôle -->
                <?php if ($_SESSION['user']['role'] === 'owner'): ?>
                    <a href="/sharetime/public/?page=owner"
                       class="btn btn-sm"
                       style="background:var(--orange-pale);color:var(--orange);border:1.5px solid rgba(232,129,26,0.4);">
                        👑 Propriétaire
                    </a>
                <?php elseif ($_SESSION['user']['role'] === 'admin'): ?>
                    <a href="/sharetime/public/?page=admin"
                       class="btn btn-sm"
                       style="background:var(--orange-pale);color:var(--orange);border:1.5px solid rgba(232,129,26,0.4);">
                        ⚙️ Admin
                    </a>
                <?php endif; ?>

                <a href="/sharetime/public/?page=creer" class="btn btn-orange btn-sm">+ Créer</a>

                <!-- Icône cloche : badge orange si notifications non lues -->
                <a href="/sharetime/public/?page=notifications"
                   style="position:relative; display:inline-flex; align-items:center; justify-content:center;
                          width:36px; height:36px; border-radius:8px; border:1.5px solid rgba(255,255,255,0.2);
                          color:white; text-decoration:none; font-size:1.1rem; transition:background 0.15s;"
                   onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'"
                   title="Notifications">
                    🔔
                    <?php if (!empty($notif_count)): ?>
                    <!-- Badge : max "9+" pour ne pas déborder sur les petites icônes -->
                    <span style="position:absolute; top:-4px; right:-4px; background:var(--orange); color:white;
                                 font-size:0.65rem; font-weight:700; min-width:16px; height:16px; border-radius:99px;
                                 display:flex; align-items:center; justify-content:center; padding:0 3px; line-height:1;">
                        <?= $notif_count > 9 ? '9+' : $notif_count ?>
                    </span>
                    <?php endif; ?>
                </a>

                <!-- Icône enveloppe : badge bleu si messages non lus -->
                <a href="/sharetime/public/?page=messages"
                   style="position:relative; display:inline-flex; align-items:center; justify-content:center;
                          width:36px; height:36px; border-radius:8px; border:1.5px solid rgba(255,255,255,0.2);
                          color:white; text-decoration:none; font-size:1.1rem; transition:background 0.15s;"
                   onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'"
                   title="Messages">
                    ✉️
                    <?php if (!empty($msg_count)): ?>
                    <span style="position:absolute; top:-4px; right:-4px; background:#3B82F6; color:white;
                                 font-size:0.65rem; font-weight:700; min-width:16px; height:16px; border-radius:99px;
                                 display:flex; align-items:center; justify-content:center; padding:0 3px; line-height:1;">
                        <?= $msg_count > 9 ? '9+' : $msg_count ?>
                    </span>
                    <?php endif; ?>
                </a>

                <!-- Affiche le pseudo (ou le prénom si pas de pseudo) comme lien vers le profil -->
                <a href="/sharetime/public/?page=profil" class="btn btn-outline btn-sm">
                    <?= htmlspecialchars($_SESSION['user']['pseudo'] ?? $_SESSION['user']['prenom']) ?>
                </a>
                <a href="/sharetime/public/?page=logout" class="btn btn-outline btn-sm">Déconnexion</a>
            <?php else: ?>
                <a href="/sharetime/public/?page=connexion" class="btn btn-outline">Se connecter</a>
                <a href="/sharetime/public/?page=inscription" class="btn btn-orange">S'inscrire</a>
            <?php endif; ?>
        </div>

        <!-- Bouton hamburger : visible uniquement sur mobile (CSS), déclenche le menu via JS -->
        <button class="navbar-burger" id="burgerBtn" aria-label="Menu" aria-expanded="false">☰</button>
    </div>
</nav>

<!-- ── JAVASCRIPT NAVBAR MOBILE ───────────────────────────────────────────── -->
<script>
(function() {
    var btn  = document.getElementById('burgerBtn');
    var menu = document.getElementById('navMenu');

    // Clic sur le burger : toggle la classe .open sur le menu et met à jour l'icône
    btn.addEventListener('click', function() {
        var open = menu.classList.toggle('open');
        btn.textContent = open ? '✕' : '☰';  // ✕ = fermer, ☰ = ouvrir
        btn.setAttribute('aria-expanded', open);  // accessibilité : indique l'état aux lecteurs d'écran
    });

    // Clic en dehors du menu : ferme automatiquement le menu mobile
    document.addEventListener('click', function(e) {
        if (!btn.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove('open');
            btn.textContent = '☰';
            btn.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>

<?php
// ── TOAST FLASH MESSAGES ──────────────────────────────────────────────────────
// Les flash messages sont lus et effacés dans index.php avant l'inclusion de ce fichier.
// Ici on les affiche sous forme de toasts avec une icône colorée selon le type.
if ($flash || $flash_html): ?>
<div id="toast-container">
    <div class="toast <?= $flash_type === 'error' ? 'toast-error' : ($flash_type === 'info' ? 'toast-info' : '') ?>" id="mainToast">
        <!-- Icône selon le type : ✓ succès, ✕ erreur, ℹ info -->
        <div class="toast-icon"><?= $flash_type === 'error' ? '✕' : ($flash_type === 'info' ? 'ℹ' : '✓') ?></div>
        <p class="toast-text">
            <?php if ($flash_html): ?>
                <?= $flash_html ?><!-- HTML autorisé pour les liens de dev (vérification email) -->
            <?php else: ?>
                <?= htmlspecialchars($flash) ?><!-- Texte brut : encodage obligatoire pour éviter le XSS -->
            <?php endif; ?>
        </p>
        <!-- Bouton de fermeture manuelle -->
        <button class="toast-close" onclick="closeToast()" aria-label="Fermer">×</button>
    </div>
</div>
<script>
    // Ferme le toast automatiquement après 5 secondes avec une animation de sortie
    var toastTimer = setTimeout(function() { closeToast(); }, 5000);

    function closeToast() {
        clearTimeout(toastTimer);
        var t = document.getElementById('mainToast');
        if (t) {
            t.style.animation = 'toast-out 0.3s ease forwards';  // animation CSS définie dans le <style>
            setTimeout(function() { t.remove(); }, 300);  // supprime l'élément après l'animation
        }
    }
</script>
<?php endif; ?>
