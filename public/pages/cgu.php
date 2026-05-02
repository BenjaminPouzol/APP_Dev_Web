<?php
/**
 * public/pages/cgu.php — Conditions Générales d'Utilisation
 *
 * Page statique sans données dynamiques. Accessible à tous (connecté ou non).
 * Liée depuis le formulaire d'inscription (lien "CGU") et le footer.
 */
?>
<main class="container" style="padding:48px 0; max-width:800px; margin:auto;">

    <h1 style="color:var(--navy); margin-bottom:8px;">Conditions Générales d'Utilisation</h1>
    <p style="color:var(--gray-500); margin-bottom:40px; font-size:0.9rem;">Dernière mise à jour : avril 2026</p>

    <div style="display:flex; flex-direction:column; gap:24px;">

        <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
            <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">1. Objet</h2>
            <p style="color:var(--gray-700); line-height:1.75;">
                Les présentes Conditions Générales d'Utilisation ont pour objet de définir les règles d'accès
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
                L'utilisateur s'engage à utiliser la plateforme dans le respect des lois en vigueur
                et des autres membres.
            </p>
        </section>

        <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
            <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">4. Comportement des utilisateurs</h2>
            <p style="color:var(--gray-700); line-height:1.75;">
                Tout comportement abusif, frauduleux, diffamatoire ou portant atteinte aux autres utilisateurs
                pourra entraîner la suspension ou la suppression du compte concerné.
            </p>
        </section>

        <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
            <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">5. Responsabilité</h2>
            <p style="color:var(--gray-700); line-height:1.75;">
                ShareTime agit en tant que plateforme de mise en relation entre particuliers.
                La responsabilité des activités, de leur organisation et de leur déroulement incombe aux utilisateurs.
            </p>
        </section>

        <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
            <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">6. Données personnelles</h2>
            <p style="color:var(--gray-700); line-height:1.75;">
                Les informations collectées sont utilisées uniquement dans le cadre du fonctionnement
                de la plateforme et de l'amélioration de l'expérience utilisateur.
            </p>
        </section>

        <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
            <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">7. Modification des conditions</h2>
            <p style="color:var(--gray-700); line-height:1.75;">
                ShareTime se réserve le droit de modifier les présentes CGU à tout moment.
                Les utilisateurs seront informés de toute mise à jour importante.
            </p>
        </section>

        <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
            <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">8. Contact</h2>
            <p style="color:var(--gray-700); line-height:1.75;">
                Pour toute question relative aux présentes conditions, vous pouvez contacter
                l'équipe ShareTime via la
                <a href="/sharetime/public/?page=contact" style="color:var(--orange); font-weight:600;">page contact</a>.
            </p>
        </section>

    </div>

    <p style="margin-top:36px;">
        <a href="/sharetime/public/?page=inscription" class="btn btn-outline-navy">
            ← Retour à l'inscription
        </a>
    </p>

</main>
