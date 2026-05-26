<?php
/**
 * public/pages/verifier_email.php — Écran d'attente de vérification d'email
 *
 * Cette page n'est jamais réellement affichée à l'utilisateur :
 * le handler GET dans app/handlers/auth.php traite le token et redirige
 * immédiatement vers ?page=connexion avec un flash message (succès ou erreur).
 *
 * Ce fichier sert uniquement de "fallback" au cas où le handler n'aurait pas
 * redirigé (token absent ou page chargée sans paramètre ?token=).
 * L'utilisateur voit brièvement cet écran de chargement.
 */
?>
<!-- Conteneur principal centré, limité à 500px, affiché brièvement avant la redirection -->
<main class="container" style="padding:80px 0; max-width:500px; margin:auto; text-align:center;">
    <!-- Carte blanche avec bordure, affichée pendant le traitement du token -->
    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:48px 40px;">
        <!-- Icône enveloppe pour illustrer visuellement la vérification d'email -->
        <p style="font-size:3rem; margin-bottom:16px;">📧</p>
        <!-- Titre indiquant que la vérification est en cours -->
        <h1 style="color:var(--navy); margin-bottom:8px; font-size:1.6rem;">Vérification en cours…</h1>
        <!-- Message rassurant l'utilisateur qu'une redirection automatique va avoir lieu -->
        <p style="color:var(--gray-500);">Vous allez être redirigé(e) automatiquement.</p>
    </div>
</main>
