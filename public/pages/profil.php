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
                    <!-- Profil d'un autre utilisateur : boutons Suivre/Se désabonner + Message -->
                    <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end;">
                        <!-- Formulaire Follow/Unfollow : toggle côté serveur (handlers/user.php) -->
                        <form method="post" action="/sharetime/public/?page=suivre" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="user_id" value="<?= $profile['idusers'] ?>">
                            <!-- Bouton orange si non suivi, outline s'il l'est déjà -->
                            <button type="submit" class="btn btn-sm <?= $is_following ? 'btn-outline-navy' : 'btn-orange' ?>">
                                <?= $is_following ? '✓ Abonné(e)' : '+ Suivre' ?>
                            </button>
                        </form>
                        <!-- Lien messagerie : ouvre la conversation avec cet utilisateur -->
                        <a href="/sharetime/public/?page=messages&with=<?= $profile['idusers'] ?>"
                           class="btn btn-sm btn-outline-navy">✉️ Message</a>
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
                    $isActive = $a['status'] === 'active';
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
                        $status_labels = ['active' => 'Active', 'terminee' => 'Terminée', 'annulee' => 'Annulée'];
                        $status_label  = $status_labels[$a['status']] ?? ucfirst($a['status']);
                    ?>
                    <!-- Badge statut : vert pour active, gris pour les autres -->
                    <span style="font-size:0.78rem; padding:4px 12px; border-radius:99px; font-weight:600; white-space:nowrap;
                                 background:<?= $isActive ? '#D1FAE5' : 'var(--gray-100)' ?>;
                                 color:<?= $isActive ? '#065F46' : 'var(--gray-500)' ?>;">
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

<?php endif; ?>
