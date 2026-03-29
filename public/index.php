<?php
require '../config/database.php';
require '../app/models/Activity.php';

$page = $_GET['page'] ?? 'home';

$allowed_pages = ['home', 'activites', 'connexion', 'contact', 'creer', 'detail', 'faq', 'profil'];

if (!in_array($page, $allowed_pages)) {
    $page = 'home';
}

require '../app/views/header.php';

if ($page === 'activites') {
    $activityModel = new Activity($pdo);
    $activities = $activityModel->getAll();
    echo '<main class="container" style="padding: 40px 0;">';
    echo '<h1 style="margin-bottom: 24px;">Activités</h1>';

    foreach ($activities as $activity) {
        echo '<div style="
            background: white;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        ">';

        echo '<h2 style="margin: 0 0 12px 0; color: #1E3A6E;">' . htmlspecialchars($activity['title']) . '</h2>';
        echo '<p><strong>Description :</strong> ' . htmlspecialchars($activity['description']) . '</p>';
        echo '<p><strong>Lieu :</strong> ' . htmlspecialchars($activity['location']) . '</p>';
        echo '<p><strong>Ville :</strong> ' . htmlspecialchars($activity['city']) . '</p>';
        echo '<p><strong>Début :</strong> ' . htmlspecialchars($activity['start_time']) . '</p>';
        echo '<p><strong>Fin :</strong> ' . htmlspecialchars($activity['end_time']) . '</p>';
        echo '<p><strong>Participants max :</strong> ' . htmlspecialchars($activity['max_participants']) . '</p>';
        echo '<p><strong>Visibilité :</strong> ' . htmlspecialchars($activity['visibility']) . '</p>';
        echo '<p><strong>Statut :</strong> ' . htmlspecialchars($activity['status']) . '</p>';

        echo '</div>';
    }

echo '</main>';
} else {
    require "pages/$page.html";
}

require '../app/views/footer.php';
