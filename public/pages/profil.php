<?php
/**
 * public/pages/profil.php — Page de profil utilisateur
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $profile            : données de l'utilisateur dont on affiche le profil (ou null si introuvable)
 *   $user_activities    : activités créées par cet utilisateur (visibles par tous)
 *   $user_registrations : activités auxquelles l'utilisateur est inscrit/en attente
 *                         (uniquement si $is_own — données sensibles, non visibles par autrui)
 *   $follower_count     : nombre d'abonnés de ce profil
 *   $following_count    : nombre d'abonnements de ce profil
 *   $is_following       : bool — l'utilisateur connecté suit-il actuellement ce profil ?
 *
 * Deux modes d'affichage :
 *   - Profil propre ($is_own = true)  : section "Mes participations" visible, bouton "Modifier"
 *   - Profil d'un tiers ($is_own = false) : participations masquées, boutons "Suivre"/"Message"/"Signaler"
 *
 * Si $profile est null (ID invalide ou utilisateur inexistant), un message d'erreur est affiché.
 */

// ── CAS : PROFIL INTROUVABLE ──────────────────────────────────────────────────
// L'ID passé en GET n'existe pas ou a été supprimé → message d'erreur minimaliste
if (!$profile): ?> <!-- Vérifie si $profile est null ou false (utilisateur introuvable en BDD) -->

<main class="container" style="padding:80px 0; text-align:center;"> <!-- Conteneur centré avec grand padding vertical pour la page d'erreur -->
    <p style="font-size:2rem; margin-bottom:16px;">😕</p> <!-- Emoji d'expression triste pour signaler visuellement l'erreur -->
    <p style="font-size:1.2rem; color:var(--gray-600);">Profil introuvable.</p> <!-- Message d'erreur textuel indiquant que le profil n'existe pas -->
</main>

<?php else: // Le profil existe : on passe en mode affichage normal
    // true si l'utilisateur connecté consulte son propre profil
    // (comparaison d'entiers pour éviter les problèmes de type PHP)
    $is_own = isset($_SESSION['user']) && (int)$_SESSION['user']['id'] === (int)$profile['idusers']; // Vérifie si l'utilisateur connecté est bien le propriétaire de ce profil
?>

<main class="container" style="padding:40px 0; max-width:800px; margin:auto;"> <!-- Conteneur principal centré, largeur limitée à 800px -->

    <!-- ── BANDEAU VÉRIFICATION EMAIL ─────────────────────────────────────────────
         Affiché uniquement sur son propre profil ($is_own) quand l'email n'a pas encore été
         confirmé. Certaines fonctionnalités (ex : création d'activité) peuvent être bloquées
         tant que l'email n'est pas vérifié, d'où l'importance d'inciter à l'action. -->
    <?php if ($is_own && empty($profile['email_verified'])): ?> <!-- Affiche l'alerte seulement sur son propre profil et seulement si l'email n'est pas vérifié -->
    <div style="background:#FEF3E2; border:1.5px solid rgba(232,129,26,0.4); border-radius:10px;
                padding:14px 20px; margin-bottom:20px; display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
        <span style="font-size:1.3rem;">⚠️</span> <!-- Icône d'avertissement pour attirer l'attention sur la non-vérification -->
        <div style="flex:1;"> <!-- Bloc de texte extensible qui prend l'espace restant -->
            <p style="margin:0; font-weight:600; color:#92400E; font-size:0.9rem;">Votre adresse email n'est pas encore vérifiée.</p> <!-- Titre de l'alerte en gras couleur ambre foncé -->
            <p style="margin:4px 0 0; color:#B45309; font-size:0.82rem;">Certaines fonctionnalités peuvent être limitées.</p> <!-- Sous-texte expliquant les conséquences de la non-vérification -->
        </div>
        <!-- Bouton de renvoi : POST vers handlers/auth.php via page=renvoyer_verification -->
        <form method="post" action="/sharetime/public/?page=renvoyer_verification" style="margin:0;"> <!-- Formulaire POST pour déclencher le renvoi de l'email de vérification -->
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"> <!-- Jeton CSRF pour sécuriser le formulaire contre les attaques cross-site -->
            <button type="submit" class="btn btn-sm btn-orange">Renvoyer l'email</button> <!-- Bouton d'action pour renvoyer l'email de confirmation -->
        </form>
    </div>
    <?php endif; ?>

    <!-- ── EN-TÊTE DU PROFIL ───────────────────────────────────────────────────── -->
    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px; margin-bottom:24px;"> <!-- Carte blanche avec bordure et coins arrondis pour l'en-tête du profil -->
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;"> <!-- Flexbox avec espace-between : avatar+info à gauche, actions à droite -->

            <!-- Zone gauche : avatar + nom + pseudo + ville + note moyenne -->
            <div style="display:flex; align-items:center; gap:20px;"> <!-- Disposition flex horizontale avec 20px d'écart entre l'avatar et les infos -->
                <!-- Avatar : photo uploadée si disponible, sinon initiale sur fond gradient navy -->
                <?php if (!empty($profile['photo_profil'])): ?> <!-- Vérifie si l'utilisateur a uploadé une photo de profil -->
                <img src="/sharetime/public/uploads/profils/<?= htmlspecialchars($profile['photo_profil']) ?>" <!-- Charge la photo de profil depuis le dossier uploads/profils -->
                     style="width:72px; height:72px; border-radius:50%; object-fit:cover; flex-shrink:0; border:2px solid var(--gray-200);"> <!-- Avatar circulaire de 72px, recadré pour remplir sans déformer -->
                <?php else: ?>
                <!-- Initiale du prénom centrée dans un cercle gradient navy (fallback si pas de photo) -->
                <div style="width:72px; height:72px; border-radius:50%;
                            background:linear-gradient(135deg, var(--navy), var(--navy-light));
                            display:flex; align-items:center; justify-content:center;
                            color:white; font-size:1.8rem; font-weight:700; flex-shrink:0;"> <!-- Cercle gradient navy de 72px pour afficher l'initiale à la place d'une photo -->
                    <?= strtoupper(mb_substr($profile['prenom'], 0, 1)) ?> <!-- Extrait et met en majuscule la première lettre du prénom (compatible multi-octets) -->
                </div>
                <?php endif; ?>

                <div>
                    <h1 style="margin:0; color:var(--navy); font-size:1.5rem;">
                        <?= htmlspecialchars($profile['prenom'] . ' ' . $profile['nom']) ?> <!-- Affiche le prénom et le nom en échappant les caractères HTML spéciaux -->
                    </h1>
                    <!-- Pseudo affiché en orange uniquement s'il a été défini (champ facultatif) -->
                    <?php if (!empty($profile['pseudo'])): ?> <!-- N'affiche le pseudo que s'il est renseigné (champ optionnel) -->
                        <p style="color:var(--orange); font-weight:600; margin:2px 0;">
                            @<?= htmlspecialchars($profile['pseudo']) ?> <!-- Affiche le pseudo préfixé d'un "@" en couleur orange -->
                        </p>
                    <?php endif; ?>
                    <!-- Ville affichée si renseignée dans le profil -->
                    <?php if (!empty($profile['ville'])): ?> <!-- N'affiche la ville que si elle est renseignée dans le profil -->
                        <p style="color:var(--gray-500); font-size:0.9rem; margin:4px 0;">
                            📍 <?= htmlspecialchars($profile['ville']) ?> <!-- Affiche la ville précédée d'une épingle de localisation -->
                        </p>
                    <?php endif; ?>
                    <!-- Note moyenne en tant qu'organisateur : masquée si 0 (aucune note reçue) -->
                    <?php if (!empty($profile['note_moyenne'])): ?> <!-- N'affiche la note que si elle est non nulle (au moins une évaluation reçue) -->
                        <p style="font-size:0.9rem; margin:4px 0; color:var(--gray-700);">
                            <span style="color:var(--orange);">★</span> <!-- Étoile pleine en orange pour symboliser la notation -->
                            <strong><?= number_format($profile['note_moyenne'], 1) ?></strong> <!-- Formate la note moyenne avec 1 décimale (ex : 4.3) -->
                            <span style="color:var(--gray-400); font-size:0.82rem;">en tant qu'organisateur</span> <!-- Précision contextuelle : la note concerne le rôle d'organisateur -->
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Zone droite : compteurs follow + boutons d'action contextuels -->
            <div style="display:flex; flex-direction:column; align-items:flex-end; gap:10px;"> <!-- Colonne flex alignée à droite pour les stats et boutons -->
                <!-- Compteurs d'abonnés et d'abonnements avec accord au pluriel -->
                <div style="display:flex; gap:16px; font-size:0.85rem; color:var(--gray-600);">
                    <span><strong style="color:var(--navy);"><?= $follower_count ?></strong> abonné<?= $follower_count > 1 ? 's' : '' ?></span> <!-- Nombre d'abonnés avec accord "abonné/abonnés" selon le count -->
                    <span><strong style="color:var(--navy);"><?= $following_count ?></strong> abonnement<?= $following_count > 1 ? 's' : '' ?></span> <!-- Nombre d'abonnements avec accord "abonnement/abonnements" -->
                </div>

                <?php if ($is_own): ?> <!-- Branche propre profil : bouton d'édition -->
                    <!-- Profil propre : bouton de modification vers profil_edit -->
                    <a href="/sharetime/public/?page=profil_edit" class="btn btn-outline-navy btn-sm">✏️ Modifier le profil</a> <!-- Lien vers la page d'édition du profil de l'utilisateur connecté -->
                <?php elseif (isset($_SESSION['user'])): ?> <!-- Branche autre profil + utilisateur connecté : actions sociales -->
                    <!-- Profil d'un autre utilisateur connecté : Suivre / Message / Signaler -->
                    <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end;"> <!-- Groupe de boutons flexibles, alignés à droite, avec retour à la ligne si nécessaire -->
                        <!-- Formulaire Follow/Unfollow : même page=suivre pour les deux actions -->
                        <form method="post" action="/sharetime/public/?page=suivre" style="margin:0;"> <!-- Formulaire POST pour suivre ou ne plus suivre cet utilisateur -->
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"> <!-- Jeton CSRF pour sécuriser l'action de suivi -->
                            <input type="hidden" name="user_id" value="<?= $profile['idusers'] ?>"> <!-- ID de l'utilisateur cible transmis en hidden au handler -->
                            <!-- Le libellé et le style changent selon si on suit déjà ce profil ($is_following) -->
                            <button type="submit" class="btn btn-sm <?= $is_following ? 'btn-outline-navy' : 'btn-orange' ?>"> <!-- Style outline si déjà abonné, orange si pas encore abonné -->
                                <?= $is_following ? '✓ Abonné(e)' : '+ Suivre' ?> <!-- Libellé dynamique : coche verte si abonné, "+" si pas encore suivi -->
                            </button>
                        </form>
                        <!-- Lien vers la messagerie avec cet utilisateur pré-sélectionné -->
                        <a href="/sharetime/public/?page=messages&with=<?= $profile['idusers'] ?>" <!-- Lien vers la page messages avec l'ID du destinataire pré-renseigné dans l'URL -->
                           class="btn btn-sm btn-outline-navy">✉️ Message</a>
                        <!-- Bouton d'ouverture de la modal de signalement (JS inline) -->
                        <button type="button" onclick="document.getElementById('modal-report').style.display='flex'" <!-- Rend la modal de signalement visible en JS au clic sur ce bouton -->
                            style="padding:6px 14px;border-radius:8px;border:1.5px solid #FECACA;background:white;color:#DC2626;font-size:0.82rem;font-weight:600;cursor:pointer;">
                            🚩 Signaler <!-- Texte du bouton avec drapeau rouge pour indiquer l'action de signalement -->
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Biographie : séparée par une bordure, affichée seulement si renseignée -->
        <!-- nl2br préserve les sauts de ligne entrés par l'utilisateur dans le textarea -->
        <?php if (!empty($profile['bio'])): ?> <!-- N'affiche la biographie que si l'utilisateur en a rédigé une -->
            <p style="color:var(--gray-700); margin-top:20px; line-height:1.75;
                      border-top:1px solid var(--gray-100); padding-top:20px;">
                <?= nl2br(htmlspecialchars($profile['bio'])) ?> <!-- Échappe le HTML de la bio et convertit les retours à la ligne en balises <br> -->
            </p>
        <?php endif; ?>
    </div>

    <!-- ── ACTIVITÉS ORGANISÉES ────────────────────────────────────────────────── -->
    <!-- Cette section est publique : visible par tous les visiteurs du profil -->
    <div style="margin-bottom:28px;">
        <h2 style="color:var(--navy); margin-bottom:16px; font-size:1.2rem;">
            Activités organisées (<?= count($user_activities) ?>) <!-- Affiche le titre de section avec le nombre total d'activités créées -->
        </h2>
        <?php if (empty($user_activities)): ?> <!-- Vérifie si l'utilisateur n'a créé aucune activité -->
            <!-- Texte différent selon si on consulte son propre profil ou celui d'un autre -->
            <p style="color:var(--gray-500);">
                <?php if ($is_own): ?> <!-- Message personnalisé avec lien de création si c'est son propre profil -->
                    Vous n'avez pas encore créé d'activité.
                    <a href="/sharetime/public/?page=creer" style="color:var(--orange); font-weight:600;">Créer une activité</a> <!-- Lien d'appel à l'action vers le formulaire de création d'activité -->
                <?php else: ?>
                    Cet utilisateur n'a pas encore créé d'activité. <!-- Message neutre affiché quand on consulte le profil d'un autre utilisateur sans activité -->
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:10px;"> <!-- Colonne de cartes d'activités avec 10px d'écart entre chaque -->
                <?php foreach ($user_activities as $user_activity_item): // Itère sur chaque activité créée par l'utilisateur
                    // Objet DateTime pour formater la date de début de l'activité
                    $start_datetime = new DateTime($user_activity_item['start_time']); // Crée un objet DateTime à partir de la date MySQL pour faciliter le formatage

                    // Booléen pour une éventuelle logique d'affichage conditionnelle
                    $is_activity_active = in_array($user_activity_item['status'], ['active', 'en_cours']); // Vrai si l'activité est en cours ou à venir (statut actif)

                    // Table de correspondance statut → libellé affiché dans le badge
                    $status_label_map = ['active' => 'À venir', 'en_cours' => 'En cours', 'terminee' => 'Terminée', 'annulee' => 'Annulée']; // Tableau associatif qui traduit les statuts BDD en libellés lisibles

                    // Libellé avec fallback ucfirst pour les statuts inconnus
                    $activity_status_label = $status_label_map[$user_activity_item['status']] ?? ucfirst($user_activity_item['status']); // Récupère le libellé ou met la première lettre en majuscule si statut inconnu

                    // Couleurs du badge statut : fond + texte selon l'état de l'activité
                    $status_badge_bg_map    = ['active'=>'#D1FAE5','en_cours'=>'#FEF3C7','annulee'=>'var(--gray-100)','terminee'=>'var(--gray-100)']; // Couleurs de fond du badge : vert pour active, jaune pour en cours, gris pour fini/annulé
                    $status_badge_color_map = ['active'=>'#065F46','en_cours'=>'#92400E','annulee'=>'var(--gray-500)','terminee'=>'var(--gray-500)']; // Couleurs du texte du badge : vert foncé, ambre, ou gris selon le statut
                    $status_badge_bg        = $status_badge_bg_map[$user_activity_item['status']]    ?? 'var(--gray-100)'; // Sélectionne la couleur de fond du badge ou utilise le gris par défaut
                    $status_badge_color     = $status_badge_color_map[$user_activity_item['status']] ?? 'var(--gray-500)'; // Sélectionne la couleur de texte du badge ou utilise le gris par défaut
                ?>
                <!-- Ligne d'activité : cliquable vers la page de détail, hover avec bordure navy -->
                <a href="/sharetime/public/?page=detail&id=<?= $user_activity_item['idactivities'] ?>" <!-- Lien cliquable vers la page de détail de l'activité -->
                   style="background:white; border:1.5px solid var(--gray-200); border-radius:10px;
                          padding:16px; display:flex; justify-content:space-between; align-items:center;
                          gap:12px; text-decoration:none; color:inherit;
                          transition:box-shadow 0.2s, border-color 0.2s;" <!-- Transition CSS douce pour les effets de survol -->
                   onmouseover="this.style.boxShadow='var(--shadow-md)';this.style.borderColor='var(--navy)'" <!-- Au survol : ajoute une ombre portée et change la bordure en navy -->
                   onmouseout="this.style.boxShadow='none';this.style.borderColor='var(--gray-200)'"> <!-- Quand la souris quitte : remet les styles par défaut -->
                    <div>
                        <p style="font-weight:600; color:var(--gray-900); margin-bottom:4px;">
                            <?= htmlspecialchars($user_activity_item['title']) ?> <!-- Affiche le titre de l'activité en échappant les caractères HTML -->
                        </p>
                        <!-- Métadonnées compactes : date, ville, ratio participants -->
                        <p style="font-size:0.85rem; color:var(--gray-500);">
                            📅 <?= $start_datetime->format('d/m/Y') ?> &nbsp;·&nbsp; <!-- Formate la date au format français JJ/MM/AAAA -->
                            📍 <?= htmlspecialchars($user_activity_item['city']) ?> &nbsp;·&nbsp; <!-- Affiche la ville de l'activité -->
                            👥 <?= $user_activity_item['nb_inscrits'] ?>/<?= $user_activity_item['max_participants'] ?> <!-- Ratio inscrits/capacité max pour visualiser la disponibilité -->
                        </p>
                    </div>
                    <!-- Badge statut coloré positionné à droite de la ligne -->
                    <span style="font-size:0.78rem; padding:4px 12px; border-radius:99px; font-weight:600; white-space:nowrap;
                                 background:<?= $status_badge_bg ?>; color:<?= $status_badge_color ?>;"> <!-- Badge pill avec couleurs dynamiques selon le statut de l'activité -->
                        <?= $activity_status_label ?> <!-- Affiche le libellé du statut traduit (À venir, En cours, Terminée, Annulée) -->
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── MES PARTICIPATIONS (propre profil uniquement) ────────────────────────
         Section réservée à $is_own pour protéger la vie privée : les activités
         auxquelles un utilisateur est inscrit ne sont pas des informations publiques.
         Un tiers ne peut pas savoir à quelles activités il a adhéré. -->
    <?php if ($is_own): ?> <!-- N'affiche les participations que si l'utilisateur consulte son propre profil -->
    <div>
        <h2 style="color:var(--navy); margin-bottom:16px; font-size:1.2rem;">
            Mes participations (<?= count($user_registrations) ?>) <!-- Titre de section avec le nombre total de participations (inscrits + en attente) -->
        </h2>
        <?php if (empty($user_registrations)): ?> <!-- Vérifie si l'utilisateur n'est inscrit à aucune activité -->
            <p style="color:var(--gray-500);">
                Vous n'êtes inscrit(e) à aucune activité.
                <a href="/sharetime/public/?page=activites" style="color:var(--orange); font-weight:600;">Explorer les activités</a> <!-- Lien d'appel à l'action vers la liste des activités disponibles -->
            </p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:10px;"> <!-- Colonne de cartes de participation avec 10px d'espacement -->
                <?php foreach ($user_registrations as $user_participation_item): // Itère sur chaque activité à laquelle l'utilisateur est inscrit
                    // Objet DateTime pour afficher la date et l'heure de début de l'activité
                    $participation_start_datetime = new DateTime($user_participation_item['start_time']); // Crée un objet DateTime pour formater la date de début de la participation

                    // Statut d'inscription depuis la table registrations (inscrit ou en_attente)
                    $registration_status = $user_participation_item['reg_status'] ?? 'inscrit'; // Récupère le statut d'inscription, "inscrit" par défaut si le champ est absent

                    // Couleurs du badge selon le statut d'inscription :
                    // orange pâle pour la liste d'attente, vert pâle pour les inscrits confirmés
                    $registration_badge_bg    = $registration_status === 'en_attente' ? '#FEF3E2' : '#D1FAE5'; // Fond orange pâle pour l'attente, fond vert pâle pour l'inscription confirmée
                    $registration_badge_color = $registration_status === 'en_attente' ? '#92400E' : '#065F46'; // Texte ambre foncé pour l'attente, texte vert foncé pour l'inscription confirmée
                    $registration_status_label = $registration_status === 'en_attente' ? 'En attente' : 'Inscrit(e)'; // Libellé lisible du statut : "En attente" ou "Inscrit(e)"
                ?>
                <!-- Lien vers la page de détail de l'activité, même style hover que les activités organisées -->
                <a href="/sharetime/public/?page=detail&id=<?= $user_participation_item['idactivities'] ?>" <!-- Lien vers la page de détail de l'activité concernée par la participation -->
                   style="background:white; border:1.5px solid var(--gray-200); border-radius:10px;
                          padding:16px; display:flex; justify-content:space-between; align-items:center;
                          gap:12px; text-decoration:none; color:inherit; transition:box-shadow 0.2s, border-color 0.2s;" <!-- Même style de transition que les activités organisées pour la cohérence visuelle -->
                   onmouseover="this.style.boxShadow='var(--shadow-md)';this.style.borderColor='var(--navy)'" <!-- Effet hover : ombre et bordure navy au survol -->
                   onmouseout="this.style.boxShadow='none';this.style.borderColor='var(--gray-200)'"> <!-- Remet les styles initiaux quand la souris quitte la carte -->
                    <div>
                        <p style="font-weight:600; color:var(--gray-900); margin-bottom:4px;">
                            <?= htmlspecialchars($user_participation_item['title']) ?> <!-- Affiche le titre de l'activité en sécurisant les caractères HTML -->
                        </p>
                        <!-- Date avec heure incluse pour les participations (plus détaillé que la liste organisées) -->
                        <p style="font-size:0.85rem; color:var(--gray-500);">
                            📅 <?= $participation_start_datetime->format('d/m/Y à H:i') ?> &nbsp;·&nbsp; 📍 <?= htmlspecialchars($user_participation_item['city']) ?> <!-- Formate la date avec l'heure (ex : 15/06/2025 à 14:00) et affiche la ville -->
                        </p>
                    </div>
                    <!-- Badge d'état de l'inscription : vert = confirmé, orange = liste d'attente -->
                    <span style="font-size:0.78rem; background:<?= $registration_badge_bg ?>; color:<?= $registration_badge_color ?>;
                                 padding:4px 12px; border-radius:99px; font-weight:600; white-space:nowrap;"> <!-- Badge pill coloré selon le statut de la participation -->
                        <?= $registration_status_label ?> <!-- Affiche "Inscrit(e)" ou "En attente" selon le statut de l'inscription -->
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</main>

<?php if (isset($_SESSION['user']) && !$is_own && $profile): ?> <!-- Affiche la modal de signalement uniquement si l'utilisateur est connecté et consulte un profil tiers -->
<!-- ── MODAL DE SIGNALEMENT ──────────────────────────────────────────────────────
     Accessible uniquement pour un utilisateur connecté consultant le profil d'un tiers.
     Le clic sur le fond de la modal (event.target === backdrop) ferme la modal.
     Le motif "Autre" révèle un textarea supplémentaire via onchange JS. -->
<div id="modal-report"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px;" <!-- Overlay plein écran semi-transparent, caché par défaut, visible via flex quand ouvert -->
     onclick="if(event.target===this)this.style.display='none'"> <!-- Ferme la modal si l'utilisateur clique en dehors du panneau central (sur l'overlay) -->
    <div style="background:white;border-radius:16px;padding:32px;max-width:460px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.2);"> <!-- Panneau blanc centré avec ombre portée pour le contenu de la modal -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="color:var(--navy);margin:0;font-size:1.15rem;">🚩 Signaler cet utilisateur</h2> <!-- Titre de la modal avec drapeau rouge -->
            <!-- Bouton × de fermeture -->
            <button onclick="document.getElementById('modal-report').style.display='none'" <!-- Cache la modal en repassant display à 'none' -->
                style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--gray-400);line-height:1;">×</button> <!-- Croix "×" stylisée pour fermer la modal -->
        </div>
        <p style="color:var(--gray-500);font-size:0.88rem;margin-bottom:20px;line-height:1.6;">
            Vous signalez le profil de <strong><?= htmlspecialchars($profile['prenom'] . ' ' . $profile['nom']) ?></strong>. <!-- Identifie explicitement la personne signalée avec son nom complet -->
            Votre signalement sera examiné par l'équipe. <!-- Rassure l'utilisateur que le signalement sera traité -->
        </p>
        <form method="POST" action="/sharetime/public/?page=signaler" <!-- Formulaire POST vers le handler de signalement -->
              style="display:flex;flex-direction:column;gap:14px;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"> <!-- Jeton CSRF pour protéger le formulaire de signalement -->
            <!-- ID de l'utilisateur signalé passé en hidden (validé côté serveur) -->
            <input type="hidden" name="signale_id" value="<?= (int)$profile['idusers'] ?>"> <!-- ID de l'utilisateur signalé casté en entier pour sécuriser la valeur -->
            <!-- Redirection après soumission : retour sur le profil signalé -->
            <input type="hidden" name="redirect" value="/sharetime/public/?page=profil&id=<?= (int)$profile['idusers'] ?>"> <!-- URL de redirection après le traitement du signalement par le handler -->
            <div>
                <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:8px;font-size:0.9rem;">Motif du signalement *</label> <!-- Label obligatoire pour le sélecteur de motif -->
                <!-- onchange : affiche le textarea libre uniquement si l'option "Autre" est choisie -->
                <select name="motif" required <!-- Sélecteur obligatoire pour choisir le motif du signalement -->
                    style="width:100%;padding:12px 14px;border:1.5px solid var(--gray-300);border-radius:10px;font-size:0.9rem;font-family:inherit;background:white;box-sizing:border-box;"
                    onchange="document.getElementById('motif-autre').style.display=this.value==='Autre'?'block':'none'"> <!-- Révèle le textarea libre en JS si l'option "Autre" est sélectionnée -->
                    <option value="">— Choisissez un motif —</option> <!-- Option vide par défaut pour forcer un choix actif -->
                    <option>Comportement abusif ou harcelant</option> <!-- Motif prédéfini pour les comportements abusifs -->
                    <option>Faux profil / usurpation d'identité</option> <!-- Motif pour les faux comptes ou l'usurpation d'identité -->
                    <option>Contenu inapproprié</option> <!-- Motif pour les contenus offensants ou non conformes -->
                    <option>Spam ou arnaque</option> <!-- Motif pour les activités de spam ou d'arnaque -->
                    <option>Autre</option> <!-- Motif libre qui révèle un textarea pour préciser -->
                </select>
                <!-- Textarea libre pour "Autre" : caché par défaut, révélé par JS -->
                <textarea id="motif-autre" name="motif_detail" rows="3" placeholder="Précisez..." <!-- Champ de texte libre pour décrire un motif non listé, caché par défaut -->
                    style="display:none;width:100%;margin-top:8px;padding:10px 14px;border:1.5px solid var(--gray-300);border-radius:10px;font-size:0.9rem;font-family:inherit;resize:vertical;box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:4px;"> <!-- Boutons alignés à droite avec 10px d'écart entre eux -->
                <button type="button" onclick="document.getElementById('modal-report').style.display='none'" <!-- Bouton Annuler : ferme la modal sans soumettre le formulaire -->
                    style="padding:10px 20px;border:1.5px solid var(--gray-300);border-radius:10px;background:white;font-size:0.9rem;cursor:pointer;">
                    Annuler <!-- Texte du bouton de fermeture sans action -->
                </button>
                <button type="submit" <!-- Bouton de soumission du formulaire de signalement -->
                    style="padding:10px 20px;background:#DC2626;color:white;border:none;border-radius:10px;font-size:0.9rem;font-weight:600;cursor:pointer;">
                    Envoyer le signalement <!-- Texte du bouton d'envoi en rouge pour souligner l'action irréversible -->
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Avant soumission : si "Autre" est sélectionné, copie le texte libre dans la valeur du select
// pour que le handler reçoive un motif complet dans name="motif" sans champ supplémentaire
document.querySelector('#modal-report form').addEventListener('submit', function(reportSubmitEvent) { // Intercepte la soumission du formulaire avant l'envoi
    var motif_select_el  = this.querySelector('select[name="motif"]'); // Référence au sélecteur de motif dans la modal
    var motif_detail_el  = document.getElementById('motif-autre'); // Référence au textarea de description libre du motif "Autre"
    if (motif_select_el.value === 'Autre') { // Vérifie si l'option "Autre" a été choisie dans le sélecteur
        // Empêche la soumission si le textarea libre est vide
        if (!motif_detail_el.value.trim()) { reportSubmitEvent.preventDefault(); motif_detail_el.focus(); return; } // Bloque l'envoi et met le focus sur le textarea si aucun texte n'est saisi
        // Préfixe "Autre : " pour que les logs distinguent le motif standardisé du texte libre
        motif_select_el.value = 'Autre : ' + motif_detail_el.value.trim(); // Remplace la valeur "Autre" par le texte complet préfixé pour le handler
    }
});
</script>
<?php endif; ?>

<?php endif; ?>
