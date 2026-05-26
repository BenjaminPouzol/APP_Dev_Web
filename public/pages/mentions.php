<?php
/**
 * public/pages/mentions.php — Mentions légales
 *
 * Lit le contenu depuis la table `mentions` (géré par le super-admin dans ?page=owner&tab=contenu).
 * Si la table est vide, affiche un contenu par défaut.
 */

// Récupère la dernière version des mentions légales depuis la base (la plus récente en premier)
$mentions_row     = $pdo->query("SELECT contenu FROM mentions ORDER BY idmentions DESC LIMIT 1")->fetch();
// Extrait le contenu textuel, ou chaîne vide si aucun enregistrement en base
$mentions_contenu = $mentions_row['contenu'] ?? '';
?>
<!-- Conteneur principal centré avec une largeur maximale de 800px -->
<main class="container" style="padding:48px 0; max-width:800px; margin:auto;">

    <!-- Titre principal de la page des mentions légales -->
    <h1 style="color:var(--navy); margin-bottom:8px;">Mentions légales</h1>
    <!-- Date de dernière mise à jour affichée sous le titre -->
    <p style="color:var(--gray-500); margin-bottom:40px; font-size:0.9rem;">Dernière mise à jour : 2026</p>

    <?php if ($mentions_contenu): // Si un contenu a été saisi par l'administrateur en base de données ?>
        <!-- Affiche le contenu dynamique géré par l'administrateur -->
        <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px; line-height:1.8; color:var(--gray-700);">
            <?= nl2br(htmlspecialchars($mentions_contenu)) // Affiche le texte en conservant les sauts de ligne ?>
        </div>
    <?php else: // Sinon, affiche le contenu statique par défaut ?>
        <!-- Contenu par défaut si la BDD est vide -->
        <!-- Conteneur vertical des sections des mentions légales -->
        <div style="display:flex; flex-direction:column; gap:24px;">

            <!-- Section 1 : identification de l'éditeur du site -->
            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">Éditeur du site</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    <strong>ShareTime</strong> est une plateforme web de mise en relation pour le partage d'activités entre particuliers.<br>
                    <!-- Lien mailto pour contacter l'équipe directement par email -->
                    Email de contact : <a href="mailto:contact@sharetime.fr" style="color:var(--orange);">contact@sharetime.fr</a>
                </p>
            </section>

            <!-- Section 2 : informations sur l'hébergeur du site -->
            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">Hébergement</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    Ce site est hébergé dans un environnement de développement local (XAMPP / Apache).
                    En production, les informations d'hébergeur seront précisées ici.
                </p>
            </section>

            <!-- Section 3 : politique RGPD et droits des utilisateurs sur leurs données -->
            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">Données personnelles (RGPD)</h2>
                <p style="color:var(--gray-700); line-height:1.75; margin-bottom:12px;">
                    Les données collectées sont utilisées exclusivement pour le fonctionnement de ShareTime.
                    Elles ne sont jamais transmises, vendues ou partagées avec des tiers.
                </p>
                <p style="color:var(--gray-700); line-height:1.75;">
                    Conformément au <strong>RGPD</strong>, vous disposez d'un droit d'accès, de rectification
                    et de suppression de vos données via le
                    <!-- Lien vers le formulaire de contact pour exercer les droits RGPD -->
                    <a href="/sharetime/public/?page=contact" style="color:var(--orange); font-weight:600;">formulaire de contact</a>.
                </p>
            </section>

            <!-- Section 4 : politique d'utilisation des cookies -->
            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">Cookies</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    ShareTime utilise uniquement des cookies de session nécessaires au fonctionnement de l'authentification.
                    Aucun cookie publicitaire ou de traçage n'est utilisé.
                </p>
            </section>

            <!-- Section 5 : limitation de responsabilité de la plateforme vis-à-vis des activités -->
            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">Limitation de responsabilité</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    ShareTime est une plateforme de mise en relation. Les activités sont organisées par des particuliers.
                    ShareTime ne peut être tenu responsable des événements organisés par ses membres.
                </p>
            </section>

        </div>
    <?php endif; // Fin de la condition contenu BDD / contenu par défaut ?>

</main>
