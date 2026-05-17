<?php
/**
 * public/pages/mentions.php — Mentions légales
 *
 * Lit le contenu depuis la table `mentions` (géré par le super-admin dans ?page=owner&tab=contenu).
 * Si la table est vide, affiche un contenu par défaut.
 */

$mentions_row     = $pdo->query("SELECT contenu FROM mentions ORDER BY idmentions DESC LIMIT 1")->fetch();
$mentions_contenu = $mentions_row['contenu'] ?? '';
?>
<main class="container" style="padding:48px 0; max-width:800px; margin:auto;">

    <h1 style="color:var(--navy); margin-bottom:8px;">Mentions légales</h1>
    <p style="color:var(--gray-500); margin-bottom:40px; font-size:0.9rem;">Dernière mise à jour : 2026</p>

    <?php if ($mentions_contenu): ?>
        <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px; line-height:1.8; color:var(--gray-700);">
            <?= nl2br(htmlspecialchars($mentions_contenu)) ?>
        </div>
    <?php else: ?>
        <!-- Contenu par défaut si la BDD est vide -->
        <div style="display:flex; flex-direction:column; gap:24px;">

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">Éditeur du site</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    <strong>ShareTime</strong> est une plateforme web de mise en relation pour le partage d'activités entre particuliers.<br>
                    Email de contact : <a href="mailto:contact@sharetime.fr" style="color:var(--orange);">contact@sharetime.fr</a>
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">Hébergement</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    Ce site est hébergé dans un environnement de développement local (XAMPP / Apache).
                    En production, les informations d'hébergeur seront précisées ici.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">Données personnelles (RGPD)</h2>
                <p style="color:var(--gray-700); line-height:1.75; margin-bottom:12px;">
                    Les données collectées sont utilisées exclusivement pour le fonctionnement de ShareTime.
                    Elles ne sont jamais transmises, vendues ou partagées avec des tiers.
                </p>
                <p style="color:var(--gray-700); line-height:1.75;">
                    Conformément au <strong>RGPD</strong>, vous disposez d'un droit d'accès, de rectification
                    et de suppression de vos données via le
                    <a href="/sharetime/public/?page=contact" style="color:var(--orange); font-weight:600;">formulaire de contact</a>.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">Cookies</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    ShareTime utilise uniquement des cookies de session nécessaires au fonctionnement de l'authentification.
                    Aucun cookie publicitaire ou de traçage n'est utilisé.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">Limitation de responsabilité</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    ShareTime est une plateforme de mise en relation. Les activités sont organisées par des particuliers.
                    ShareTime ne peut être tenu responsable des événements organisés par ses membres.
                </p>
            </section>

        </div>
    <?php endif; ?>

</main>
