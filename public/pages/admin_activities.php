<?php
/**
 * public/pages/admin_activities.php — Gestion des activités (panel admin)
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $admin_activities_list : tableau des activités de la page courante
 *   $admin_total_count     : nombre total d'activités
 *   $admin_total_pages     : nombre de pages de pagination
 *   $admin_current_page    : page courante
 *   $flash                 : message de succès/info après action
 *
 * Actions possibles (admin ET superadmin) :
 *   - set_status : changer le statut (active / en_cours / annulee / terminee)
 *   - delete     : supprimer définitivement (supprime aussi inscriptions, commentaires, etc.)
 *
 * Les actions POST sont traitées par handlers/admin.php (page=admin_activities).
 */
?>
<!-- ── EN-TÊTE ADMIN ──────────────────────────────────────────────────────── -->
<div style="background:var(--navy);padding:28px 0;">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <!-- Libellé de section affiché au-dessus du titre principal -->
            <p style="color:rgba(255,255,255,0.55);font-size:0.8rem;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px;">Administration</p>
            <!-- Titre principal de la page de gestion des activités -->
            <h1 style="color:white;margin:0;font-size:1.6rem;">Gestion des activités</h1>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <!-- Affichage du badge de rôle de l'admin connecté (admin ou owner) -->
            <?= role_badge($_SESSION['user']['role']) ?>
            <!-- Nom complet de l'admin connecté, protégé contre les XSS -->
            <span style="color:rgba(255,255,255,0.6);font-size:0.9rem;"><?= htmlspecialchars($_SESSION['user']['prenom'].' '.$_SESSION['user']['nom']) ?></span>
        </div>
    </div>
</div>

<main>
    <!-- Barre de navigation admin avec l'onglet 'admin_activities' actif -->
    <?php admin_nav('admin_activities'); ?>

    <div class="container" style="padding-bottom:48px;">

        <!-- Message de confirmation après action (changement de statut, suppression) -->
        <?php if ($flash): // Affiche le bandeau vert uniquement si un message flash existe ?>
        <div style="background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0;border-radius:10px;padding:12px 18px;margin-bottom:24px;font-weight:600;">
            <!-- htmlspecialchars protège contre toute injection dans le message flash -->
            <?= htmlspecialchars($flash) ?>
        </div>
        <?php endif; ?>

        <!-- ── TABLEAU DES ACTIVITÉS ───────────────────────────────────────── -->
        <div style="background:white;border:1.5px solid var(--gray-200);border-radius:14px;overflow:hidden;">
            <div style="padding:18px 20px;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <h2 style="margin:0;font-size:1rem;color:var(--navy);">
                    Toutes les activités
                    <!-- Compteur total en badge gris : affiche le nombre exact d'activités en BDD -->
                    <span style="margin-left:8px;background:#F3F4F6;color:#6B7280;font-size:0.75rem;padding:2px 10px;border-radius:99px;font-weight:600;"><?= $admin_total_count ?></span>
                </h2>
                <!-- Indicateur de pagination affiché uniquement s'il y a plus d'une page -->
                <?php if ($admin_total_pages > 1): ?>
                <span style="font-size:0.82rem;color:var(--gray-500);">Page <?= $admin_current_page ?> / <?= $admin_total_pages ?></span>
                <?php endif; ?>
            </div>

            <?php if (empty($admin_activities_list)): // Aucune activité en base de données ?>
            <!-- État vide : aucune activité enregistrée -->
            <div style="padding:48px;text-align:center;color:var(--gray-400);">
                <p style="font-size:2rem;margin-bottom:8px;">🎯</p>
                <p>Aucune activité enregistrée.</p>
            </div>
            <?php else: ?>
            <!-- overflow-x:auto pour scroller horizontalement sur mobile sans casser la mise en page -->
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                    <thead>
                        <!-- Ligne d'en-tête du tableau avec les labels des colonnes -->
                        <tr style="background:#F9FAFB;border-bottom:1px solid var(--gray-200);">
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Activité</th>
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Créateur</th>
                            <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Participants</th>
                            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Date</th>
                            <th style="padding:10px 16px;text-align:center;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Statut</th>
                            <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--gray-500);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    // Tables de correspondance statut → couleurs/libellés réutilisées pour chaque ligne
                    $status_badge_colors = [
                        'active'   => ['#D1FAE5', '#065F46'],   // vert  = à venir
                        'en_cours' => ['#FEF3C7', '#92400E'],   // orange= en cours
                        'annulee'  => ['#FEE2E2', '#DC2626'],   // rouge = annulée
                        'terminee' => ['#F3F4F6', '#6B7280'],   // gris  = terminée
                    ];
                    // Libellés lisibles affichés dans les badges, correspondant aux valeurs BDD
                    $status_badge_labels = ['active'=>'À venir','en_cours'=>'En cours','annulee'=>'Annulée','terminee'=>'Terminée'];
                    // Boucle sur chaque activité récupérée depuis la base de données
                    foreach ($admin_activities_list as $activity_row):
                        // Objet DateTime pour formater la date de début de l'activité
                        $start_datetime = new DateTime($activity_row['start_time']);
                        // Déstructuration des couleurs du badge (fond + texte) avec fallback gris
                        [$status_badge_bg, $status_badge_color] = $status_badge_colors[$activity_row['status']] ?? ['#F3F4F6', '#6B7280'];
                        // Badge violet "Privée" pour les activités non publiques, chaîne vide sinon
                        $visibility_html_badge = $activity_row['visibility'] === 'privee'
                            ? '<span style="background:#EDE9FE;color:#7C3AED;font-size:0.7rem;padding:1px 7px;border-radius:99px;font-weight:600;margin-left:4px;">Privée</span>'
                            : '';
                    ?>
                    <!-- Ligne du tableau pour une activité : séparée par une bordure en bas -->
                    <tr style="border-bottom:1px solid var(--gray-50);">
                        <!-- Colonne Activité : titre (tronqué avec ellipsis) + badge privée + ville -->
                        <td style="padding:12px 16px;max-width:220px;">
                            <p style="margin:0;font-weight:600;color:var(--gray-900);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <!-- Titre sécurisé + badge "Privée" injecté directement (HTML de confiance) -->
                                <?= htmlspecialchars($activity_row['title']) ?><?= $visibility_html_badge ?>
                            </p>
                            <!-- Ville de l'activité affichée en texte secondaire -->
                            <p style="margin:0;color:var(--gray-500);font-size:0.78rem;"><?= htmlspecialchars($activity_row['city']) ?></p>
                        </td>

                        <!-- Colonne Créateur : prénom + nom de l'organisateur de l'activité -->
                        <td style="padding:12px 16px;">
                            <p style="margin:0;color:var(--gray-700);"><?= htmlspecialchars($activity_row['prenom'].' '.$activity_row['nom']) ?></p>
                        </td>

                        <!-- Colonne Participants : inscrits confirmés / max participants -->
                        <td style="padding:12px 16px;text-align:center;">
                            <!-- Nombre d'inscrits casté en entier pour éviter tout affichage flottant -->
                            <span style="font-weight:600;color:var(--gray-900);"><?= (int)$activity_row['nb_inscrits'] ?></span>
                            <span style="color:var(--gray-400);">/</span>
                            <!-- Capacité maximale définie par l'organisateur -->
                            <span style="color:var(--gray-500);"><?= (int)$activity_row['max_participants'] ?></span>
                        </td>

                        <!-- Colonne Date de début formatée en jour/mois/année -->
                        <td style="padding:12px 16px;color:var(--gray-600);font-size:0.82rem;white-space:nowrap;"><?= $start_datetime->format('d/m/Y') ?></td>

                        <!-- Colonne Statut : badge coloré selon l'état actuel de l'activité -->
                        <td style="padding:12px 16px;text-align:center;">
                            <span style="background:<?= $status_badge_bg ?>;color:<?= $status_badge_color ?>;padding:3px 10px;border-radius:99px;font-size:0.75rem;font-weight:600;white-space:nowrap;">
                                <!-- Libellé du badge avec fallback ucfirst si le statut n'est pas dans la table -->
                                <?= $status_badge_labels[$activity_row['status']] ?? ucfirst($activity_row['status']) ?>
                            </span>
                        </td>

                        <!-- Colonne Actions : changer statut + voir + supprimer -->
                        <td style="padding:12px 16px;">
                            <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;align-items:center;">

                                <!-- Formulaire de changement de statut : select + bouton OK -->
                                <form method="POST" action="/sharetime/public/?page=admin_activities" style="display:flex;gap:4px;align-items:center;">
                                    <!-- Token CSRF caché pour sécuriser le formulaire contre les attaques cross-site -->
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <!-- ID de l'activité cible transmis de manière invisible -->
                                    <input type="hidden" name="activity_id" value="<?= (int)$activity_row['idactivities'] ?>">
                                    <!-- Type d'action : le handler sait qu'il doit changer le statut -->
                                    <input type="hidden" name="action" value="set_status">
                                    <!-- Select pré-sélectionné sur le statut actuel de l'activité -->
                                    <select name="status" style="padding:5px 8px;border-radius:6px;border:1.5px solid var(--gray-200);font-size:0.78rem;color:var(--gray-700);">
                                        <!-- L'attribut 'selected' est ajouté sur l'option correspondant au statut actuel -->
                                        <option value="active"   <?= $activity_row['status']==='active'   ?'selected':'' ?>>À venir</option>
                                        <option value="en_cours" <?= $activity_row['status']==='en_cours' ?'selected':'' ?>>En cours</option>
                                        <option value="annulee"  <?= $activity_row['status']==='annulee'  ?'selected':'' ?>>Annulée</option>
                                        <option value="terminee" <?= $activity_row['status']==='terminee' ?'selected':'' ?>>Terminée</option>
                                    </select>
                                    <!-- Bouton de soumission du formulaire de changement de statut -->
                                    <button type="submit" style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;cursor:pointer;">OK</button>
                                </form>

                                <!-- Lien Voir : ouvre la page de détail publique de l'activité -->
                                <a href="/sharetime/public/?page=detail&id=<?= (int)$activity_row['idactivities'] ?>"
                                   style="padding:5px 10px;border-radius:6px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-700);font-size:0.78rem;font-weight:600;text-decoration:none;"
                                   title="Voir l'activité">👁</a>

                                <!-- Bouton Supprimer : suppression définitive + confirmation JS obligatoire -->
                                <form method="POST" action="/sharetime/public/?page=admin_activities" style="display:inline;">
                                    <!-- Token CSRF pour sécuriser la suppression contre les requêtes forgées -->
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <!-- ID de l'activité à supprimer, casté en entier pour la sécurité -->
                                    <input type="hidden" name="activity_id" value="<?= (int)$activity_row['idactivities'] ?>">
                                    <!-- Action 'delete' indique au handler de supprimer l'activité et ses données liées -->
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit"
                                        style="padding:5px 10px;border-radius:6px;border:1.5px solid #DC2626;background:#DC2626;color:white;font-size:0.78rem;font-weight:600;cursor:pointer;"
                                        onclick="return confirm('Supprimer définitivement cette activité et toutes ses inscriptions ?')">
                                        <!-- confirm() bloque la soumission si l'admin clique "Annuler" -->
                                        🗑
                                    </button>
                                </form>

                            </div>
                        </td>
                    </tr>
                    <?php endforeach; // Fin du foreach sur les activités de la page courante ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── PAGINATION ─────────────────────────────────────────────────── -->
        <?php if ($admin_total_pages > 1): // Affiche la pagination uniquement s'il y a plusieurs pages ?>
        <div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:28px;flex-wrap:wrap;">
            <?php if ($admin_current_page > 1): // Lien "Précédent" masqué sur la première page ?>
                <a href="/sharetime/public/?page=admin_activities&p=<?= $admin_current_page - 1 ?>" class="btn btn-outline-navy btn-sm">← Précédent</a>
            <?php endif; ?>
            <!-- Fenêtre glissante de 5 pages autour de la courante : max(1, page-2) à min(total, page+2) -->
            <?php for ($page_number = max(1, $admin_current_page - 2); $page_number <= min($admin_total_pages, $admin_current_page + 2); $page_number++): ?>
                <a href="/sharetime/public/?page=admin_activities&p=<?= $page_number ?>"
                   style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;font-size:0.9rem;font-weight:600;text-decoration:none;
                          background:<?= $page_number === $admin_current_page ? 'var(--navy)' : 'var(--gray-100)' ?>;
                          color:<?= $page_number === $admin_current_page ? 'white' : 'var(--gray-600)' ?>;">
                    <!-- Numéro de page : fond navy + texte blanc si c'est la page active, gris sinon -->
                    <?= $page_number ?>
                </a>
            <?php endfor; ?>
            <?php if ($admin_current_page < $admin_total_pages): // Lien "Suivant" masqué sur la dernière page ?>
                <a href="/sharetime/public/?page=admin_activities&p=<?= $admin_current_page + 1 ?>" class="btn btn-outline-navy btn-sm">Suivant →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- Table responsive sur mobile : réduit taille de police et padding des cellules -->
<style>
@media (max-width: 768px) {
    table { font-size: 0.8rem !important; }  /* Police réduite pour faire tenir le tableau sur petit écran */
    td, th { padding: 8px 10px !important; } /* Padding réduit pour gagner de l'espace horizontal */
}
</style>
