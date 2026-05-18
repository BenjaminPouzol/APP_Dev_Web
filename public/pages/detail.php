<?php
/**
 * public/pages/detail.php — Page de détail d'une activité
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $activity          : tableau avec toutes les colonnes de l'activité + JOIN users
 *                        (creator_id, prenom, nom, creator_note, nb_inscrits, nb_attente)
 *   $reg_status        : statut de l'utilisateur connecté — 'inscrit' | 'en_attente' | 'annule' | null
 *   $waitlist_count    : nombre total de personnes en liste d'attente
 *   $waitlist_position : position de l'utilisateur dans la liste d'attente (0 si non en attente)
 *   $comments          : tableau des commentaires avec données auteur
 *   $has_rated         : bool — l'utilisateur a-t-il déjà noté l'organisateur pour cette activité ?
 *   $CATEGORY_MAP      : mapping catégorie → [emoji, classe CSS, libellé]
 *
 * Si $activity est null (ID invalide ou activité inexistante), affiche un message d'erreur.
 */

// ── CAS : ACTIVITÉ INTROUVABLE ─────────────────────────────────────────────
if (!$activity): ?>

<main class="container" style="padding:80px 0; text-align:center;">
    <p style="font-size:2rem; margin-bottom:16px;">😕</p>
    <p style="font-size:1.2rem; color:var(--gray-600); margin-bottom:20px;">Activité introuvable.</p>
    <a href="/sharetime/public/?page=activites" class="btn btn-navy">← Retour aux activités</a>
</main>

<?php else:
    // ── VARIABLES LOCALES DÉRIVÉES ──────────────────────────────────────────
    $cat    = $CATEGORY_MAP[$activity['category']] ?? $CATEGORY_MAP['autre'];   // [emoji, CSS, libellé]
    $places = $activity['max_participants'] - $activity['nb_inscrits'];          // places restantes
    $start  = new DateTime($activity['start_time']);
    $end    = new DateTime($activity['end_time']);
    $is_inscrit   = $reg_status === 'inscrit';                                   // déjà confirmé
    $is_waiting   = $reg_status === 'en_attente';                                // en liste d'attente
    // L'utilisateur est organisateur si son ID correspond au creator_id de l'activité
    $is_organizer = isset($_SESSION['user']) && (int)$_SESSION['user']['id'] === (int)$activity['creator_id'];
?>

<main class="container" style="padding:40px 0; max-width:800px; margin:auto;">

    <!-- Lien retour vers la liste (flèche textuelle, pas de bouton pour rester léger) -->
    <a href="/sharetime/public/?page=activites"
       style="color:var(--gray-500); font-size:0.9rem; display:inline-flex; align-items:center; gap:6px; margin-bottom:24px; text-decoration:none;">
        ← Retour aux activités
    </a>

    <!-- ── IMAGE / HEADER VISUEL ─────────────────────────────────────────────
         Si une photo a été uploadée → fond d'image cover.
         Sinon → fond coloré par catégorie avec emoji (comme les cartes liste).
         Le badge de visibilité est positionné en absolu dans les deux cas. -->
    <?php if (!empty($activity['photo'])): ?>
    <div style="border-radius:var(--radius-lg); height:220px; margin-bottom:24px; position:relative; overflow:hidden;
                background-image:url('/sharetime/public/uploads/activites/<?= htmlspecialchars($activity['photo']) ?>');
                background-size:cover; background-position:center;">
    <?php else: ?>
    <div class="card-image <?= $cat[1] ?>"
         style="border-radius:var(--radius-lg); height:200px; margin-bottom:24px; font-size:4rem; position:relative;">
        <?= $cat[0] ?>
    <?php endif; ?>
        <!-- Badge visibilité : 🌍 Public ou 🔒 Privé -->
        <span class="card-badge-vis" style="font-size:0.85rem;">
            <?= $activity['visibility'] === 'publique' ? '🌍 Public' : '🔒 Privé' ?>
        </span>
    </div>

    <!-- ── CARD PRINCIPALE ────────────────────────────────────────────────── -->
    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">

        <!-- ── BANDEAU DE STATUT ─────────────────────────────────────────────
             Affiché uniquement pour les activités annulées ou terminées.
             Rouge = annulée, gris = terminée. -->
        <?php if ($activity['status'] === 'annulee'): ?>
            <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
                ❌ Cette activité a été annulée.
            </div>
        <?php elseif ($activity['status'] === 'en_cours'): ?>
            <div style="background:#FEF3C7; color:#92400E; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
                🔴 Cette activité est en cours.
            </div>
        <?php elseif ($activity['status'] === 'terminee'): ?>
            <div style="background:#F3F4F6; color:var(--gray-500); padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600;">
                ✅ Cette activité est terminée.
            </div>
        <?php endif; ?>

        <!-- ── TITRE + BADGE CATÉGORIE ───────────────────────────────────── -->
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
            <h1 style="color:var(--navy); margin:0;"><?= htmlspecialchars($activity['title']) ?></h1>
            <!-- Chip catégorie cliquable → filtre la liste par catégorie -->
            <a href="/sharetime/public/?page=activites&category=<?= htmlspecialchars($activity['category']) ?>"
               style="background:var(--gray-100); color:var(--gray-600); padding:4px 12px; border-radius:99px;
                      font-size:0.78rem; font-weight:600; text-decoration:none; white-space:nowrap; flex-shrink:0;">
                <?= $cat[0] ?> <?= $cat[2] ?>
            </a>
        </div>

        <!-- ── ORGANISATEUR + NOTE ────────────────────────────────────────── -->
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:28px;">
            <p style="color:var(--gray-500); font-size:0.9rem; margin:0;">
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
            <?php if (isset($_SESSION['user']) && !$is_organizer): ?>
            <button type="button" onclick="document.getElementById('modal-report-detail').style.display='flex'"
                style="padding:5px 12px;border-radius:8px;border:1.5px solid #FECACA;background:white;color:#DC2626;font-size:0.78rem;font-weight:600;cursor:pointer;flex-shrink:0;">
                🚩 Signaler l'organisateur
            </button>
            <?php endif; ?>
        </div>

        <!-- ── GRILLE D'INFORMATIONS ──────────────────────────────────────── -->
        <!-- 4 tuiles en 2×2 : début / fin / lieu / participants -->
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
                    <!-- Compteur inscrits / max avec indicateur coloré -->
                    👥 <?= $activity['nb_inscrits'] ?> / <?= $activity['max_participants'] ?>
                    <?php if ($places <= 0): ?>
                        <span class="places-full"> — Complet</span>
                    <?php elseif ($places <= 2): ?>
                        <span class="places-few"> — <?= $places ?> place(s)</span>
                    <?php else: ?>
                        <span class="places-ok"> — <?= $places ?> places libres</span>
                    <?php endif; ?>
                </p>
                <!-- Compteur liste d'attente : masqué si liste désactivée ou vide -->
                <?php if ($activity['liste_attente_active'] && $activity['nb_attente'] > 0): ?>
                    <p style="font-size:0.8rem; color:var(--gray-500); margin-top:4px; margin-bottom:0;">
                        ⏳ <?= $activity['nb_attente'] ?> en attente
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── DESCRIPTION ────────────────────────────────────────────────── -->
        <div style="margin-bottom:28px;">
            <h3 style="color:var(--navy); margin-bottom:12px;">Description</h3>
            <!-- nl2br : conserve les retours à la ligne saisis par l'organisateur -->
            <p style="color:var(--gray-700); line-height:1.75;">
                <?= nl2br(htmlspecialchars($activity['description'])) ?>
            </p>
        </div>

        <!-- ── ZONE D'ACTION INSCRIPTION ─────────────────────────────────────
             Affichée uniquement si l'activité est active (pas annulée, pas terminée).
             6 cas mutuellement exclusifs selon le statut de l'utilisateur :

             1. Non connecté               → invitation à se connecter
             2. Est l'organisateur          → boutons Modifier / Annuler l'activité
             3. Déjà inscrit (confirmé)     → bandeau vert + bouton Se désinscrire
             4. En liste d'attente          → bandeau jaune + position + bouton Quitter
             5. Complet + liste d'attente   → bouton Rejoindre la liste d'attente
             6. Complet sans liste          → message "complet" sans action
             7. Places disponibles          → bouton S'inscrire (cas par défaut) -->
        <?php if ($activity['status'] === 'active'): ?>

            <!-- Cas 1 : visiteur non connecté -->
            <?php if (!isset($_SESSION['user'])): ?>
                <div style="background:var(--orange-pale); border-radius:10px; padding:20px; text-align:center;">
                    <p style="margin-bottom:12px; color:var(--gray-700);">Connectez-vous pour vous inscrire à cette activité.</p>
                    <a href="/sharetime/public/?page=connexion" class="btn btn-orange">Se connecter</a>
                </div>

            <!-- Cas 2 : organisateur — peut modifier ou annuler -->
            <?php elseif ($is_organizer): ?>
                <div style="background:var(--navy-pale); border-radius:10px; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                    <p style="color:var(--navy); font-weight:600; margin:0;">Vous êtes l'organisateur de cette activité.</p>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a href="/sharetime/public/?page=modifier_activite&id=<?= $activity['idactivities'] ?>"
                           class="btn btn-outline-navy btn-sm">✏️ Modifier</a>
                        <!-- Annulation : confirmation JS obligatoire avant soumission -->
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

            <!-- Cas 3 : déjà inscrit (confirmé) — peut se désinscrire -->
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

            <!-- Cas 4 : en liste d'attente — affiche la position -->
            <?php elseif ($is_waiting): ?>
                <div style="background:#FEF3C7; border-radius:10px; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                    <div>
                        <p style="color:#92400E; font-weight:600; margin:0;">⏳ Vous êtes sur liste d'attente.</p>
                        <!-- Position dans la file : visible si > 0 (0 = non calculé) -->
                        <?php if ($waitlist_position > 0): ?>
                            <p style="color:#92400E; font-size:0.85rem; margin:4px 0 0;">
                                Position : <?= $waitlist_position ?> / <?= $waitlist_count ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <!-- Se désinscrire de la liste d'attente (même handler que la désinscription) -->
                    <form method="post" action="/sharetime/public/?page=se_desinscrire">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="activity_id" value="<?= $activity['idactivities'] ?>">
                        <button type="submit" class="btn btn-outline-navy btn-sm"
                                onclick="return confirm('Quitter la liste d\'attente ?')">
                            Quitter l'attente
                        </button>
                    </form>
                </div>

            <!-- Cas 5 : activité complète avec liste d'attente activée -->
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

            <!-- Cas 6 : activité complète sans liste d'attente — aucune action possible -->
            <?php elseif ($places <= 0): ?>
                <div style="background:#FEE2E2; border-radius:10px; padding:16px; text-align:center;">
                    <p style="color:#DC2626; font-weight:600; margin:0;">Cette activité est complète.</p>
                </div>

            <!-- Cas 7 : places disponibles — inscription normale -->
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

        <!-- ── NOTATION DE L'ORGANISATEUR ─────────────────────────────────────
             Conditions cumulatives pour afficher le formulaire de notation :
             - L'activité est terminée (statut 'terminee')
             - L'utilisateur est connecté
             - L'utilisateur était inscrit (reg_status = 'inscrit')
             - L'utilisateur n'est pas l'organisateur
             Si déjà noté ($has_rated), affiche un message à la place du formulaire. -->
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
                            <!-- 5 étoiles radio cachées : l'affichage est géré par les <span class="star-label"> -->
                            <div style="display:flex; gap:6px;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label style="cursor:pointer; font-size:1.6rem; line-height:1;" title="<?= $i ?> étoile<?= $i > 1 ? 's' : '' ?>">
                                        <!-- Radio visuellement caché (opacity:0, taille 0) — accessible via label -->
                                        <input type="radio" name="note" value="<?= $i ?>" required
                                               style="position:absolute; opacity:0; width:0; height:0;">
                                        <!-- ☆ vide par défaut, rempli (★) via JS quand sélectionné -->
                                        <span class="star-label" data-val="<?= $i ?>">☆</span>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <button type="submit" class="btn btn-orange btn-sm">Envoyer</button>
                        </div>
                    </form>
                    <!-- JS : quand un radio est coché, remplit toutes les étoiles jusqu'à sa valeur -->
                    <script>
                    (function(){
                        var labels = document.querySelectorAll('.star-label');
                        labels.forEach(function(lbl){
                            lbl.parentElement.querySelector('input').addEventListener('change', function(){
                                var val = parseInt(this.value);
                                // ★ pour les étoiles ≤ val, ☆ pour les autres
                                labels.forEach(function(l){ l.textContent = parseInt(l.dataset.val) <= val ? '★' : '☆'; });
                            });
                        });
                    })();
                    </script>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- ── SECTION COMMENTAIRES ───────────────────────────────────────────────
         Ouverte à tous les visiteurs en lecture.
         Le formulaire d'ajout est visible uniquement si connecté.
         Chaque commentaire peut être supprimé par son auteur ou par un admin. -->
    <div id="comments" style="margin-top:28px; background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">
        <h3 style="color:var(--navy); margin-bottom:20px;">
            💬 Commentaires
            <!-- Compteur de commentaires entre parenthèses si au moins 1 -->
            <?php if (count($comments) > 0): ?>
                <span style="font-size:0.85rem; font-weight:400; color:var(--gray-500);">(<?= count($comments) ?>)</span>
            <?php endif; ?>
        </h3>

        <?php if (empty($comments)): ?>
            <p style="color:var(--gray-400); font-style:italic;">Aucun commentaire pour le moment.</p>
        <?php else: ?>
            <!-- Liste des commentaires existants -->
            <div style="display:flex; flex-direction:column; gap:14px; margin-bottom:24px;">
                <?php foreach ($comments as $comment): ?>
                    <div style="background:var(--gray-50); border-radius:10px; padding:14px 16px;">
                        <!-- En-tête du commentaire : nom + pseudo + date + bouton suppression -->
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="font-weight:600; color:var(--navy); font-size:0.9rem;">
                                    <?= htmlspecialchars($comment['prenom'] . ' ' . $comment['nom']) ?>
                                </span>
                                <!-- Pseudo affiché en gris clair si disponible -->
                                <?php if ($comment['pseudo']): ?>
                                    <span style="color:var(--gray-400); font-size:0.8rem;">@<?= htmlspecialchars($comment['pseudo']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <span style="color:var(--gray-400); font-size:0.78rem;">
                                    <?= (new DateTime($comment['created_at']))->format('d/m/Y à H:i') ?>
                                </span>
                                <!-- Bouton suppression : visible pour l'auteur du commentaire OU un admin -->
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
                        <!-- Contenu du commentaire : nl2br pour les sauts de ligne -->
                        <p style="color:var(--gray-700); line-height:1.6; margin:0;">
                            <?= nl2br(htmlspecialchars($comment['content'])) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire d'ajout de commentaire : visible uniquement si connecté -->
        <?php if (isset($_SESSION['user'])): ?>
            <form method="post" action="/sharetime/public/?page=commenter">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="activity_id" value="<?= $activity['idactivities'] ?>">
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <!-- maxlength côté client doublé de la validation mb_strlen côté serveur -->
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
            <!-- Visiteur non connecté : invite à se connecter pour commenter -->
            <p style="color:var(--gray-500); font-size:0.9rem;">
                <a href="/sharetime/public/?page=connexion" style="color:var(--orange); font-weight:600;">Connectez-vous</a>
                pour laisser un commentaire.
            </p>
        <?php endif; ?>
    </div>

</main>

<?php if (isset($_SESSION['user']) && !$is_organizer && $activity): ?>
<!-- ── MODAL SIGNALEMENT ORGANISATEUR ───────────────────────────────────────── -->
<div id="modal-report-detail"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px;"
     onclick="if(event.target===this)this.style.display='none'">
    <div style="background:white;border-radius:16px;padding:32px;max-width:460px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 style="color:var(--navy);margin:0;font-size:1.15rem;">🚩 Signaler l'organisateur</h2>
            <button onclick="document.getElementById('modal-report-detail').style.display='none'"
                style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--gray-400);line-height:1;">×</button>
        </div>
        <p style="color:var(--gray-500);font-size:0.88rem;margin-bottom:20px;line-height:1.6;">
            Vous signalez <strong><?= htmlspecialchars($activity['prenom'] . ' ' . $activity['nom']) ?></strong>,
            organisateur de cette activité.
        </p>
        <form id="detail-report-form" method="POST" action="/sharetime/public/?page=signaler"
              style="display:flex;flex-direction:column;gap:14px;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="signale_id" value="<?= (int)$activity['creator_id'] ?>">
            <input type="hidden" name="redirect" value="/sharetime/public/?page=detail&id=<?= (int)$activity['idactivities'] ?>">
            <div>
                <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:8px;font-size:0.9rem;">Motif du signalement *</label>
                <select name="motif" required
                    style="width:100%;padding:12px 14px;border:1.5px solid var(--gray-300);border-radius:10px;font-size:0.9rem;font-family:inherit;background:white;box-sizing:border-box;"
                    onchange="document.getElementById('motif-autre-detail').style.display=this.value==='Autre'?'block':'none'">
                    <option value="">— Choisissez un motif —</option>
                    <option>Comportement abusif ou harcelant</option>
                    <option>Faux profil / usurpation d'identité</option>
                    <option>Contenu inapproprié dans l'activité</option>
                    <option>Activité frauduleuse ou trompeuse</option>
                    <option>Spam ou arnaque</option>
                    <option>Autre</option>
                </select>
                <textarea id="motif-autre-detail" name="motif_detail" rows="3" placeholder="Précisez..."
                    style="display:none;width:100%;margin-top:8px;padding:10px 14px;border:1.5px solid var(--gray-300);border-radius:10px;font-size:0.9rem;font-family:inherit;resize:vertical;box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:4px;">
                <button type="button" onclick="document.getElementById('modal-report-detail').style.display='none'"
                    style="padding:10px 20px;border:1.5px solid var(--gray-300);border-radius:10px;background:white;font-size:0.9rem;cursor:pointer;">
                    Annuler
                </button>
                <button type="submit"
                    style="padding:10px 20px;background:#DC2626;color:white;border:none;border-radius:10px;font-size:0.9rem;font-weight:600;cursor:pointer;">
                    Envoyer
                </button>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('detail-report-form').addEventListener('submit', function(e) {
    var sel    = this.querySelector('select[name="motif"]');
    var detail = document.getElementById('motif-autre-detail');
    if (sel.value === 'Autre') {
        if (!detail.value.trim()) { e.preventDefault(); detail.focus(); return; }
        sel.value = 'Autre : ' + detail.value.trim();
    }
});
</script>
<?php endif; ?>

<?php endif; ?>
