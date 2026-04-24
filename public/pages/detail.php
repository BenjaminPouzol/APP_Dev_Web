<?php if (!$activity): ?>

<main class="container" style="padding:80px 0; text-align:center;">
    <p style="font-size:2rem; margin-bottom:16px;">😕</p>
    <p style="font-size:1.2rem; color:var(--gray-600); margin-bottom:20px;">Activité introuvable.</p>
    <a href="/sharetime/public/?page=activites" class="btn btn-navy">← Retour aux activités</a>
</main>

<?php else:
    $emojis        = ['🏃', '🎨', '🌲', '🤝', '🖼️'];
    $color_classes = ['sport', 'atelier', 'sortie', 'club', 'art'];
    $idx    = $activity['idactivities'] % 5;
    $places = $activity['max_participants'] - $activity['nb_inscrits'];
    $start  = new DateTime($activity['start_time']);
    $end    = new DateTime($activity['end_time']);
?>

<main class="container" style="padding:40px 0; max-width:800px; margin:auto;">

    <a href="/sharetime/public/?page=activites"
       style="color:var(--gray-500); font-size:0.9rem; display:inline-flex; align-items:center; gap:6px; margin-bottom:24px; text-decoration:none;">
        ← Retour aux activités
    </a>

    <!-- Image header -->
    <div class="card-image <?= $color_classes[$idx] ?>"
         style="border-radius:var(--radius-lg); height:200px; margin-bottom:24px; font-size:4rem; position:relative;">
        <?= $emojis[$idx] ?>
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

        <h1 style="color:var(--navy); margin-bottom:6px;"><?= htmlspecialchars($activity['title']) ?></h1>
        <p style="color:var(--gray-500); font-size:0.9rem; margin-bottom:28px;">
            Organisée par
            <a href="/sharetime/public/?page=profil&id=<?= $activity['creator_id'] ?>"
               style="color:var(--orange); font-weight:600; text-decoration:none;">
                <?= htmlspecialchars($activity['prenom'] . ' ' . $activity['nom']) ?>
            </a>
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
            </div>
        </div>

        <!-- Description -->
        <div style="margin-bottom:28px;">
            <h3 style="color:var(--navy); margin-bottom:12px;">Description</h3>
            <p style="color:var(--gray-700); line-height:1.75;">
                <?= nl2br(htmlspecialchars($activity['description'])) ?>
            </p>
        </div>

        <!-- Actions inscription -->
        <?php if ($activity['status'] === 'active'): ?>
            <?php if (isset($_SESSION['user'])): ?>
                <?php if ((int)$_SESSION['user']['id'] === (int)$activity['creator_id']): ?>
                    <div style="background:var(--navy-pale); border-radius:10px; padding:16px; text-align:center;">
                        <p style="color:var(--navy); font-weight:600; margin:0;">Vous êtes l'organisateur de cette activité.</p>
                    </div>
                <?php elseif ($is_registered): ?>
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
            <?php else: ?>
                <div style="background:var(--orange-pale); border-radius:10px; padding:20px; text-align:center;">
                    <p style="margin-bottom:12px; color:var(--gray-700);">Connectez-vous pour vous inscrire à cette activité.</p>
                    <a href="/sharetime/public/?page=connexion" class="btn btn-orange">Se connecter</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php endif; ?>
