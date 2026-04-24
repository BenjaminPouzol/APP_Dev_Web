<?php if (!$profile): ?>

<main class="container" style="padding:80px 0; text-align:center;">
    <p style="font-size:2rem; margin-bottom:16px;">😕</p>
    <p style="font-size:1.2rem; color:var(--gray-600);">Profil introuvable.</p>
</main>

<?php else:
    $is_own = isset($_SESSION['user']) && (int)$_SESSION['user']['id'] === (int)$profile['idusers'];
?>

<main class="container" style="padding:40px 0; max-width:800px; margin:auto;">

    <?php if (!empty($flash)): ?>
        <div style="background:#D1FAE5; color:#065F46; padding:12px 16px; border-radius:10px; margin-bottom:24px; font-weight:500;">
            ✅ <?= htmlspecialchars($flash) ?>
        </div>
    <?php endif; ?>

    <!-- En-tête profil -->
    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px; margin-bottom:24px;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
            <div style="display:flex; align-items:center; gap:20px;">
                <div style="width:72px; height:72px; border-radius:50%;
                            background:linear-gradient(135deg, var(--navy), var(--navy-light));
                            display:flex; align-items:center; justify-content:center;
                            color:white; font-size:1.8rem; font-weight:700; flex-shrink:0;">
                    <?= strtoupper(mb_substr($profile['prenom'], 0, 1)) ?>
                </div>
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
                </div>
            </div>
            <?php if ($is_own): ?>
                <a href="/sharetime/public/?page=profil_edit" class="btn btn-outline-navy">✏️ Modifier le profil</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($profile['bio'])): ?>
            <p style="color:var(--gray-700); margin-top:20px; line-height:1.75;
                      border-top:1px solid var(--gray-100); padding-top:20px;">
                <?= nl2br(htmlspecialchars($profile['bio'])) ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Activités organisées -->
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
                    $start = new DateTime($a['start_time']);
                    $isActive = $a['status'] === 'active';
                ?>
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
                    <span style="font-size:0.78rem; padding:4px 12px; border-radius:99px; font-weight:600; white-space:nowrap;
                                 background:<?= $isActive ? '#D1FAE5' : 'var(--gray-100)' ?>;
                                 color:<?= $isActive ? '#065F46' : 'var(--gray-500)' ?>;">
                        <?= $isActive ? 'Active' : ucfirst($a['status']) ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Activités auxquelles je participe (profil propre seulement) -->
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
                    <span style="font-size:0.78rem; background:#D1FAE5; color:#065F46;
                                 padding:4px 12px; border-radius:99px; font-weight:600; white-space:nowrap;">
                        Inscrit(e)
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</main>

<?php endif; ?>
