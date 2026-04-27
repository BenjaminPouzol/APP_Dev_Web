<?php if (!$activity): ?>

<main class="container" style="padding:80px 0; text-align:center;">
    <p style="font-size:2rem; margin-bottom:16px;">😕</p>
    <p style="font-size:1.2rem; color:var(--gray-600); margin-bottom:20px;">Activité introuvable.</p>
    <a href="/sharetime/public/?page=activites" class="btn btn-navy">← Retour aux activités</a>
</main>

<?php else:
    $cat    = $CATEGORY_MAP[$activity['category']] ?? $CATEGORY_MAP['autre'];
    $places = $activity['max_participants'] - $activity['nb_inscrits'];
    $start  = new DateTime($activity['start_time']);
    $end    = new DateTime($activity['end_time']);
    $is_inscrit   = $reg_status === 'inscrit';
    $is_waiting   = $reg_status === 'en_attente';
    $is_organizer = isset($_SESSION['user']) && (int)$_SESSION['user']['id'] === (int)$activity['creator_id'];
?>

<main class="container" style="padding:40px 0; max-width:800px; margin:auto;">

    <a href="/sharetime/public/?page=activites"
       style="color:var(--gray-500); font-size:0.9rem; display:inline-flex; align-items:center; gap:6px; margin-bottom:24px; text-decoration:none;">
        ← Retour aux activités
    </a>

    <!-- Image header -->
    <div class="card-image <?= $cat[1] ?>"
         style="border-radius:var(--radius-lg); height:200px; margin-bottom:24px; font-size:4rem; position:relative;">
        <?= $cat[0] ?>
        <span class="card-badge-vis" style="font-size:0.85rem;">
            <?= $activity['visibility'] === 'publique' ? '🌍 Public' : '🔒 Privé' ?>
        </span>
    </div>

    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">

        <!-- Statut annulé / terminé -->
        <?php if ($activity['status'] === 'annulee'): ?>
            <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
                ❌ Cette activité a été annulée.
            </div>
        <?php elseif ($activity['status'] === 'terminee'): ?>
            <div style="background:#F3F4F6; color:var(--gray-500); padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
                ✅ Cette activité est terminée.
            </div>
        <?php endif; ?>

        <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
            <h1 style="color:var(--navy); margin:0;"><?= htmlspecialchars($activity['title']) ?></h1>
            <a href="/sharetime/public/?page=activites&category=<?= htmlspecialchars($activity['category']) ?>"
               style="background:var(--gray-100); color:var(--gray-600); padding:4px 12px; border-radius:99px;
                      font-size:0.78rem; font-weight:600; text-decoration:none; white-space:nowrap; flex-shrink:0;">
                <?= $cat[0] ?> <?= $cat[2] ?>
            </a>
        </div>
        <p style="color:var(--gray-500); font-size:0.9rem; margin-bottom:28px;">
            Organisée par
            <a href="/sharetime/public/?page=profil&id=<?= $activity['creator_id'] ?>"
               style="color:var(--orange); font-weight:600; text-decoration:none;">
                <?= htmlspecialchars($activity['prenom'] . ' ' . $activity['nom']) ?>
            </a>
            <?php if ($activity['creator_note'] > 0): ?>
                <span style="color:var(--gray-400); margin:0 4px;">·</span>
                <span style="color:var(--orange);">★</span>
                <span style="font-weight:600; color:var(--gray-700);"><?= number_format($activity['creator_note'], 1) ?></span>
            <?php endif; ?>
        </p>

        <!-- Infos en grille -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:28px;">
            <div style="background:var(--gray-50); border-radius:10px; padding:16px;">
                <p style="font-size:0.75rem; color:var(--gray-500); text-transform:uppercase; font-weight:600; margin-bottom:4px;">Début</p>
                <p style="font-weight:600; color:var(--gray-900);">📅 <?= $start->format('d/m/Y à H:i') ?></p>
            </div>
            <div style="background:var(--gray-50); border-radius:10px; padding:16px;">
                <p style="font-size:0.75rem; color:var(--gray-500); text-transform:uppercase; font-weight:600; margin-bottom:4px;">Fin</p>
                <p style="font-weight:600; color:var(--gray-900);">🏁 <?= $end->format('d/m/Y à H:i') ?></p>
            </div>
            <div style="background:var(--gray-50); border-radius:10px; padding:16px;">
                <p style="font-size:0.75rem; color:var(--gray-500); text-transform:uppercase; font-weight:600; margin-bottom:4px;">Lieu</p>
                <p style="font-weight:600; color:var(--gray-900);">📍 <?= htmlspecialchars($activity['location']) ?>, <?= htmlspecialchars($activity['city']) ?></p>
            </div>
            <div style="background:var(--gray-50); border-radius:10px; padding:16px;">
                <p style="font-size:0.75rem; color:var(--gray-500); text-transform:uppercase; font-weight:600; margin-bottom:4px;">Participants</p>
                <p style="font-weight:600; color:var(--gray-900);">
                    👥 <?= $activity['nb_inscrits'] ?> / <?= $activity['max_participants'] ?>
                    <?php if ($places <= 0): ?>
                        <span class="places-full"> — Complet</span>
                    <?php elseif ($places <= 2): ?>
                        <span class="places-few"> — <?= $places ?> place(s)</span>
                    <?php else: ?>
                        <span class="places-ok"> — <?= $places ?> places libres</span>
                    <?php endif; ?>
                </p>
                <?php if ($activity['liste_attente_active'] && $activity['nb_attente'] > 0): ?>
                    <p style="font-size:0.8rem; color:var(--gray-500); margin-top:4px; margin-bottom:0;">
                        ⏳ <?= $activity['nb_attente'] ?> en attente
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Description -->
        <div style="margin-bottom:28px;">
            <h3 style="color:var(--navy); margin-bottom:12px;">Description</h3>
            <p style="color:var(--gray-700); line-height:1.75;">
                <?= nl2br(htmlspecialchars($activity['description'])) ?>
            </p>
        </div>

        <!-- ── Actions inscription (activité active) ── -->
        <?php if ($activity['status'] === 'active'): ?>
            <?php if (!isset($_SESSION['user'])): ?>
                <div style="background:var(--orange-pale); border-radius:10px; padding:20px; text-align:center;">
                    <p style="margin-bottom:12px; color:var(--gray-700);">Connectez-vous pour vous inscrire à cette activité.</p>
                    <a href="/sharetime/public/?page=connexion" class="btn btn-orange">Se connecter</a>
                </div>

            <?php elseif ($is_organizer): ?>
                <div style="background:var(--navy-pale); border-radius:10px; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                    <p style="color:var(--navy); font-weight:600; margin:0;">Vous êtes l'organisateur de cette activité.</p>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a href="/sharetime/public/?page=modifier_activite&id=<?= $activity['idactivities'] ?>"
                           class="btn btn-outline-navy btn-sm">✏️ Modifier</a>
                        <form method="post" action="/sharetime/public/?page=annuler_activite" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="activity_id" value="<?= $activity['idactivities'] ?>">
                            <button type="submit" class="btn btn-sm"
                                    style="border:1.5px solid #DC2626; color:#DC2626; background:white; cursor:pointer;"
                                    onclick="return confirm('Annuler définitivement cette activité ?\n\nLes inscrits ne seront pas notifiés automatiquement.')">
                                ❌ Annuler l'activité
                            </button>
                        </form>
                    </div>
                </div>

            <?php elseif ($is_inscrit): ?>
                <div style="background:#D1FAE5; border-radius:10px; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                    <p style="color:#065F46; font-weight:600; margin:0;">✅ Vous êtes inscrit(e) à cette activité.</p>
                    <form method="post" action="/sharetime/public/?page=se_desinscrire">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="activity_id" value="<?= $activity['idactivities'] ?>">
                        <button type="submit" class="btn btn-outline-navy btn-sm"
                                onclick="return confirm('Se désinscrire de cette activité ?')">
                            Se désinscrire
                        </button>
                    </form>
                </div>

            <?php elseif ($is_waiting): ?>
                <div style="background:#FEF3C7; border-radius:10px; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                    <div>
                        <p style="color:#92400E; font-weight:600; margin:0;">⏳ Vous êtes sur liste d'attente.</p>
                        <?php if ($waitlist_position > 0): ?>
                            <p style="color:#92400E; font-size:0.85rem; margin:4px 0 0;">
                                Position : <?= $waitlist_position ?> / <?= $waitlist_count ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="/sharetime/public/?page=se_desinscrire">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="activity_id" value="<?= $activity['idactivities'] ?>">
                        <button type="submit" class="btn btn-outline-navy btn-sm"
                                onclick="return confirm('Quitter la liste d\'attente ?')">
                            Quitter l'attente
                        </button>
                    </form>
                </div>

            <?php elseif ($places <= 0 && !empty($activity['liste_attente_active'])): ?>
                <div style="background:#FEF3C7; border-radius:10px; padding:16px 20px;">
                    <p style="color:#92400E; font-weight:600; margin-bottom:12px;">
                        Cette activité est complète — <?= $waitlist_count ?> personne(s) en attente.
                    </p>
                    <form method="post" action="/sharetime/public/?page=s_inscrire">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="activity_id" value="<?= $activity['idactivities'] ?>">
                        <button type="submit" class="btn btn-navy btn-lg" style="width:100%;">
                            ⏳ Rejoindre la liste d'attente
                        </button>
                    </form>
                </div>

            <?php elseif ($places <= 0): ?>
                <div style="background:#FEE2E2; border-radius:10px; padding:16px; text-align:center;">
                    <p style="color:#DC2626; font-weight:600; margin:0;">Cette activité est complète.</p>
                </div>

            <?php else: ?>
                <form method="post" action="/sharetime/public/?page=s_inscrire">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="activity_id" value="<?= $activity['idactivities'] ?>">
                    <button type="submit" class="btn btn-orange btn-lg" style="width:100%;">
                        🎯 S'inscrire à cette activité
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ── Notation (activité terminée) ── -->
        <?php if ($activity['status'] === 'terminee' && isset($_SESSION['user']) && $is_inscrit && !$is_organizer): ?>
            <div id="rating" style="margin-top:28px; border-top:1.5px solid var(--gray-200); padding-top:24px;">
                <h3 style="color:var(--navy); margin-bottom:12px;">⭐ Noter l'organisateur</h3>
                <?php if ($has_rated): ?>
                    <p style="color:var(--gray-500);">Vous avez déjà noté cet organisateur pour cette activité. Merci !</p>
                <?php else: ?>
                    <form method="post" action="/sharetime/public/?page=noter">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="activity_id" value="<?= $activity['idactivities'] ?>">
                        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                            <div style="display:flex; gap:6px;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label style="cursor:pointer; font-size:1.6rem; line-height:1;" title="<?= $i ?> étoile<?= $i > 1 ? 's' : '' ?>">
                                        <input type="radio" name="note" value="<?= $i ?>" required
                                               style="position:absolute; opacity:0; width:0; height:0;">
                                        <span class="star-label" data-val="<?= $i ?>">☆</span>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <button type="submit" class="btn btn-orange btn-sm">Envoyer</button>
                        </div>
                    </form>
                    <script>
                    (function(){
                        var labels = document.querySelectorAll('.star-label');
                        labels.forEach(function(lbl){
                            lbl.parentElement.querySelector('input').addEventListener('change', function(){
                                var val = parseInt(this.value);
                                labels.forEach(function(l){ l.textContent = parseInt(l.dataset.val) <= val ? '★' : '☆'; });
                            });
                        });
                    })();
                    </script>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- ── Commentaires ── -->
    <div id="comments" style="margin-top:28px; background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">
        <h3 style="color:var(--navy); margin-bottom:20px;">
            💬 Commentaires
            <?php if (count($comments) > 0): ?>
                <span style="font-size:0.85rem; font-weight:400; color:var(--gray-500);">(<?= count($comments) ?>)</span>
            <?php endif; ?>
        </h3>

        <?php if (empty($comments)): ?>
            <p style="color:var(--gray-400); font-style:italic;">Aucun commentaire pour le moment.</p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:14px; margin-bottom:24px;">
                <?php foreach ($comments as $comment): ?>
                    <div style="background:var(--gray-50); border-radius:10px; padding:14px 16px;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="font-weight:600; color:var(--navy); font-size:0.9rem;">
                                    <?= htmlspecialchars($comment['prenom'] . ' ' . $comment['nom']) ?>
                                </span>
                                <?php if ($comment['pseudo']): ?>
                                    <span style="color:var(--gray-400); font-size:0.8rem;">@<?= htmlspecialchars($comment['pseudo']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <span style="color:var(--gray-400); font-size:0.78rem;">
                                    <?= (new DateTime($comment['created_at']))->format('d/m/Y à H:i') ?>
                                </span>
                                <?php if (isset($_SESSION['user']) && ((int)$_SESSION['user']['id'] === (int)$comment['user_id'] || is_admin())): ?>
                                    <form method="post" action="/sharetime/public/?page=supprimer_commentaire" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="comment_id" value="<?= $comment['idcomments'] ?>">
                                        <input type="hidden" name="activity_id" value="<?= $activity['idactivities'] ?>">
                                        <button type="submit" class="btn btn-sm"
                                                style="padding:2px 8px; font-size:0.75rem; background:none; color:var(--gray-400); border:1px solid var(--gray-200);"
                                                onclick="return confirm('Supprimer ce commentaire ?')">✕</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p style="color:var(--gray-700); line-height:1.6; margin:0;">
                            <?= nl2br(htmlspecialchars($comment['content'])) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire d'ajout de commentaire -->
        <?php if (isset($_SESSION['user'])): ?>
            <form method="post" action="/sharetime/public/?page=commenter">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="activity_id" value="<?= $activity['idactivities'] ?>">
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <textarea name="content" rows="3" maxlength="1000" required
                              placeholder="Votre commentaire…"
                              style="border:1.5px solid var(--gray-200); border-radius:8px; padding:10px 14px;
                                     font-family:inherit; font-size:0.9rem; color:var(--gray-700);
                                     resize:vertical; outline:none; width:100%; box-sizing:border-box;"></textarea>
                    <div style="text-align:right;">
                        <button type="submit" class="btn btn-navy btn-sm">Publier</button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <p style="color:var(--gray-500); font-size:0.9rem;">
                <a href="/sharetime/public/?page=connexion" style="color:var(--orange); font-weight:600;">Connectez-vous</a>
                pour laisser un commentaire.
            </p>
        <?php endif; ?>
    </div>

</main>

<?php endif; ?>
