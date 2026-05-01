<main class="container" style="padding:40px 0; max-width:700px; margin:auto;">
    <h1 style="margin-bottom:8px; color:var(--navy);">Modifier l'activité</h1>
    <p style="color:var(--gray-500); margin-bottom:32px;">
        Modifie les informations de ton activité. Les participants inscrits recevront une notification.
    </p>

    <?php if (!empty($error)): ?>
        <div style="background:#FEE2E2; color:#DC2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:500;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px;">
        <form method="POST" action="/sharetime/public/?page=modifier_activite" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:20px;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="activity_id" value="<?= (int)$activity['idactivities'] ?>">

            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Titre *</label>
                <input type="text" name="title" required placeholder="Ex : Randonnée en forêt"
                       value="<?= htmlspecialchars($_POST['title'] ?? $activity['title']) ?>"
                       style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
            </div>

            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Description *</label>
                <textarea name="description" required rows="4" placeholder="Décris ton activité, le programme, ce qu'il faut prévoir..."
                          style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; resize:vertical; box-sizing:border-box;"><?= htmlspecialchars($_POST['description'] ?? $activity['description']) ?></textarea>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Lieu *</label>
                    <input type="text" name="location" required
                           value="<?= htmlspecialchars($_POST['location'] ?? $activity['location']) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Ville *</label>
                    <input type="text" name="city" required
                           value="<?= htmlspecialchars($_POST['city'] ?? $activity['city']) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Date et heure de début *</label>
                    <?php
                        $start_val = $_POST['start_time'] ?? date('Y-m-d\TH:i', strtotime($activity['start_time']));
                    ?>
                    <input type="datetime-local" name="start_time" required value="<?= htmlspecialchars($start_val) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Date et heure de fin *</label>
                    <?php
                        $end_val = $_POST['end_time'] ?? date('Y-m-d\TH:i', strtotime($activity['end_time']));
                    ?>
                    <input type="datetime-local" name="end_time" required value="<?= htmlspecialchars($end_val) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                        Participants max *
                        <?php if ($activity['nb_inscrits'] > 0): ?>
                            <span style="font-size:0.78rem; font-weight:400; color:var(--gray-500);">(min. <?= $activity['nb_inscrits'] ?> inscrits)</span>
                        <?php endif; ?>
                    </label>
                    <input type="number" name="max_participants" required min="<?= max(2, (int)$activity['nb_inscrits']) ?>"
                           value="<?= htmlspecialchars($_POST['max_participants'] ?? $activity['max_participants']) ?>"
                           style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Visibilité *</label>
                    <?php $vis = $_POST['visibility'] ?? $activity['visibility']; ?>
                    <select name="visibility"
                            style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box; background:white;">
                        <option value="publique" <?= $vis === 'publique' ? 'selected' : '' ?>>Publique</option>
                        <option value="privee"   <?= $vis === 'privee'   ? 'selected' : '' ?>>Privée</option>
                    </select>
                </div>
            </div>

            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">Catégorie *</label>
                <?php $cur_cat = $_POST['category'] ?? $activity['category']; ?>
                <select name="category"
                        style="width:100%; padding:12px 16px; border:1.5px solid var(--gray-300); border-radius:10px; font-size:0.95rem; font-family:inherit; box-sizing:border-box; background:white;">
                    <?php foreach ($CATEGORY_MAP as $val => [$emoji, , $label]): ?>
                        <option value="<?= $val ?>" <?= $cur_cat === $val ? 'selected' : '' ?>>
                            <?= $emoji ?> <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php $wl_checked = isset($_POST['liste_attente_active']) ? !empty($_POST['liste_attente_active']) : !empty($activity['liste_attente_active']); ?>
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:14px 16px;
                          border:1.5px solid var(--gray-200); border-radius:10px; background:var(--gray-50);">
                <input type="checkbox" name="liste_attente_active" value="1"
                       <?= $wl_checked ? 'checked' : '' ?>
                       style="width:18px; height:18px; accent-color:var(--orange); cursor:pointer;">
                <span style="color:var(--gray-700); font-weight:500;">
                    Activer la liste d'attente
                    <span style="display:block; font-size:0.8rem; font-weight:400; color:var(--gray-400);">
                        Les participants peuvent rejoindre une file d'attente si l'activité est complète.
                    </span>
                </span>
            </label>

            <!-- Photo de l'activité -->
            <div>
                <label style="display:block; font-weight:600; color:var(--gray-700); margin-bottom:8px;">
                    Photo de l'activité
                    <span style="font-size:0.78rem; font-weight:400; color:var(--gray-400);">(JPG, PNG, WebP · max 2 Mo)</span>
                </label>
                <?php if (!empty($activity['photo'])): ?>
                <div style="margin-bottom:10px; display:flex; align-items:center; gap:12px;">
                    <img src="/sharetime/public/uploads/activites/<?= htmlspecialchars($activity['photo']) ?>"
                         style="width:80px; height:56px; object-fit:cover; border-radius:8px; border:2px solid var(--gray-200);">
                    <span style="font-size:0.82rem; color:var(--gray-500);">Photo actuelle — importer une nouvelle pour la remplacer</span>
                </div>
                <?php else: ?>
                <p style="font-size:0.82rem; color:var(--gray-400); margin:0 0 10px;">Aucune photo pour l'instant. Vous pouvez en ajouter une ci-dessous.</p>
                <?php endif; ?>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp"
                       style="font-family:inherit; font-size:0.9rem; color:var(--gray-700);">
            </div>

            <div style="display:flex; gap:12px; margin-top:8px;">
                <button type="submit" class="btn btn-orange btn-lg">Enregistrer les modifications</button>
                <a href="/sharetime/public/?page=detail&id=<?= (int)$activity['idactivities'] ?>" class="btn btn-outline-navy btn-lg">Annuler</a>
            </div>
        </form>
    </div>
</main>
