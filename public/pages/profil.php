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
if (!$profile): ?>

<main class="container" style="padding:80px 0; text-align:center;">
    <p style="font-size:2rem; margin-bottom:16px;">😕</p>
    <p style="font-size:1.2rem; color:var(--gray-600);">Profil introuvable.</p>
</main>

<?php else:
    // true si l'utilisateur connecté consulte son propre profil
    // (comparaison d'entiers pour éviter les problèmes de type PHP)
    $is_own = isset($_SESSION['user']) && (int)$_SESSION['user']['id'] === (int)$profile['idusers'];
?>

<main class="container" style="padding:40px 0; max-width:800px; margin:auto;">

    <!-- ── BANDEAU VÉRIFICATION EMAIL ─────────────────────────────────────────────
         Affiché uniquement sur son propre profil ($is_own) quand l'email n'a pas encore été
         confirmé. Certaines fonctionnalités (ex : création d'activité) peuvent être bloquées
         tant que l'email n'est pas vérifié, d'où l'importance d'inciter à l'action. -->
    <?php if ($is_own && empty($profile['email_verified'])): ?>
    <div style="background:#FEF3E2; border:1.5px solid rgba(232,129,26,0.4); border-radius:10px;
                padding:14px 20px; margin-bottom:20px; display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
        <span style="font-size:1.3rem;">⚠️</span>
        <div style="flex:1;">
            <p style="margin:0; font-weight:600; color:#92400E; font-size:0.9rem;">Votre adresse email n'est pas encore vérifiée.</p>
            <p style="margin:4px 0 0; color:#B45309; font-size:0.82rem;">Certaines fonctionnalités peuvent être limitées.</p>
        </div>
        <!-- Bouton de renvoi : POST vers handlers/auth.php via page=renvoyer_verification -->
        <form method="post" action="/sharetime/public/?page=renvoyer_verification" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <button type="submit" class="btn btn-sm btn-orange">Renvoyer l'email</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- ── EN-TÊTE DU PROFIL ───────────────────────────────────────────────────── -->
    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px; margin-bottom:24px;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">

            <!-- Zone gauche : avatar + nom + pseudo + ville + note moyenne -->
            <div style="display:flex; align-items:center; gap:20px;">
                <!-- Avatar : photo uploadée si disponible, sinon initiale sur fond gradient navy -->
                <?php if (!empty($profile['photo_profil'])): ?>
                <img src="/sharetime/public/uploads/profils/<?= htmlspecialchars($profile['photo_profil']) ?>"
                     style="width:72px; height:72px; border-radius:50%; object-fit:cover; flex-shrink:0; border:2px solid var(--gray-200);">
                <?php else: ?>
                <!-- Initiale du prénom centrée dans un cercle gradient navy (fallback si pas de photo) -->
                <div style="width:72px; height:72px; border-radius:50%;
                            background:linear-gradient(135deg, var(--navy), var(--navy-light));
                            display:flex; align-items:center; justify-content:center;
                            color:white; font-size:1.8rem; font-weight:700; flex-shrink:0;">
                    <?= strtoupper(mb_substr($profile['prenom'], 0, 1)) ?>
                </div>
                <?php endif; ?>

                <div>
                    <h1 style="margin:0; color:var(--navy); font-size:1.5rem;">
                        <?= htmlspecialchars($profile['prenom'] . ' ' . $profile['nom']) ?>
                    </h1>
                    <!-- Pseudo affiché en orange uniquement s'il a été défini (champ facultatif) -->
                    <?php if (!empty($profile['pseudo'])): ?>
                        <p style="color:var(--orange); font-weight:600; margin:2px 0;">
                            @<?= htmlspecialchars($profile['pseudo']) ?>
                        </p>
                    <?php endif; ?>
                    <!-- Ville affichée si renseignée dans le profil -->
                    <?php if (!empty($profile['ville'])): ?>
                        <p style="color:var(--gray-500); font-size:0.9rem; margin:4px 0;">
                            📍 <?= htmlspecialchars($profile['ville']) ?>
                        </p>
                    <?php endif; ?>
                    <!-- Note moyenne en tant qu'organisateur : masquée si 0 (aucune note reçue) -->
                    <?php if (!empty($profile['note_moyenne'])): ?>
                        <p style="font-size:0.9rem; margin:4px 0; color:var(--gray-700);">
                            <span style="color:var(--orange);">★</span>
                            <strong><?= number_format($profile['note_moyenne'], 1) ?></strong>
                            <span style="color:var(--gray-400); font-size:0.82rem;">en tant qu'organisateur</span>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Zone droite : compteurs follow + boutons d'action contextuels -->
            <div style="display:flex; flex-direction:column; align-items:flex-end; gap:10px;">
                <!-- Compteurs d'abonnés et d'abonnements avec accord au pluriel -->
                <div style="display:flex; gap:16px; font-size:0.85rem; color:var(--gray-600);">
                    <span><strong style="color:var(--navy);"><?= $follower_count ?></strong> abonné<?= $follower_count > 1 ? 's' : '' ?></span>
                    <span><strong style="color:var(--navy);"><?= $following_count ?></strong> abonnement<?= $following_count > 1 ? 's' : '' ?></span>
                </div>

                <?php if ($is_own): ?>
                    <!-- Profil propre : bouton de modification vers profil_edit -->
                    <a href="/sharetime/public/?page=profil_edit" class="btn btn-outline-navy btn-sm">✏️ Modifier le profil</a>
                <?php elseif (isset($_SESSION['user'])): ?>
                    <!-- Profil d'un autre utilisateur connecté : Suivre / Message / Signaler -->
                    <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end;">
                        <!-- Formulaire Follow/Unfollow : même page=suivre pour les deux actions -->
                        <form method="post" action="/sharetime/public/?page=suivre" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="user_id" value="<?= $profile['idusers'] ?>">
                            <!-- Le libellé et le style changent selon si on suit déjà ce profil ($is_following) -->
                            <button type="submit" class="btn btn-sm <?= $is_following ? 'btn-outline-navy' : 'btn-orange' ?>">
                                <?= $is_following ? '✓ Abonné(e)' : '+ Suivre' ?>
                            </button>
                        </form>
                        <!-- Lien vers la messagerie avec cet utilisateur pré-sélectionné -->
                        <a href="/sharetime/public/?page=messages&with=<?= $profile['idusers'] ?>"
                           class="btn btn-sm btn-outline-navy">✉️ Message</a>
                        <!-- Bouton d'ouverture de la modal de signalement (JS inline) -->
                        <button type="button" onclick="document.getElementById('modal-report').style.display='flex'"
                            style="padding:6px 14px;border-radius:8px;border:1.5px solid #FECACA;background:white;color:#DC2626;font-size:0.82rem;font-weight:600;cursor:pointer;">
                            🚩 Signaler
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Biographie : séparée par une bordure, affichée seulement si renseignée -->
        <!-- nl2br préserve les sauts de ligne entrés par l'utilisateur dans le textarea -->
        <?php if (!empty($profile['bio'])): ?>
            <p style="color:var(--gray-700); margin-top:20px; line-height:1.75;
                      border-top:1px solid var(--gray-100); padding-top:20px;">
                <?= nl2br(htmlspecialchars($profile['bio'])) ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- ── ACTIVITÉS ORGANISÉES ────────────────────────────────────────────────── -->
    <!-- Cette section est publique : visible par tous les visiteurs du profil -->
    <div style="margin-bottom:28px;">
        <h2 style="color:var(--navy); margin-bottom:16px; font-size:1.2rem;">
            Activités organisées (<?= count($user_activities) ?>)
        </h2>
        <?php if (empty($user_activities)): ?>
            <!-- Texte différent selon si on consulte son propre profil ou celui d'un autre -->
            <p style="color:var(--gray-500);">
                <?php if ($is_own): ?>
                    Vous n'avez pas encore créé d'activité.
                    <a href="/sharetime/public/?page=creer" style="color:var(--orange); font-weight:600;">Créer une activité</a>
                <?php else: ?>
                    Cet utilisateur n'a pas encore créé d'activité.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($user_activities as $user_activity_item):
                    // Objet DateTime pour formater la date de début de l'activité
                    $start_datetime = new DateTime($user_activity_item['start_time']);

                    // Booléen pour une éventuelle logique d'affichage conditionnelle
                    $is_activity_active = in_array($user_activity_item['status'], ['active', 'en_cours']);

                    // Table de correspondance statut → libellé affiché dans le badge
                    $status_label_map = ['active' => 'À venir', 'en_cours' => 'En cours', 'terminee' => 'Terminée', 'annulee' => 'Annulée'];
                    // Libellé avec fallback ucfirst pour les statuts inconnus
                    $activity_status_label = $status_label_map[$user_activity_item['status']] ?? ucfirst($user_activity_item['status']);

                    // Couleurs du badge statut : fond + texte selon l'état de l'activité
                    $status_badge_bg_map    = ['active'=>'#D1FAE5','en_cours'=>'#FEF3C7','annulee'=>'var(--gray-100)','terminee'=>'var(--gray-100)'];
                    $status_badge_color_map = ['active'=>'#065F46','en_cours'=>'#92400E','annulee'=>'var(--gray-500)','terminee'=>'var(--gray-500)'];
                    $status_badge_bg        = $status_badge_bg_map[$user_activity_item['status']]    ?? 'var(--gray-100)';
                    $status_badge_color     = $status_badge_color_map[$user_activity_item['status']] ?? 'var(--gray-500)';
                ?>
                <!-- Ligne d'activité : cliquable vers la page de détail, hover avec bordure navy -->
                <a href="/sharetime/public/?page=detail&id=<?= $user_activity_item['idactivities'] ?>"
                   style="background:white; border:1.5px solid var(--gray-200); border-radius:10px;
                          padding:16px; display:flex; justify-content:space-between; align-items:center;
                          gap:12px; text-decoration:none; color:inherit;
                          transition:box-shadow 0.2s, border-color 0.2s;"
                   onmouseover="this.style.boxShadow='var(--shadow-md)';this.style.borderColor='var(--navy)'"
                   onmouseout="this.style.boxShadow='none';this.style.borderColor='var(--gray-200)'">
                    <div>
                        <p style="font-weight:600; color:var(--gray-900); margin-bottom:4px;">
                            <?= htmlspecialchars($user_activity_item['title']) ?>
                        </p>
                        <!-- Métadonnées compactes : date, ville, ratio participants -->
                        <p style="font-size:0.85rem; color:var(--gray-500);">
                            📅 <?= $start_datetime->format('d/m/Y') ?> &nbsp;·&nbsp;
                            📍 <?= htmlspecialchars($user_activity_item['city']) ?> &nbsp;·&nbsp;
                            👥 <?= $user_activity_item['nb_inscrits'] ?>/<?= $user_activity_item['max_participants'] ?>
                        </p>
                    </div>
                    <!-- Badge statut coloré positionné à droite de la ligne -->
                    <span style="font-size:0.78rem; padding:4px 12px; border-radius:99px; font-weight:600; white-space:nowrap;
                                 background:<?= $status_badge_bg ?>; color:<?= $status_badge_color ?>;">
                        <?= $activity_status_label ?>
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
    <?php if ($is_own): ?>
    <div>
        <h2 style="color:var(--navy); margin-bottom:16px; font-size:1.2rem;">
            Mes participations (<?= count($user_registrations) ?>)
        </h2>
        <?php if (empty($user_registrations)): ?>
            <p style="color:var(--gray-500);">
                Vous n'êtes inscrit(e) à aucune activité.
                <a href="/sharetime/public/?page=activites" style="color:var(--orange); font-weight:600;">Explorer les activités</a>
            </p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($user_registrations as $user_participation_item):
                    // Objet DateTime pour afficher la date et l'heure de début de l'activité
                    $participation_start_datetime = new DateTime($user_participation_item['start_time']);

                    // Statut d'inscription depuis la table registrations (inscrit ou en_attente)
                    $registration_status = $user_participation_item['reg_status'] ?? 'inscrit';

                    // Couleurs du badge selon le statut d'inscription :
                    // orange pâle pour la liste d'attente, vert pâle pour les inscrits confirmés
                    $registration_badge_bg    = $registration_status === 'en_attente' ? '#FEF3E2' : '#D1FAE5';
                    $registration_badge_color = $registration_status === 'en_attente' ? '#92400E' : '#065F46';
                    $registration_status_label = $registration_status === 'en_attente' ? 'En attente' : 'Inscrit(e)';
                ?>
                <!-- Lien vers la page de détail de l'activité, même style hover que les activités organisées -->
                <a href="/sharetime/public/?page=detail&id=<?= $user_participation_item['idactivities'] ?>"
                   style="background:white; border:1.5px solid var(--gray-200); border-radius:10px;
                          padding:16px; display:flex; justify-content:space-between; align-items:center;
                          gap:12px; text-decoration:none; color:inherit; transition:box-shadow 0.2s, border-color 0.2s;"
                   onmouseover="this.style.boxShadow='var(--shadow-md)';this.style.borderColor='var(--navy)'"
                   onmouseout="this.style.boxShadow='none';this.style.borderColor='var(--gray-200)'">
                    <div>
                        <p style="font-weight:600; color:var(--gray-900); margin-bottom:4px;">
                            <?= htmlspecialchars($user_participation_item['title']) ?>
                        </p>
                        <!-- Date avec heure incluse pour les participations (plus détaillé que la liste organisées) -->
                        <p style="font-size:0.85rem; color:var(--gray-500);">
                            📅 <?= $participation_start_datetime->format('d/m/Y à H:i') ?> &nbsp;·&nbsp; 📍 <?= htmlspecialchars($user_participation_item['city']) ?>
                        </p>
                    </div>
                    <!-- Badge d'état de l'inscription : vert = confirmé, orange = liste d'attente -->
                    <span style="font-size:0.78rem; background:<?= $registration_badge_bg ?>; color:<?= $registration_badge_color ?>;
                                 padding:4px 12px; border-radius:99px; font-weight:600; white-space:nowrap;">
                        <?= $registration_status_label ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</main>

<?php if (isset($_SESSION['user']) && !$is_own && $profile): ?>
<!-- ── MODAL DE SIGNALEMENT ──────────────────────────────────────────────────────
     Accessible uniquement pour un utilisateur connecté consultant le profil d'un tiers.
     Le clic sur le fond de la modal (event.target === backdrop) ferme la modal.
     Le motif "Autre" révèle un textarea supplémentaire via onchange JS. -->
<div id="modal-report"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px;"
     onclick="if(event.target===this)this.style.display='none'">
    <div style="background:white;border-radius:16px;padding:32px;max-width:460px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="color:var(--navy);margin:0;font-size:1.15rem;">🚩 Signaler cet utilisateur</h2>
            <!-- Bouton × de fermeture -->
            <button onclick="document.getElementById('modal-report').style.display='none'"
                style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--gray-400);line-height:1;">×</button>
        </div>
        <p style="color:var(--gray-500);font-size:0.88rem;margin-bottom:20px;line-height:1.6;">
            Vous signalez le profil de <strong><?= htmlspecialchars($profile['prenom'] . ' ' . $profile['nom']) ?></strong>.
            Votre signalement sera examiné par l'équipe.
        </p>
        <form method="POST" action="/sharetime/public/?page=signaler"
              style="display:flex;flex-direction:column;gap:14px;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <!-- ID de l'utilisateur signalé passé en hidden (validé côté serveur) -->
            <input type="hidden" name="signale_id" value="<?= (int)$profile['idusers'] ?>">
            <!-- Redirection après soumission : retour sur le profil signalé -->
            <input type="hidden" name="redirect" value="/sharetime/public/?page=profil&id=<?= (int)$profile['idusers'] ?>">
            <div>
                <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:8px;font-size:0.9rem;">Motif du signalement *</label>
                <!-- onchange : affiche le textarea libre uniquement si l'option "Autre" est choisie -->
                <select name="motif" required
                    style="width:100%;padding:12px 14px;border:1.5px solid var(--gray-300);border-radius:10px;font-size:0.9rem;font-family:inherit;background:white;box-sizing:border-box;"
                    onchange="document.getElementById('motif-autre').style.display=this.value==='Autre'?'block':'none'">
                    <option value="">— Choisissez un motif —</option>
                    <option>Comportement abusif ou harcelant</option>
                    <option>Faux profil / usurpation d'identité</option>
                    <option>Contenu inapproprié</option>
                    <option>Spam ou arnaque</option>
                    <option>Autre</option>
                </select>
                <!-- Textarea libre pour "Autre" : caché par défaut, révélé par JS -->
                <textarea id="motif-autre" name="motif_detail" rows="3" placeholder="Précisez..."
                    style="display:none;width:100%;margin-top:8px;padding:10px 14px;border:1.5px solid var(--gray-300);border-radius:10px;font-size:0.9rem;font-family:inherit;resize:vertical;box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:4px;">
                <button type="button" onclick="document.getElementById('modal-report').style.display='none'"
                    style="padding:10px 20px;border:1.5px solid var(--gray-300);border-radius:10px;background:white;font-size:0.9rem;cursor:pointer;">
                    Annuler
                </button>
                <button type="submit"
                    style="padding:10px 20px;background:#DC2626;color:white;border:none;border-radius:10px;font-size:0.9rem;font-weight:600;cursor:pointer;">
                    Envoyer le signalement
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Avant soumission : si "Autre" est sélectionné, copie le texte libre dans la valeur du select
// pour que le handler reçoive un motif complet dans name="motif" sans champ supplémentaire
document.querySelector('#modal-report form').addEventListener('submit', function(reportSubmitEvent) {
    var motif_select_el  = this.querySelector('select[name="motif"]');
    var motif_detail_el  = document.getElementById('motif-autre');
    if (motif_select_el.value === 'Autre') {
        // Empêche la soumission si le textarea libre est vide
        if (!motif_detail_el.value.trim()) { reportSubmitEvent.preventDefault(); motif_detail_el.focus(); return; }
        // Préfixe "Autre : " pour que les logs distinguent le motif standardisé du texte libre
        motif_select_el.value = 'Autre : ' + motif_detail_el.value.trim();
    }
});
</script>
<?php endif; ?>

<?php endif; ?>
