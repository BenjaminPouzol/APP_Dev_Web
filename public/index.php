<?php
$page = $_GET['page'] ?? 'home';

$allowed_pages = ['home', 'activites', 'connexion', 'contact', 'creer', 'detail', 'faq', 'profil'];

if (!in_array($page, $allowed_pages)) {
    $page = 'home';
}

require '../app/views/header.php';

if ($page === 'home') {
    require 'pages/activites.html';
} else {
    require "pages/$page.html";
}

require '../app/views/footer.php';
?>
