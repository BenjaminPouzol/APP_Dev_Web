<?php
session_start();
require '../config/database.php';
require '../app/models/Activity.php';
require '../app/models/User.php';

$page = $_GET['page'] ?? 'home';
$error = null;
$success = null;

// ── CONNEXION ──────────────────────────────────────────
if ($page === 'connexion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        $userModel = new User($pdo);
        $user = $userModel->findByEmail($email);

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            $_SESSION['user'] = [
                'id'     => $user['idusers'],
                'nom'    => $user['nom'],
                'prenom' => $user['prenom'],
                'email'  => $user['email'],
                'role'   => $user['role']
            ];
            header('Location: /sharetime/public/');
            exit;
        } else {
            $error = "Email ou mot de passe incorrect.";
        }
    }
}

// ── INSCRIPTION ────────────────────────────────────────
if ($page === 'inscription' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom   = trim($_POST['firstname'] ?? '');
    $nom      = trim($_POST['lastname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm-password'] ?? '';

    if (empty($prenom) || empty($nom) || empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } else {
        $userModel = new User($pdo);
        if ($userModel->emailExists($email)) {
            $error = "Cet email est déjà utilisé.";
        } else {
            $userModel->create($prenom, $nom, $email, $password);
            $success = "Compte créé avec succès ! Vous pouvez vous connecter.";
            $page = 'connexion';
        }
    }
}

// ── DÉCONNEXION ────────────────────────────────────────
if ($page === 'logout') {
    session_destroy();
    header('Location: /sharetime/public/');
    exit;
}

// ── ROUTING ────────────────────────────────────────────
$allowed_pages = ['home', 'activites', 'connexion', 'inscription', 'contact', 'creer', 'detail', 'faq', 'profil'];
if (!in_array($page, $allowed_pages)) {
    $page = 'home';
}

require '../app/views/header.php';

// ── PAGES ──────────────────────────────────────────────
if ($page === 'home' || $page === 'activites') {
    $activityModel = new Activity($pdo);
    $activities = $activityModel->getAll();

    echo '<main class="container" style="padding: 40px 0;">';
    echo '<h1 style="margin-bottom: 24px;">Activités</h1>';

    foreach ($activities as $activity) {
        echo '<div style="background:white;border:1px solid #ddd;border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">';
        echo '<h2 style="margin:0 0 12px 0;color:#1E3A6E;">' . htmlspecialchars($activity['title']) . '</h2>';
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

} elseif ($page === 'connexion') {
    require 'pages/connexion.html';

} elseif ($page === 'inscription') {
    require 'pages/inscription.php';

} else {
    if ($page === 'connexion') {
        require 'pages/connexion.php';
    } else {
        require "pages/$page.html";
    }
}

require '../app/views/footer.php';