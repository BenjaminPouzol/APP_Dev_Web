<!-- En-tête admin -->
<div style="background:var(--navy);padding:28px 0;">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <p style="color:rgba(255,255,255,0.55);font-size:0.8rem;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Administration</p>
            <h1 style="color:white;margin:0;font-size:1.6rem;">Tableau de bord</h1>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <?= role_badge($_SESSION['user']['role']) ?>
            <span style="color:rgba(255,255,255,0.6);font-size:0.9rem;"><?= htmlspecialchars($_SESSION['user']['prenom'].' '.$_SESSION['user']['nom']) ?></span>
        </div>
    </div>
</div>

<main>
    <?php admin_nav('admin'); ?>

    <div class="container" style="padding-bottom:48px;">

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:36px;">
            <?php
            $cards = [
                ['Membres',      $admin_stats['membres'],      '👤', '#EBF0F8', 'var(--navy)'],
                ['Admins',       $admin_stats['admins'],       '🛡️', '#FEF3E2', 'var(--orange)'],
                ['Activités',    $admin_stats['activites'],    '🎯', '#D1FAE5', '#065F46'],
                ['Inscriptions', $admin_stats['inscriptions'], '✅', '#EDE9FE', '#7C3AED'],
                ['Suspendus',    $admin_stats['suspendus'],    '🚫', '#FEE2E2', '#DC2626'],
            ];
            foreach ($cards as [$label, $val, $icon, $bg, $color]):
            ?>
            <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;padding:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-size:0.75rem;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;"><?= $label ?></span>
                    <span style="font-size:1.3rem;background:<?= $bg ?>;padding:6px;border-radius:8px;"><?= $icon ?></span>
                </div>
                <p style="font-size:2rem;font-weight:800;color:<?= $color ?>;margin:0;"><?= $val ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Deux colonnes : derniers membres + dernières activités -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

            <!-- Derniers membres -->
            <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
                <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;">
                    <h2 style="margin:0;font-size:1rem;color:var(--navy);">Derniers membres</h2>
                    <a href="/sharetime/public/?page=admin_users" style="font-size:0.82rem;color:var(--orange);font-weight:600;text-decoration:none;">Tout voir →</a>
                </div>
                <?php foreach ($admin_recent_users as $u): ?>
                <div style="padding:12px 20px;border-bottom:1px solid var(--gray-50);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-light));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                            <?= strtoupper(mb_substr($u['prenom'],0,1)) ?>
                        </div>
                        <div>
                            <p style="margin:0;font-size:0.88rem;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></p>
                            <p style="margin:0;font-size:0.78rem;color:var(--gray-500);"><?= htmlspecialchars($u['email']) ?></p>
                        </div>
                    </div>
                    <?= role_badge($u['role'], !empty($u['is_banned'])) ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Dernières activités -->
            <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
                <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;">
                    <h2 style="margin:0;font-size:1rem;color:var(--navy);">Dernières activités</h2>
                    <a href="/sharetime/public/?page=admin_activities" style="font-size:0.82rem;color:var(--orange);font-weight:600;text-decoration:none;">Tout voir →</a>
                </div>
                <?php foreach ($admin_recent_activities as $a):
                    $start = new DateTime($a['start_time']);
                    $statusColors = ['active'=>['#D1FAE5','#065F46'],'annulee'=>['#FEE2E2','#DC2626'],'terminee'=>['#F3F4F6','#6B7280']];
                    [$sbg,$scol] = $statusColors[$a['status']] ?? ['#F3F4F6','#6B7280'];
                ?>
                <div style="padding:12px 20px;border-bottom:1px solid var(--gray-50);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <div>
                        <p style="margin:0;font-size:0.88rem;font-weight:600;color:var(--gray-900);"><?= htmlspecialchars($a['title']) ?></p>
                        <p style="margin:0;font-size:0.78rem;color:var(--gray-500);"><?= htmlspecialchars($a['city']) ?> · <?= $start->format('d/m/Y') ?></p>
                    </div>
                    <span style="background:<?= $sbg ?>;color:<?= $scol ?>;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;white-space:nowrap;"><?= ucfirst($a['status']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</main>

<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
    div[style*="repeat(auto-fit"] { grid-template-columns: repeat(2,1fr) !important; }
}
</style>
