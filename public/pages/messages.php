<?php
/**
 * public/pages/messages.php — Interface de messagerie privée
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $conversations         : tableau des conversations avec le dernier message,
 *                            le compteur de non-lus et les infos de l'interlocuteur
 *   $conversation_user     : tableau des infos de l'interlocuteur actif (ou null si aucun ouvert)
 *   $conversation_messages : messages de la conversation active (ou tableau vide)
 *   $with_id               : ID de l'interlocuteur actif (ou null)
 *
 * Layout deux panneaux :
 *   - Sidebar gauche (280px fixe) : liste des conversations avec aperçu du dernier message
 *   - Zone principale droite : bulles de la conversation active ou état vide
 *
 * Sur mobile (≤768px) : la grille passe en 1 colonne.
 * Quand une conversation est ouverte, la sidebar est masquée sur mobile
 * pour laisser toute la place aux messages (retour possible via le bouton ← en haut).
 */
?>
<!-- ── STYLES LOCAUX ──────────────────────────────────────────────────────────
     Tous les styles de messagerie sont isolés ici pour ne pas polluer style.css.
     On utilise les variables CSS globales pour rester cohérent avec la charte. -->
<style>
/* Conteneur principal : grille 2 colonnes avec hauteur calculée pour remplir la fenêtre */
.msg-layout {
    display: grid;
    grid-template-columns: 280px 1fr;  /* sidebar fixe + zone principale flexible */
    gap: 0;
    height: calc(100vh - 180px);       /* 180px ≈ hauteur navbar + padding page */
    min-height: 500px;
    background: white;
    border: 1.5px solid var(--gray-200);
    border-radius: var(--radius-lg);
    overflow: hidden;                  /* coins arrondis sur tous les enfants */
}

/* Sidebar gauche : liste des conversations scrollable */
.msg-sidebar {
    border-right: 1.5px solid var(--gray-200);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.msg-sidebar-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--gray-100);
    font-weight: 700;
    color: var(--navy);
    font-size: 0.95rem;
    flex-shrink: 0;   /* ne se compresse pas quand les conversations débordent */
}
/* Zone scrollable de la liste des conversations */
.msg-conv-list {
    flex: 1;
    overflow-y: auto;
}

/* Item de conversation dans la sidebar */
.msg-conv-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid var(--gray-100);
    transition: background 0.1s;
}
.msg-conv-item:hover  { background: var(--gray-50); }
.msg-conv-item.active { background: #EBF0F8; }  /* bleu pâle pour la conversation actuellement ouverte */

/* Avatar circulaire : photo de profil ou initiale sur fond gradient navy */
.msg-conv-avatar {
    width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, var(--navy), var(--navy-light));
    color: white; font-size: 1rem; font-weight: 700;
    overflow: hidden;  /* clip la photo si elle dépasse le cercle */
}
.msg-conv-avatar img { width: 100%; height: 100%; object-fit: cover; }
.msg-conv-info { flex: 1; min-width: 0; }  /* min-width:0 pour que text-overflow fonctionne dans le flex */

/* Nom et aperçu tronqués par ellipsis pour ne pas déborder de la sidebar */
.msg-conv-name    { font-weight: 600; font-size: 0.88rem; color: var(--gray-900); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.msg-conv-preview { font-size: 0.78rem; color: var(--gray-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
.msg-conv-meta    { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.msg-conv-time    { font-size: 0.72rem; color: var(--gray-400); }

/* Badge de compteur de messages non lus : orange, max 99 affiché (min() en PHP) */
.msg-unread-badge {
    background: var(--orange); color: white;
    font-size: 0.65rem; font-weight: 700;
    min-width: 18px; height: 18px; border-radius: 99px;
    display: flex; align-items: center; justify-content: center; padding: 0 4px;
}

/* Zone principale : flex colonne pour en-tête + corps scrollable + barre de saisie */
.msg-main {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* En-tête de la conversation active : avatar + nom + pseudo de l'interlocuteur */
.msg-conv-header {
    padding: 14px 20px;
    border-bottom: 1px solid var(--gray-100);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;  /* ne se compresse pas pour laisser de la place aux messages */
}

/* Zone des bulles : scrollable, s'étend pour occuper tout l'espace disponible */
.msg-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* Ligne de bulle : flex horizontal, envoyés à droite via row-reverse */
.msg-bubble-row      { display: flex; align-items: flex-end; gap: 8px; }
.msg-bubble-row.sent { flex-direction: row-reverse; }  /* retourne la ligne pour les messages envoyés */

/* Bulle de message */
.msg-bubble {
    max-width: 65%;          /* pas toute la largeur pour laisser respirer l'œil */
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 0.88rem;
    line-height: 1.5;
    word-break: break-word;  /* coupe les mots très longs (URLs collées, etc.) */
}
/* Reçus : fond gris clair, coin gauche bas aplati (queue de bulle côté gauche) */
.msg-bubble.received { background: var(--gray-100); color: var(--gray-900); border-bottom-left-radius: 4px; }
/* Envoyés : fond navy, coin droit bas aplati (queue de bulle côté droit) */
.msg-bubble.sent     { background: var(--navy); color: white; border-bottom-right-radius: 4px; }
.msg-bubble-time     { font-size: 0.7rem; color: var(--gray-400); flex-shrink: 0; margin-bottom: 4px; }

/* Barre de saisie en bas : textarea auto-resize + bouton Envoyer */
.msg-form-bar {
    padding: 14px 20px;
    border-top: 1px solid var(--gray-100);
    display: flex;
    gap: 10px;
    align-items: flex-end;
    flex-shrink: 0;
}
.msg-form-bar textarea {
    flex: 1;
    padding: 10px 14px;
    border: 1.5px solid var(--gray-300);
    border-radius: 10px;
    font-size: 0.9rem;
    font-family: inherit;
    resize: none;          /* redimensionnement automatique géré par JS */
    max-height: 120px;     /* empêche de prendre toute la hauteur sur les très longs messages */
    line-height: 1.4;
    transition: border-color 0.15s;
}
.msg-form-bar textarea:focus { outline: none; border-color: var(--navy); }

/* État vide (aucune conversation sélectionnée) : icône + texte centrés verticalement */
.msg-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--gray-400);
    gap: 12px;
}

/* Responsive mobile : grille en 1 colonne + hauteur auto */
@media (max-width: 768px) {
    .msg-layout { grid-template-columns: 1fr; height: auto; min-height: 0; }
    .msg-sidebar { border-right: none; border-bottom: 1.5px solid var(--gray-200); max-height: 260px; }
    <?php if ($conversation_user): ?>
    /* Quand une conversation est ouverte sur mobile, masquer la sidebar pour tout l'espace aux messages */
    .msg-sidebar { display: none; }
    <?php endif; ?>
    .msg-main { min-height: 500px; }
}
</style>

<main class="container" style="padding:40px 0;">

    <!-- En-tête de page : titre + bouton ← de retour sur mobile (si conversation ouverte) -->
    <div style="display:flex; align-items:center; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
        <!-- Bouton ← visible uniquement si une conversation est ouverte (retour vers la liste) -->
        <?php if ($conversation_user): // affiche la flèche de retour seulement si une conversation est active ?>
            <a href="/sharetime/public/?page=messages" style="color:var(--gray-400); text-decoration:none; font-size:1.2rem; line-height:1;" title="Retour">&larr;</a>
        <?php endif; ?>
        <h1 style="color:var(--navy); margin:0;">Messages</h1>
    </div>

    <div class="msg-layout">

        <!-- ── SIDEBAR : LISTE DES CONVERSATIONS ─────────────────────────── -->
        <div class="msg-sidebar">
            <div class="msg-sidebar-header">Conversations</div>
            <div class="msg-conv-list">
                <!-- État vide : aucune conversation commencée -->
                <?php if (empty($conversations)): // aucune conversation existante pour cet utilisateur ?>
                    <div style="padding:32px 16px; text-align:center; color:var(--gray-400); font-size:0.85rem;">
                        Aucune conversation.<br>Visitez un profil pour envoyer un message.
                    </div>
                <?php endif; ?>

                <?php foreach ($conversations as $conversation_item): // boucle sur chaque fil de discussion
                    // Nom d'affichage de l'interlocuteur : pseudo préféré, prénom en fallback
                    $contact_display_name = htmlspecialchars($conversation_item['pseudo'] ?: $conversation_item['prenom']); // opérateur ?: : utilise le pseudo s'il existe, sinon le prénom

                    // Initiale du prénom pour l'avatar-lettre si pas de photo de profil
                    $contact_name_initial = strtoupper(mb_substr($conversation_item['prenom'], 0, 1)); // extrait la première lettre du prénom en majuscule (compatible UTF-8)

                    // Formatage de l'heure du dernier message :
                    // "HH:MM" si c'est aujourd'hui, "dd/mm" si c'est un autre jour
                    $last_message_time = ''; // valeur par défaut si aucun message n'a encore été échangé
                    if ($conversation_item['last_time']) { // vérifie qu'un horodatage de dernier message existe
                        $conversation_datetime = new DateTime($conversation_item['last_time']); // convertit la chaîne SQL en objet DateTime manipulable
                        $current_datetime      = new DateTime(); // crée un DateTime représentant l'instant présent
                        $last_message_time     = $conversation_datetime->format('d/m') === $current_datetime->format('d/m')
                            ? $conversation_datetime->format('H:i')   // même jour → affiche l'heure (ex : "14:32")
                            : $conversation_datetime->format('d/m');  // autre jour → affiche la date courte (ex : "22/05")
                    }

                    // true si c'est la conversation actuellement ouverte (marquée 'active' en CSS)
                    $is_current_conversation = ($conversation_user && (int)$conversation_user['idusers'] === (int)$conversation_item['idusers']); // compare l'ID de l'interlocuteur affiché avec celui de l'item listé

                    // Aperçu du dernier message tronqué à 36 caractères pour tenir dans la sidebar
                    $message_preview_text = mb_strimwidth(htmlspecialchars($conversation_item['last_content']), 0, 36, '…'); // mb_strimwidth gère correctement les caractères multi-octets (accents, emojis)

                    // true si le dernier message a été envoyé par l'utilisateur connecté
                    // → affiche "Vous : " comme préfixe de l'aperçu
                    $last_message_is_mine = (int)$conversation_item['last_sender_id'] === (int)$_SESSION['user']['id']; // compare l'expéditeur du dernier message avec l'ID de session courant
                ?>
                <!-- Lien vers la conversation avec cet interlocuteur -->
                <a href="/sharetime/public/?page=messages&with=<?= $conversation_item['idusers'] ?>"
                   class="msg-conv-item <?= $is_current_conversation ? 'active' : '' ?>"> <!-- classe 'active' si c'est la conversation actuellement ouverte -->
                    <!-- Avatar : photo uploadée ou initiale sur fond gradient -->
                    <div class="msg-conv-avatar">
                        <?php if (!empty($conversation_item['photo_profil'])): // l'utilisateur a une photo de profil ?>
                            <img src="/sharetime/public/uploads/profils/<?= htmlspecialchars($conversation_item['photo_profil']) ?>" alt="">
                        <?php else: // pas de photo : affiche l'initiale du prénom ?>
                            <?= $contact_name_initial ?>
                        <?php endif; ?>
                    </div>
                    <div class="msg-conv-info">
                        <div class="msg-conv-name"><?= $contact_display_name ?></div>
                        <!-- Aperçu : préfixé "Vous : " si le dernier message vient de l'utilisateur connecté -->
                        <div class="msg-conv-preview"><?= $last_message_is_mine ? 'Vous : ' : '' ?><?= $message_preview_text ?></div>
                    </div>
                    <div class="msg-conv-meta">
                        <span class="msg-conv-time"><?= $last_message_time ?></span>
                        <!-- Badge non-lus : masqué pour la conversation active (déjà en train de lire) -->
                        <?php if ($conversation_item['unread_count'] > 0 && !$is_current_conversation): // badge visible si des messages non lus existent et que la conversation n'est pas déjà ouverte ?>
                            <!-- min(99,...) pour éviter un badge ">99" trop large graphiquement -->
                            <span class="msg-unread-badge"><?= min(99, $conversation_item['unread_count']) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; // fin de la boucle sur les conversations ?>
            </div>
        </div>

        <!-- ── ZONE PRINCIPALE ────────────────────────────────────────────── -->
        <div class="msg-main">
            <?php if ($conversation_user): // une conversation est sélectionnée : affiche les messages ?>

                <!-- En-tête de la conversation : avatar + nom + pseudo de l'interlocuteur -->
                <div class="msg-conv-header">
                    <!-- Avatar réduit dans l'en-tête (36px vs 42px dans la sidebar) -->
                    <div class="msg-conv-avatar" style="width:36px;height:36px;font-size:0.85rem;">
                        <?php if (!empty($conversation_user['photo_profil'])): // l'interlocuteur a une photo ?>
                            <img src="/sharetime/public/uploads/profils/<?= htmlspecialchars($conversation_user['photo_profil']) ?>" alt="">
                        <?php else: // pas de photo : affiche l'initiale du prénom ?>
                            <?= strtoupper(mb_substr($conversation_user['prenom'], 0, 1)) ?> <!-- première lettre du prénom en majuscule -->
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;">
                        <!-- Nom cliquable vers le profil public de l'interlocuteur -->
                        <a href="/sharetime/public/?page=profil&id=<?= $conversation_user['idusers'] ?>"
                           style="font-weight:700; color:var(--navy); text-decoration:none; font-size:0.95rem;">
                            <?= htmlspecialchars($conversation_user['prenom'] . ' ' . $conversation_user['nom']) ?> <!-- concatène prénom et nom, protégés contre le XSS -->
                        </a>
                        <?php if (!empty($conversation_user['pseudo'])): // affiche le pseudo seulement s'il est renseigné ?>
                            <div style="font-size:0.78rem; color:var(--gray-400);">@<?= htmlspecialchars($conversation_user['pseudo']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── BULLES DE MESSAGES ───────────────────────────────────────
                     Chaque message est 'sent' (envoyé) ou 'received' (reçu).
                     Un séparateur de date apparaît quand le jour change entre deux messages
                     consécutifs (mémorisé dans $previous_message_day). -->
                <div class="msg-body" id="msgBody">
                    <?php if (empty($conversation_messages)): // aucun message échangé dans cette conversation ?>
                        <!-- État vide : invite à écrire le premier message -->
                        <div style="flex:1; display:flex; align-items:center; justify-content:center; color:var(--gray-400); font-size:0.9rem;">
                            Envoyez votre premier message !
                        </div>
                    <?php endif; ?>
                    <?php
                    // Mémorise le dernier jour affiché pour n'insérer le séparateur qu'aux changements
                    $previous_message_day = null; // initialisé à null : aucun jour affiché au départ
                    foreach ($conversation_messages as $message_item): // itère sur chaque message du fil
                        // true si ce message a été envoyé par l'utilisateur connecté (à droite)
                        $is_outgoing_message = (int)$message_item['sender_id'] === (int)$_SESSION['user']['id']; // compare l'expéditeur du message avec l'utilisateur de session
                        $message_datetime    = new DateTime($message_item['created_at']); // convertit l'horodatage SQL en objet DateTime
                        $message_day_label   = $message_datetime->format('d/m/Y');  // utilisé pour le séparateur de date (ex : "22/05/2025")
                        $message_time_label  = $message_datetime->format('H:i');    // affiché sous chaque bulle (ex : "14:32")
                    ?>
                    <!-- Séparateur de date : inséré seulement quand le jour change -->
                    <?php if ($message_day_label !== $previous_message_day): $previous_message_day = $message_day_label; // met à jour le jour mémorisé pour le prochain tour ?>
                        <div style="text-align:center; margin:8px 0;">
                            <span style="background:var(--gray-100); color:var(--gray-500); font-size:0.73rem;
                                         padding:3px 12px; border-radius:99px;"><?= $message_day_label ?></span>
                        </div>
                    <?php endif; ?>
                    <!-- Ligne de bulle : 'sent' pour envoyé (droite), 'received' pour reçu (gauche) -->
                    <div class="msg-bubble-row <?= $is_outgoing_message ? 'sent' : 'received' ?>"> <!-- la classe détermine l'alignement horizontal de la bulle -->
                        <!-- Mini avatar de l'interlocuteur uniquement pour les messages reçus -->
                        <?php if (!$is_outgoing_message): // n'affiche l'avatar qu'à côté des messages reçus ?>
                            <div class="msg-conv-avatar" style="width:28px;height:28px;font-size:0.7rem;flex-shrink:0;">
                                <?php if (!empty($conversation_user['photo_profil'])): // photo disponible ?>
                                    <img src="/sharetime/public/uploads/profils/<?= htmlspecialchars($conversation_user['photo_profil']) ?>" alt="">
                                <?php else: // fallback sur l'initiale ?>
                                    <?= strtoupper(mb_substr($conversation_user['prenom'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="msg-bubble <?= $is_outgoing_message ? 'sent' : 'received' ?>"> <!-- classe CSS qui colore la bulle (navy pour envoyé, gris pour reçu) -->
                                <!-- nl2br préserve les sauts de ligne multi-lignes dans les messages -->
                                <?= nl2br(htmlspecialchars($message_item['content'])) ?> <!-- échappe le HTML puis convertit les \n en <br> pour l'affichage -->
                            </div>
                            <!-- Heure du message : alignée à droite pour les envoyés, à gauche pour les reçus -->
                            <div style="text-align:<?= $is_outgoing_message ? 'right' : 'left' ?>;"> <!-- aligne l'heure du même côté que la bulle -->
                                <span class="msg-bubble-time"><?= $message_time_label ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; // fin de la boucle sur les messages ?>
                </div>

                <!-- ── BARRE DE SAISIE ───────────────────────────────────────────
                     Comportements :
                     - Entrée → soumet le formulaire (Shift+Entrée = saut de ligne)
                     - data-no-loading : empêche le spinner global du footer de s'activer
                     - disabled à la soumission : évite les doubles envois -->
                <form method="post" action="/sharetime/public/?page=envoyer_message" class="msg-form-bar" id="msgForm"
                      onsubmit="this.querySelector('button[type=submit]').disabled=true;"> <!-- désactive le bouton à la soumission pour éviter un double envoi -->
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"> <!-- jeton anti-CSRF généré côté serveur, vérifié avant traitement -->
                    <!-- receiver_id : ID de l'interlocuteur, validé côté serveur -->
                    <input type="hidden" name="receiver_id" value="<?= (int)$conversation_user['idusers'] ?>"> <!-- cast en entier pour prévenir toute injection -->
                    <textarea name="content" id="msgInput" placeholder="Votre message…" rows="1"
                              maxlength="1000" data-no-loading="1"
                              onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();document.getElementById('msgForm').submit();}"></textarea> <!-- Entrée seule soumet, Shift+Entrée insère un saut de ligne -->
                    <button type="submit" class="btn btn-orange btn-sm" style="flex-shrink:0;">Envoyer</button>
                </form>

            <?php else: // aucune conversation sélectionnée : affiche un écran d'invitation ?>
                <!-- ── État vide : aucune conversation sélectionnée ─────────── -->
                <div class="msg-empty">
                    <span style="font-size:3rem;">✉️</span>
                    <p style="font-weight:600; color:var(--gray-600);">Sélectionnez une conversation</p>
                    <p style="font-size:0.85rem;">ou visitez un profil pour démarrer une discussion.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- ── JAVASCRIPT ──────────────────────────────────────────────────────────────
     Deux comportements automatiques déclenchés au chargement de la page :
     1. Auto-scroll : fait défiler la zone de messages jusqu'au bas pour voir
        les plus récents sans scroll manuel (évite de partir tout en haut).
     2. Auto-resize du textarea : la zone de saisie grandit avec le contenu
        jusqu'à max-height:120px défini dans le CSS, puis scroll interne. -->
<script>
(function() { // IIFE : isole les variables pour ne pas polluer le scope global
    // Scroll automatique vers le dernier message dès le chargement de la page
    var messages_body_el = document.getElementById('msgBody'); // récupère la zone de défilement des bulles
    if (messages_body_el) messages_body_el.scrollTop = messages_body_el.scrollHeight; // positionne le scroll tout en bas (scrollHeight = hauteur totale du contenu)

    // Redimensionnement automatique du textarea au fur et à mesure de la saisie
    var message_textarea_el = document.getElementById('msgInput'); // récupère le champ de saisie du message
    if (message_textarea_el) { // vérifie que l'élément existe (absent si aucune conversation ouverte)
        message_textarea_el.addEventListener('input', function() { // déclenché à chaque frappe dans le textarea
            this.style.height = 'auto';                                   // reset avant recalcul pour obtenir la vraie hauteur du contenu
            this.style.height = Math.min(this.scrollHeight, 120) + 'px'; // applique la hauteur calculée, limitée à 120px max
        });
    }
})();
</script>
