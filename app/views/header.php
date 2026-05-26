<!DOCTYPE html> <!-- déclaration du type de document HTML5 -->
<html lang="fr"> <!-- définit la langue principale de la page (utilisé par les lecteurs d'écran et moteurs de recherche) -->
<head>
    <meta charset="UTF-8"> <!-- encodage des caractères : permet les accents et caractères spéciaux -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- adapte la mise en page à la largeur de l'écran sur mobile -->
    <title>ShareTime — Partageons l'instant</title> <!-- titre affiché dans l'onglet du navigateur -->
    <!-- Feuille de style principale (variables CSS, composants, responsive) -->
    <link rel="stylesheet" href="/sharetime/public/css/style.css"> <!-- charge le fichier CSS global du projet -->
    <style>
        /* ── Mise en page pleine hauteur ──────────────────────────────────────
           body en flex colonne garantit que le footer reste en bas même si la
           page a peu de contenu (footer collé en bas = "sticky footer") */
        html, body { height: 100%; margin: 0; } /* supprime les marges par défaut du navigateur */
        body { display: flex; flex-direction: column; min-height: 100vh; } /* colonne flex sur toute la hauteur de la fenêtre */
        main { flex: 1; }  /* main prend tout l'espace disponible entre navbar et footer */

        /* ── Toast notifications ──────────────────────────────────────────────
           Les toasts sont positionnés en fixed en haut à droite, empilés
           verticalement (flex column). z-index 9999 les place au-dessus de tout. */
        #toast-container {
            position: fixed; top: 20px; right: 20px; z-index: 9999; /* positionné par rapport à la fenêtre, toujours visible */
            display: flex; flex-direction: column; gap: 10px; /* toasts empilés verticalement avec un espace entre eux */
            max-width: 340px; width: calc(100% - 40px);  /* responsive sur mobile */
        }
        .toast {
            background: white; border-radius: 10px; padding: 14px 16px; /* fond blanc arrondi avec rembourrage interne */
            box-shadow: 0 4px 20px rgba(0,0,0,0.13); /* ombre portée pour donner de la profondeur */
            display: flex; align-items: flex-start; gap: 10px; /* icône, texte et bouton côte à côte */
            animation: toast-in 0.3s ease; /* animation d'entrée définie dans @keyframes toast-in */
            border-left: 4px solid #059669;  /* vert = succès par défaut */
        }
        .toast-error { border-left-color: #DC2626; }  /* rouge = erreur */
        .toast-info  { border-left-color: #3B82F6; }  /* bleu = info */
        .toast-icon {
            width: 20px; height: 20px; border-radius: 50%; background: #059669; /* cercle vert (succès) */
            color: white; display: flex; align-items: center; justify-content: center; /* icône centrée dans le cercle */
            font-size: 0.7rem; font-weight: 700; flex-shrink: 0; /* taille fixe : ne rétrécit pas si le texte est long */
        }
        .toast-error .toast-icon { background: #DC2626; } /* cercle rouge pour les toasts d'erreur */
        .toast-info  .toast-icon { background: #3B82F6; } /* cercle bleu pour les toasts d'information */
        .toast-text { flex: 1; margin: 0; font-size: 0.88rem; color: #374151; font-weight: 500; line-height: 1.4; } /* texte occupe tout l'espace disponible */
        .toast-close {
            background: none; border: none; cursor: pointer; color: #9CA3AF; /* bouton discret sans fond */
            font-size: 1.2rem; padding: 0; line-height: 1; flex-shrink: 0; /* taille fixe : ne rétrécit pas */
        }
        .toast-close:hover { color: #6B7280; } /* assombrit la croix au survol pour indiquer l'interactivité */
        /* Animation d'entrée : glisse depuis la droite */
        @keyframes toast-in  { from { opacity:0; transform:translateX(40px); } to { opacity:1; transform:translateX(0); } }
        /* Animation de sortie : repart vers la droite (déclenchée par JS) */
        @keyframes toast-out { from { opacity:1; transform:translateX(0); } to { opacity:0; transform:translateX(40px); } }

        /* ── Bouton hamburger (menu mobile) ───────────────────────────────────
           Caché sur desktop (display:none), affiché sur mobile via @media */
        .navbar-burger {
            display: none; /* caché par défaut (desktop) */
            background: none; /* pas de fond */
            border: 1.5px solid rgba(255,255,255,0.35); /* bordure blanche semi-transparente */
            color: white; /* icône blanche */
            font-size: 1.25rem; /* taille de l'icône ☰ */
            cursor: pointer; /* curseur main au survol */
            padding: 6px 11px; /* zone cliquable confortable */
            border-radius: 8px; /* coins arrondis */
            line-height: 1; /* hauteur de ligne minimale pour centrer l'icône */
            margin-left: auto;  /* pousse le bouton à droite dans le flex container */
            transition: background 0.15s; /* transition douce au survol */
        }
        .navbar-burger:hover { background: rgba(255,255,255,0.08); } /* légère surbrillance au survol */

        /* Éléments réservés au menu mobile — cachés sur desktop */
        .mobile-only { display: none; } /* caché par défaut, affiché via @media sur mobile */

        @media (max-width: 768px) {
            /* Sur mobile, .mobile-only est affiché et .navbar-actions est masqué */
            .mobile-only { display: block; } /* affiche les éléments réservés au mobile */
            .navbar-burger { display: block; } /* affiche le bouton hamburger */

            /* Le menu devient un drawer vertical positionné en fixed sous la navbar */
            .navbar-links {
                display: none;         /* masqué par défaut, affiché avec la classe .open */
                position: fixed; /* se superpose au contenu sans déplacer la mise en page */
                top: 64px;             /* hauteur de la navbar */
                left: 0;
                right: 0; /* s'étend sur toute la largeur de l'écran */
                background: var(--navy); /* même fond bleu marine que la navbar */
                flex-direction: column; /* liens empilés verticalement */
                padding: 8px 0 16px; /* espace interne vertical */
                box-shadow: 0 8px 24px rgba(0,0,0,0.25); /* ombre sous le menu pour le détacher du contenu */
                z-index: 99; /* au-dessus du contenu mais sous le toast (z-index 9999) */
                border-top: 1px solid rgba(255,255,255,0.08); /* séparateur subtil entre navbar et menu */
            }
            .navbar-links.open { display: flex; }  /* JS ajoute/retire cette classe pour ouvrir/fermer le menu */
            .navbar-links li { width: 100%; } /* chaque item prend toute la largeur pour une zone de clic maximale */
            .navbar-links a {
                height: auto !important; /* annule la hauteur fixe du desktop */
                padding: 13px 24px !important; /* rembourrage généreux pour faciliter le tap sur mobile */
                border-bottom: none !important; /* supprime l'indicateur de page active du desktop */
                border-left: 3px solid transparent;  /* espace réservé pour l'indicateur actif */
            }
            /* Indicateur de page active : bordure gauche orange */
            .navbar-links a.active,
            .navbar-links a:hover { border-left-color: var(--orange); background: rgba(255,255,255,0.05); } /* surbrillance au survol et page active */

            /* Les boutons desktop (navbar-actions) sont masqués, remplacés par les .mobile-only */
            .navbar-actions { display: none; } /* cache les boutons de la navbar desktop sur mobile */
        }
    </style>
</head>
<body> <!-- ouverture du corps de la page : sera fermé dans footer.php -->

<!-- ── NAVBAR ─────────────────────────────────────────────────────────────── -->
<!-- La navbar est commune à toutes les pages.
     Elle utilise $page (défini dans index.php) pour surligner le lien actif,
     $notif_count et $msg_count pour afficher les badges de compteurs. -->
<nav class="navbar"> <!-- barre de navigation principale (styles dans style.css) -->
    <div class="container"> <!-- centrage horizontal du contenu (largeur max + auto margin) -->

        <!-- Logo : cliquable, retourne à l'accueil -->
        <a href="/sharetime/public/" class="navbar-logo"> <!-- lien vers la page d'accueil -->
            <img src="/sharetime/public/images/logo.png" alt="ShareTime logo"> <!-- logo image avec texte alternatif -->
            <div class="navbar-logo-text"> <!-- conteneur du nom de marque en deux couleurs -->
                <span class="share">Share</span><span class="time">Time</span> <!-- "Share" en orange, "Time" en blanc (via CSS) -->
            </div>
        </a>

        <!-- Liens de navigation principaux (desktop : affichés en ligne, mobile : masqués par CSS) -->
        <ul class="navbar-links" id="navMenu"> <!-- liste de navigation, ciblée par le JS du burger -->
            <!-- Chaque lien reçoit class="active" si $page correspond, pour surligner l'onglet courant -->
            <li><a href="/sharetime/public/" <?= ($page === 'home') ? 'class="active"' : '' ?>>Accueil</a></li> <!-- lien actif si on est sur la page d'accueil -->
            <li><a href="/sharetime/public/?page=activites" <?= ($page === 'activites') ? 'class="active"' : '' ?>>Activités</a></li> <!-- lien actif sur le catalogue -->
            <li><a href="/sharetime/public/?page=carte"   <?= ($page === 'carte')    ? 'class="active"' : '' ?>>Carte</a></li> <!-- lien actif sur la carte -->
            <li><a href="/sharetime/public/?page=faq"     <?= ($page === 'faq')      ? 'class="active"' : '' ?>>FAQ</a></li> <!-- lien actif sur la FAQ -->
            <li><a href="/sharetime/public/?page=contact" <?= ($page === 'contact')  ? 'class="active"' : '' ?>>Contact</a></li> <!-- lien actif sur le contact -->

            <?php if (isset($_SESSION['user'])): ?> <!-- si l'utilisateur est connecté : affiche les liens utilisateur dans le menu mobile -->
                <!-- Liens supplémentaires visibles uniquement dans le menu mobile -->
                <li class="mobile-only" style="border-top: 1px solid rgba(255,255,255,0.08); margin-top:8px;"> <!-- séparateur visuel dans le menu mobile -->
                    <a href="/sharetime/public/?page=creer" style="color:var(--orange); font-weight:700;">+ Créer une activité</a> <!-- raccourci création, mis en avant en orange -->
                </li>
                <?php if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'owner'])): ?> <!-- si l'utilisateur a un rôle privilégié -->
                <li class="mobile-only">
                    <?php if ($_SESSION['user']['role'] === 'owner'): ?> <!-- cas du super-administrateur -->
                        <a href="/sharetime/public/?page=owner" style="color:var(--orange); font-weight:600;">👑 Super-Admin</a>
                    <?php else: ?> <!-- cas de l'administrateur classique -->
                        <a href="/sharetime/public/?page=admin" style="color:var(--orange); font-weight:600;">⚙️ Administration</a>
                    <?php endif; ?>
                </li>
                <?php endif; ?>
                <!-- Badge notifications dans le menu mobile -->
                <li class="mobile-only">
                    <a href="/sharetime/public/?page=notifications">
                        🔔 Notifications<?php if (!empty($notif_count)): ?> <!-- affiche le badge uniquement si des notifications non lues existent -->
                        <!-- Badge : max "9+" pour ne pas déborder sur les petites icônes -->
                        <span style="background:var(--orange);color:white;font-size:0.65rem;font-weight:700;
                                     padding:1px 6px;border-radius:99px;margin-left:6px;"><?= $notif_count > 9 ? '9+' : $notif_count ?></span> <!-- affiche le nombre ou "9+" si supérieur à 9 -->
                        <?php endif; ?>
                    </a>
                </li>
                <!-- Badge messages dans le menu mobile -->
                <li class="mobile-only">
                    <a href="/sharetime/public/?page=messages">
                        ✉️ Messages<?php if (!empty($msg_count)): ?> <!-- affiche le badge uniquement si des messages non lus existent -->
                        <span style="background:#3B82F6;color:white;font-size:0.65rem;font-weight:700;
                                     padding:1px 6px;border-radius:99px;margin-left:6px;"><?= $msg_count > 9 ? '9+' : $msg_count ?></span> <!-- badge bleu avec compteur -->
                        <?php endif; ?>
                    </a>
                </li>
                <li class="mobile-only"><a href="/sharetime/public/?page=profil">Mon profil</a></li> <!-- lien vers le profil personnel -->
                <li class="mobile-only"><a href="/sharetime/public/?page=logout">Déconnexion</a></li> <!-- lien de déconnexion (handler dans auth.php) -->
            <?php else: ?> <!-- si le visiteur n'est pas connecté : affiche les liens de connexion/inscription -->
                <li class="mobile-only" style="border-top: 1px solid rgba(255,255,255,0.08); margin-top:8px;"> <!-- séparateur visuel -->
                    <a href="/sharetime/public/?page=connexion" style="font-weight:600;">Se connecter</a>
                </li>
                <li class="mobile-only">
                    <a href="/sharetime/public/?page=inscription" style="color:var(--orange); font-weight:700;">S'inscrire</a> <!-- mis en avant en orange -->
                </li>
            <?php endif; ?>
        </ul>

        <!-- Boutons d'action desktop (masqués sur mobile par CSS) -->
        <div class="navbar-actions"> <!-- conteneur des actions droite de la navbar, visible uniquement en desktop -->
            <?php if (isset($_SESSION['user'])): ?> <!-- si l'utilisateur est connecté -->
                <!-- Bouton panel admin/owner selon le rôle -->
                <?php if ($_SESSION['user']['role'] === 'owner'): ?> <!-- bouton super-admin pour l'owner -->
                    <a href="/sharetime/public/?page=owner"
                       class="btn btn-sm"
                       style="background:var(--orange-pale);color:var(--orange);border:1.5px solid rgba(232,129,26,0.4);">
                        👑 Super-Admin
                    </a>
                <?php elseif ($_SESSION['user']['role'] === 'admin'): ?> <!-- bouton admin pour les administrateurs classiques -->
                    <a href="/sharetime/public/?page=admin"
                       class="btn btn-sm"
                       style="background:var(--orange-pale);color:var(--orange);border:1.5px solid rgba(232,129,26,0.4);">
                        ⚙️ Admin
                    </a>
                <?php endif; ?>

                <a href="/sharetime/public/?page=creer" class="btn btn-orange btn-sm">+ Créer</a> <!-- bouton principal d'appel à l'action pour créer une activité -->

                <!-- Icône cloche : badge orange si notifications non lues -->
                <a href="/sharetime/public/?page=notifications"
                   style="position:relative; display:inline-flex; align-items:center; justify-content:center;
                          width:36px; height:36px; border-radius:8px; border:1.5px solid rgba(255,255,255,0.2);
                          color:white; text-decoration:none; font-size:1.1rem; transition:background 0.15s;"
                   onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'"
                   title="Notifications"> <!-- survol JS inline (pseudo-classes inapplicables sur style inline) — info-bulle au survol -->
                    🔔
                    <?php if (!empty($notif_count)): ?> <!-- affiche le badge uniquement si au moins une notification non lue -->
                    <!-- Badge : max "9+" pour ne pas déborder sur les petites icônes -->
                    <span style="position:absolute; top:-4px; right:-4px; background:var(--orange); color:white;
                                 font-size:0.65rem; font-weight:700; min-width:16px; height:16px; border-radius:99px;
                                 display:flex; align-items:center; justify-content:center; padding:0 3px; line-height:1;">
                        <?= $notif_count > 9 ? '9+' : $notif_count ?> <!-- plafonne l'affichage à "9+" pour les grandes valeurs -->
                    </span>
                    <?php endif; ?>
                </a>

                <!-- Icône enveloppe : badge bleu si messages non lus -->
                <a href="/sharetime/public/?page=messages"
                   style="position:relative; display:inline-flex; align-items:center; justify-content:center;
                          width:36px; height:36px; border-radius:8px; border:1.5px solid rgba(255,255,255,0.2);
                          color:white; text-decoration:none; font-size:1.1rem; transition:background 0.15s;"
                   onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'"
                   title="Messages"> <!-- survol JS inline (cohérence avec le bouton cloche) — info-bulle au survol -->
                    ✉️
                    <?php if (!empty($msg_count)): ?> <!-- affiche le badge uniquement si des messages non lus existent -->
                    <span style="position:absolute; top:-4px; right:-4px; background:#3B82F6; color:white;
                                 font-size:0.65rem; font-weight:700; min-width:16px; height:16px; border-radius:99px;
                                 display:flex; align-items:center; justify-content:center; padding:0 3px; line-height:1;">
                        <?= $msg_count > 9 ? '9+' : $msg_count ?> <!-- plafonne l'affichage à "9+" pour les grandes valeurs -->
                    </span>
                    <?php endif; ?>
                </a>

                <!-- Affiche le pseudo (ou le prénom si pas de pseudo) comme lien vers le profil -->
                <a href="/sharetime/public/?page=profil" class="btn btn-outline btn-sm">
                    <?= htmlspecialchars($_SESSION['user']['pseudo'] ?? $_SESSION['user']['prenom']) ?> <!-- htmlspecialchars protège contre l'injection XSS -->
                </a>
                <a href="/sharetime/public/?page=logout" class="btn btn-outline btn-sm">Déconnexion</a> <!-- lien de déconnexion -->
            <?php else: ?> <!-- visiteur non connecté : affiche les boutons connexion / inscription -->
                <a href="/sharetime/public/?page=connexion" class="btn btn-outline">Se connecter</a>
                <a href="/sharetime/public/?page=inscription" class="btn btn-orange">S'inscrire</a> <!-- bouton principal en orange pour encourager l'inscription -->
            <?php endif; ?>
        </div>

        <!-- Bouton hamburger : visible uniquement sur mobile (CSS), déclenche le menu via JS -->
        <button class="navbar-burger" id="burgerBtn" aria-label="Menu" aria-expanded="false">☰</button> <!-- aria-expanded mis à jour par JS pour l'accessibilité -->
    </div>
</nav>

<!-- ── JAVASCRIPT NAVBAR MOBILE ───────────────────────────────────────────── -->
<script>
(function() { // IIFE : évite de polluer le scope global avec les variables btn et menu
    var btn  = document.getElementById('burgerBtn'); // référence au bouton hamburger
    var menu = document.getElementById('navMenu');   // référence à la liste de liens

    // Clic sur le burger : toggle la classe .open sur le menu et met à jour l'icône
    btn.addEventListener('click', function() {
        var open = menu.classList.toggle('open'); // bascule la classe .open et récupère le nouvel état
        btn.textContent = open ? '✕' : '☰';  // ✕ = fermer, ☰ = ouvrir
        btn.setAttribute('aria-expanded', open);  // accessibilité : indique l'état aux lecteurs d'écran
    });

    // Clic en dehors du menu : ferme automatiquement le menu mobile
    document.addEventListener('click', function(e) {
        if (!btn.contains(e.target) && !menu.contains(e.target)) { // vérifie que le clic est en dehors du bouton ET du menu
            menu.classList.remove('open'); // ferme le menu
            btn.textContent = '☰'; // restaure l'icône hamburger
            btn.setAttribute('aria-expanded', 'false'); // met à jour l'attribut d'accessibilité
        }
    });
})();
</script>

<?php
// ── TOAST FLASH MESSAGES ──────────────────────────────────────────────────────
// Les flash messages sont lus et effacés dans index.php avant l'inclusion de ce fichier.
// Ici on les affiche sous forme de toasts avec une icône colorée selon le type.
if ($flash || $flash_html): ?> <!-- affiche le bloc toast uniquement si un message flash est présent -->
<div id="toast-container"> <!-- conteneur positionné en fixed en haut à droite (styles CSS en haut du fichier) -->
    <!-- Classe CSS dynamique selon le type : vide = succès (vert), toast-error = rouge, toast-info = bleu -->
    <div class="toast <?= $flash_type === 'error' ? 'toast-error' : ($flash_type === 'info' ? 'toast-info' : '') ?>" id="mainToast">
        <!-- Icône selon le type : ✓ succès, ✕ erreur, ℹ info -->
        <div class="toast-icon"><?= $flash_type === 'error' ? '✕' : ($flash_type === 'info' ? 'ℹ' : '✓') ?></div> <!-- symbole adapté au type du message -->
        <p class="toast-text">
            <?php if ($flash_html): ?> <!-- HTML autorisé pour les liens de dev (vérification email) -->
                <?= $flash_html ?><!-- HTML autorisé pour les liens de dev (vérification email) -->
            <?php else: ?> <!-- texte brut : encodage obligatoire pour éviter le XSS -->
                <?= htmlspecialchars($flash) ?><!-- Texte brut : encodage obligatoire pour éviter le XSS -->
            <?php endif; ?>
        </p>
        <!-- Bouton de fermeture manuelle -->
        <button class="toast-close" onclick="closeToast()" aria-label="Fermer">×</button> <!-- déclenche closeToast() défini dans le script ci-dessous -->
    </div>
</div>
<script>
    // Ferme le toast automatiquement après 5 secondes avec une animation de sortie
    var toastTimer = setTimeout(function() { closeToast(); }, 5000); // démarre un minuteur de 5 secondes

    function closeToast() {
        clearTimeout(toastTimer); // annule le minuteur si la fermeture est déclenchée manuellement avant
        var t = document.getElementById('mainToast'); // récupère l'élément toast dans le DOM
        if (t) {
            t.style.animation = 'toast-out 0.3s ease forwards';  // animation CSS définie dans le <style>
            setTimeout(function() { t.remove(); }, 300);  // supprime l'élément après l'animation
        }
    }
</script>
<?php endif; ?> <!-- fin de la condition d'affichage du toast -->
