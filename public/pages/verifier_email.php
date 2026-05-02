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
<main class="container" style="padding:80px 0; max-width:500px; margin:auto; text-align:center;">
    <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:48px 40px;">
        <p style="font-size:3rem; margin-bottom:16px;">📧</p>
        <h1 style="color:var(--navy); margin-bottom:8px; font-size:1.6rem;">Vérification en cours…</h1>
        <p style="color:var(--gray-500);">Vous allez être redirigé(e) automatiquement.</p>
    </div>
</main>
