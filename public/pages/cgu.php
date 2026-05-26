<?php
/**
 * public/pages/cgu.php — Conditions Générales d'Utilisation
 *
 * Lit le contenu depuis la table `cgu` (géré par le super-admin dans ?page=owner&tab=contenu).
 * Si la table est vide, affiche un contenu par défaut.
 */

// Récupère la dernière version des CGU depuis la base (tri descendant pour avoir la plus récente)
$cgu_row = $pdo->query("SELECT contenu, version FROM cgu ORDER BY idcgu DESC LIMIT 1")->fetch();
// Extrait le contenu textuel des CGU, ou chaîne vide si aucun enregistrement en base
$cgu_contenu = $cgu_row['contenu'] ?? '';
// Extrait le numéro de version, avec "v1.0" comme valeur par défaut si absent
$cgu_version = $cgu_row['version'] ?? 'v1.0';
?>
<!-- Conteneur principal centré avec une largeur maximale de 800px -->
<main class="container" style="padding:48px 0; max-width:800px; margin:auto;">

    <!-- Titre principal de la page des CGU -->
    <h1 style="color:var(--navy); margin-bottom:8px;">Conditions Générales d'Utilisation</h1>
    <!-- Affiche le numéro de version récupéré en base (ou "v1.0" par défaut) -->
    <p style="color:var(--gray-500); margin-bottom:40px; font-size:0.9rem;">
        Version : <?= htmlspecialchars($cgu_version) // Protège l'affichage contre les injections XSS ?>
    </p>

    <?php if ($cgu_contenu): // Si un contenu a été saisi par l'administrateur en base de données ?>
        <!-- Affiche le contenu dynamique géré par l'administrateur -->
        <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px; line-height:1.8; color:var(--gray-700);">
            <?= nl2br(htmlspecialchars($cgu_contenu)) // Affiche le texte en conservant les sauts de ligne ?>
        </div>
    <?php else: // Sinon, affiche le contenu statique par défaut ?>
        <!-- Contenu par défaut si la BDD est vide -->
        <!-- Conteneur vertical des sections d'articles des CGU -->
        <div style="display:flex; flex-direction:column; gap:24px;">

            <!-- Article 1 : objet des CGU -->
            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">1. Objet</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    Les présentes Conditions Générales d'Utilisation définissent les règles d'accès
                    et d'utilisation de la plateforme ShareTime.
                </p>
            </section>

            <!-- Article 2 : règles d'inscription et responsabilités liées au compte -->
            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">2. Inscription</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    L'utilisateur s'engage à fournir des informations exactes lors de la création de son compte.
                    Il est responsable de la confidentialité de ses identifiants de connexion.
                </p>
            </section>

            <!-- Article 3 : description de la plateforme et règles de comportement -->
            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">3. Utilisation de la plateforme</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    ShareTime permet aux utilisateurs de découvrir, créer et rejoindre des activités.
                    L'utilisateur s'engage à respecter les lois en vigueur et les autres membres.
                </p>
            </section>

            <!-- Article 4 : limitation de responsabilité de la plateforme -->
            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">4. Responsabilité</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    ShareTime agit en tant que plateforme de mise en relation entre particuliers.
                    La responsabilité des activités incombe aux utilisateurs qui les organisent.
                </p>
            </section>

            <!-- Article 5 : politique de traitement des données personnelles -->
            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">5. Données personnelles</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    Les informations collectées sont utilisées uniquement dans le cadre du fonctionnement
                    de la plateforme et de l'amélioration de l'expérience utilisateur.
                </p>
            </section>

            <!-- Article 6 : informations de contact -->
            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">6. Contact</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    Pour toute question, contactez l'équipe ShareTime via la
                    <!-- Lien vers le formulaire de contact -->
                    <a href="/sharetime/public/?page=contact" style="color:var(--orange); font-weight:600;">page contact</a>.
                </p>
            </section>

        </div>
    <?php endif; // Fin de la condition contenu BDD / contenu par défaut ?>

    <!-- Lien de retour vers la page d'inscription -->
    <p style="margin-top:36px;">
        <a href="/sharetime/public/?page=inscription" class="btn btn-outline-navy">
            ← Retour à l'inscription
        </a>
    </p>

</main>
