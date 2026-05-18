<?php
/**
 * public/pages/profil.php — Page de profil utilisateur
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $profile           : données de l'utilisateur dont on affiche le profil (peut être null)
 *   $user_activities   : activités créées par cet utilisateur
 *   $user_registrations: activités auxquelles l'utilisateur est inscrit/en attente
 *                        (uniquement si $is_own — données sensibles, non visibles par autrui)
 *   $follower_count    : nombre d'abonnés de ce profil
 *   $following_count   : nombre d'abonnements de ce profil
 *   $is_following      : bool — l'utilisateur connecté suit-il ce profil ?
 *
 * Si $profile est null (ID invalide ou utilisateur inexistant), affiche un message d'erreur.
 * La page peut afficher son propre profil OU le profil public d'un autre utilisateur.
 */

// ── CAS : PROFIL INTROUVABLE ──────────────────────────────────────────────
if (!$profile): ?>

<main class="container" style="padding:80px 0; text-align:center;">
    <p style="font-size:2rem; margin-bottom:16px;">😕</p>
    <p style="font-size:1.2rem; color:var(--gray-600);">Profil introuvable.</p>
</main>

<?php else:
    // true si l'utilisateur connecté consulte son propre profil
    $is_own = isset($_SESSION['user']) && (int)$_SESSION['user']['id'] === (int)$profile['idusers'];
?>

<main class="container" style="padding:40px 0; max-width:800px; margin:auto;">

    <!-- ── BANDEAU VÉRIFICATION EMAIL ─────────────────────────────────────────
         Affiché uniquement sur son propre profil ($is_own) si l'email n'est
         pas encore vérifié. Contient un bouton de renvoi de l'email. -->
    <?php if ($is_own && empty($profile['email_verified'])): ?>
    <div style="background:#FEF3E2; border:1.5px solid rgba(232,129,26,0.4); border-radius:10px;
                padding:14px 20px; margin-bottom:20px; display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
        <span style="font-size:1.3rem;">⚠️</span>
        <div style="flex:1;">
            <p style="margin:0; font-weight:600; color:#92400E; font-size:0.9rem;">Votre adresse email n'est pas encore vérifiée.</p>
            <p style="margin:4px 0 0; color:#B45309; font-size:0.82rem;">Certaines fonctionnalités peuvent être limitées.</p>
        </div>
        <!-- Bouton de renvoi : POST vers handlers/auth.php (page=renvoyer_verification) -->
        <form method="post" action="/sharetime/public/?page=renvoyer_verification" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <button type="submit" class="btn btn-sm btn-orange">Renvoyer l'email</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- ── EN-TÊTE DU PROFIL ───────────────────────────────────────────────── -->
    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px; margin-bottom:24px;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">

            <!-- Zone gauche : avatar + nom + pseudo + ville + note -->
            <div style="display:flex; align-items:center; gap:20px;">
                <!-- Avatar : photo uploadée ou initiale sur fond gradient navy -->
                <?php if (!empty($profile['photo_profil'])): ?>
                <img src="/sharetime/public/uploads/profils/<?= htmlspecialchars($profile['photo_profil']) ?>"
                     style="width:72px; height:72px; border-radius:50%; object-fit:cover; flex-shrink:0; border:2px solid var(--gray-200);">
                <?php else: ?>
                <!-- Initiale du prénom centrée dans un cercle gradient navy -->
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
                    <?php if (!empty($profile['pseudo'])): ?>
                        <p style="color:var(--orange); font-weight:600; margin:2px 0;">
                            @<?= htmlspecialchars($profile['pseudo']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($profile['ville'])): ?>
                        <p style="color:var(--gray-500); font-size:0.9rem; margin:4px 0;">
                            📍 <?= htmlspecialchars($profile['ville']) ?>
                        </p>
                    <?php endif; ?>
                    <!-- Note moyenne de l'utilisateur en tant qu'organisateur (masquée si 0) -->
                    <?php if (!empty($profile['note_moyenne'])): ?>
                        <p style="font-size:0.9rem; margin:4px 0; color:var(--gray-700);">
                            <span style="color:var(--orange);">★</span>
                            <strong><?= number_format($profile['note_moyenne'], 1) ?></strong>
                            <span style="color:var(--gray-400); font-size:0.82rem;">en tant qu'organisateur</span>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Zone droite : compteurs follow + boutons d'action -->
            <div style="display:flex; flex-direction:column; align-items:flex-end; gap:10px;">
                <!-- Compteurs abonnés / abonnements -->
                <div style="display:flex; gap:16px; font-size:0.85rem; color:var(--gray-600);">
                    <span><strong style="color:var(--navy);"><?= $follower_count ?></strong> abonné<?= $follower_count > 1 ? 's' : '' ?></span>
                    <span><strong style="color:var(--navy);"><?= $following_count ?></strong> abonnement<?= $following_count > 1 ? 's' : '' ?></span>
                </div>

                <?php if ($is_own): ?>
                    <!-- Profil propre : bouton de modification -->
                    <a href="/sharetime/public/?page=profil_edit" class="btn btn-outline-navy btn-sm">✏️ Modifier le profil</a>
                <?php elseif (isset($_SESSION['user'])): ?>
                    <!-- Profil d'un autre utilisateur : boutons Suivre/Se désabonner + Message + Signaler -->
                    <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end;">
                        <!-- Formulaire Follow/Unfollow -->
                        <form method="post" action="/sharetime/public/?page=suivre" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="user_id" value="<?= $profile['idusers'] ?>">
                            <button type="submit" class="btn btn-sm <?= $is_following ? 'btn-outline-navy' : 'btn-orange' ?>">
                                <?= $is_following ? '✓ Abonné(e)' : '+ Suivre' ?>
                            </button>
                        </form>
                        <a href="/sharetime/public/?page=messages&with=<?= $profile['idusers'] ?>"
                           class="btn btn-sm btn-outline-navy">✉️ Message</a>
                        <!-- Bouton signalement -->
                        <button type="button" onclick="document.getElementById('modal-report').style.display='flex'"
                            style="padding:6px 14px;border-radius:8px;border:1.5px solid #FECACA;background:white;color:#DC2626;font-size:0.82rem;font-weight:600;cursor:pointer;">
                            🚩 Signaler
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Biographie : affichée seulement si renseignée, séparée par une bordure -->
        <?php if (!empty($profile['bio'])): ?>
            <p style="color:var(--gray-700); margin-top:20px; line-height:1.75;
                      border-top:1px solid var(--gray-100); padding-top:20px;">
                <?= nl2br(htmlspecialchars($profile['bio'])) ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- ── ACTIVITÉS ORGANISÉES ────────────────────────────────────────────── -->
    <div style="margin-bottom:28px;">
        <h2 style="color:var(--navy); margin-bottom:16px; font-size:1.2rem;">
            Activités organisées (<?= count($user_activities) ?>)
        </h2>
        <?php if (empty($user_activities)): ?>
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
                <?php foreach ($user_activities as $a):
                    $start    = new DateTime($a['start_time']);
                    $isActive = in_array($a['status'], ['active', 'en_cours']);
                ?>
                <!-- Ligne activité : lien vers la page de détail, hover avec box-shadow navy -->
                <a href="/sharetime/public/?page=detail&id=<?= $a['idactivities'] ?>"
                   style="background:white; border:1.5px solid var(--gray-200); border-radius:10px;
                          padding:16px; display:flex; justify-content:space-between; align-items:center;
                          gap:12px; text-decoration:none; color:inherit;
                          transition:box-shadow 0.2s, border-color 0.2s;"
                   onmouseover="this.style.boxShadow='var(--shadow-md)';this.style.borderColor='var(--navy)'"
                   onmouseout="this.style.boxShadow='none';this.style.borderColor='var(--gray-200)'">
                    <div>
                        <p style="font-weight:600; color:var(--gray-900); margin-bottom:4px;">
                            <?= htmlspecialchars($a['title']) ?>
                        </p>
                        <p style="font-size:0.85rem; color:var(--gray-500);">
                            📅 <?= $start->format('d/m/Y') ?> &nbsp;·&nbsp;
                            📍 <?= htmlspecialchars($a['city']) ?> &nbsp;·&nbsp;
                            👥 <?= $a['nb_inscrits'] ?>/<?= $a['max_participants'] ?>
                        </p>
                    </div>
                    <?php
                        // Libellé du statut avec fallback sur ucfirst pour les états inattendus
                        $status_labels = ['active' => 'À venir', 'en_cours' => 'En cours', 'terminee' => 'Terminée', 'annulee' => 'Annulée'];
                        $status_label  = $status_labels[$a['status']] ?? ucfirst($a['status']);
                    ?>
                    <!-- Badge statut : vert à venir, orange en cours, gris sinon -->
                    <?php
                        $badge_bg  = ['active'=>'#D1FAE5','en_cours'=>'#FEF3C7','annulee'=>'var(--gray-100)','terminee'=>'var(--gray-100)'];
                        $badge_col = ['active'=>'#065F46','en_cours'=>'#92400E','annulee'=>'var(--gray-500)','terminee'=>'var(--gray-500)'];
                    ?>
                    <span style="font-size:0.78rem; padding:4px 12px; border-radius:99px; font-weight:600; white-space:nowrap;
                                 background:<?= $badge_bg[$a['status']] ?? 'var(--gray-100)' ?>;
                                 color:<?= $badge_col[$a['status']] ?? 'var(--gray-500)' ?>;">
                        <?= $status_label ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── MES PARTICIPATIONS (propre profil seulement) ───────────────────────
         Non affiché sur le profil public d'un autre utilisateur pour des
         raisons de confidentialité (quelles activités il a rejointes n'est pas public). -->
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
                <?php foreach ($user_registrations as $a):
                    $start = new DateTime($a['start_time']);
                ?>
                <a href="/sharetime/public/?page=detail&id=<?= $a['idactivities'] ?>"
                   style="background:white; border:1.5px solid var(--gray-200); border-radius:10px;
                          padding:16px; display:flex; justify-content:space-between; align-items:center;
                          gap:12px; text-decoration:none; color:inherit; transition:box-shadow 0.2s, border-color 0.2s;"
                   onmouseover="this.style.boxShadow='var(--shadow-md)';this.style.borderColor='var(--navy)'"
                   onmouseout="this.style.boxShadow='none';this.style.borderColor='var(--gray-200)'">
                    <div>
                        <p style="font-weight:600; color:var(--gray-900); margin-bottom:4px;">
                            <?= htmlspecialchars($a['title']) ?>
                        </p>
                        <p style="font-size:0.85rem; color:var(--gray-500);">
                            📅 <?= $start->format('d/m/Y à H:i') ?> &nbsp;·&nbsp; 📍 <?= htmlspecialchars($a['city']) ?>
                        </p>
                    </div>
                    <?php
                        // Couleurs du badge selon le statut d'inscription
                        $rs       = $a['reg_status'] ?? 'inscrit';
                        $rs_bg    = $rs === 'en_attente' ? '#FEF3E2' : '#D1FAE5';   // orange pâle ou vert pâle
                        $rs_color = $rs === 'en_attente' ? '#92400E' : '#065F46';   // orange foncé ou vert foncé
                        $rs_label = $rs === 'en_attente' ? 'En attente' : 'Inscrit(e)';
                    ?>
                    <span style="font-size:0.78rem; background:<?= $rs_bg ?>; color:<?= $rs_color ?>;
                                 padding:4px 12px; border-radius:99px; font-weight:600; white-space:nowrap;">
                        <?= $rs_label ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</main>

<?php if (isset($_SESSION['user']) && !$is_own && $profile): ?>
<!-- ── MODAL DE SIGNALEMENT ──────────────────────────────────────────────────── -->
<div id="modal-report"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px;"
     onclick="if(event.target===this)this.style.display='none'">
    <div style="background:white;border-radius:16px;padding:32px;max-width:460px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="color:var(--navy);margin:0;font-size:1.15rem;">🚩 Signaler cet utilisateur</h2>
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
            <input type="hidden" name="signale_id" value="<?= (int)$profile['idusers'] ?>">
            <input type="hidden" name="redirect" value="/sharetime/public/?page=profil&id=<?= (int)$profile['idusers'] ?>">
            <div>
                <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:8px;font-size:0.9rem;">Motif du signalement *</label>
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
// Fusionne la valeur du select et du textarea "Autre" avant soumission
document.querySelector('#modal-report form').addEventListener('submit', function(e) {
    var sel    = this.querySelector('select[name="motif"]');
    var detail = document.getElementById('motif-autre');
    if (sel.value === 'Autre') {
        if (!detail.value.trim()) { e.preventDefault(); detail.focus(); return; }
        sel.value = 'Autre : ' + detail.value.trim();
    }
});
</script>
<?php endif; ?>

<?php endif; ?>
