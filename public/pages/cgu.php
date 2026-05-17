<?php
/**
 * public/pages/cgu.php — Conditions Générales d'Utilisation
 *
 * Lit le contenu depuis la table `cgu` (géré par le super-admin dans ?page=owner&tab=contenu).
 * Si la table est vide, affiche un contenu par défaut.
 */

$cgu_row = $pdo->query("SELECT contenu, version FROM cgu ORDER BY idcgu DESC LIMIT 1")->fetch();
$cgu_contenu = $cgu_row['contenu'] ?? '';
$cgu_version = $cgu_row['version'] ?? 'v1.0';
?>
<main class="container" style="padding:48px 0; max-width:800px; margin:auto;">

    <h1 style="color:var(--navy); margin-bottom:8px;">Conditions Générales d'Utilisation</h1>
    <p style="color:var(--gray-500); margin-bottom:40px; font-size:0.9rem;">
        Version : <?= htmlspecialchars($cgu_version) ?>
    </p>

    <?php if ($cgu_contenu): ?>
        <div style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:32px; line-height:1.8; color:var(--gray-700);">
            <?= nl2br(htmlspecialchars($cgu_contenu)) ?>
        </div>
    <?php else: ?>
        <!-- Contenu par défaut si la BDD est vide -->
        <div style="display:flex; flex-direction:column; gap:24px;">

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">1. Objet</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    Les présentes Conditions Générales d'Utilisation définissent les règles d'accès
                    et d'utilisation de la plateforme ShareTime.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">2. Inscription</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    L'utilisateur s'engage à fournir des informations exactes lors de la création de son compte.
                    Il est responsable de la confidentialité de ses identifiants de connexion.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">3. Utilisation de la plateforme</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    ShareTime permet aux utilisateurs de découvrir, créer et rejoindre des activités.
                    L'utilisateur s'engage à respecter les lois en vigueur et les autres membres.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">4. Responsabilité</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    ShareTime agit en tant que plateforme de mise en relation entre particuliers.
                    La responsabilité des activités incombe aux utilisateurs qui les organisent.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">5. Données personnelles</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    Les informations collectées sont utilisées uniquement dans le cadre du fonctionnement
                    de la plateforme et de l'amélioration de l'expérience utilisateur.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">6. Contact</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    Pour toute question, contactez l'équipe ShareTime via la
                    <a href="/sharetime/public/?page=contact" style="color:var(--orange); font-weight:600;">page contact</a>.
                </p>
            </section>

        </div>
    <?php endif; ?>

    <p style="margin-top:36px;">
        <a href="/sharetime/public/?page=inscription" class="btn btn-outline-navy">
            ← Retour à l'inscription
        </a>
    </p>

</main>
