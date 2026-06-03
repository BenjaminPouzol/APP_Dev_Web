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
        <div style="display:flex; flex-direction:column; gap:24px;">

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">1. Objet et champ d'application</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    Les présentes Conditions Générales d'Utilisation (ci-après « CGU ») régissent l'accès et l'utilisation
                    de la plateforme ShareTime, accessible à l'adresse <strong>sharetime.fr</strong> (ci-après « la Plateforme »),
                    éditée par l'équipe ShareTime.<br><br>
                    Toute inscription ou utilisation de la Plateforme vaut acceptation pleine et entière des présentes CGU.
                    Si vous n'acceptez pas ces conditions, veuillez ne pas utiliser la Plateforme.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">2. Description du service</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    ShareTime est une plateforme communautaire permettant à ses membres de :<br><br>
                    • <strong>Créer</strong> des activités locales (sportives, culturelles, sociales, créatives, etc.) ;<br>
                    • <strong>Rejoindre</strong> des activités proposées par d'autres membres ;<br>
                    • <strong>Interagir</strong> avec d'autres participants via la messagerie interne ;<br>
                    • <strong>Découvrir</strong> des événements à proximité grâce à la recherche géolocalisée.<br><br>
                    ShareTime agit exclusivement en tant qu'intermédiaire technique facilitant la mise en relation entre particuliers.
                    Elle n'organise, ne sponsorise et n'est pas partie prenante des activités publiées.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">3. Inscription et compte utilisateur</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    <strong>3.1 Conditions d'accès</strong><br>
                    L'inscription est réservée aux personnes physiques majeures (18 ans ou plus) ou aux mineurs disposant
                    d'une autorisation parentale explicite. En vous inscrivant, vous déclarez avoir l'âge requis.<br><br>

                    <strong>3.2 Exactitude des informations</strong><br>
                    Vous vous engagez à fournir des informations exactes, complètes et à jour lors de la création de votre
                    compte (nom, prénom, adresse e-mail, date de naissance). Toute usurpation d'identité est strictement interdite.<br><br>

                    <strong>3.3 Confidentialité des identifiants</strong><br>
                    Vous êtes seul responsable de la confidentialité de votre mot de passe. Vous vous engagez à ne pas le
                    communiquer à des tiers et à informer immédiatement ShareTime de tout accès non autorisé à votre compte.<br><br>

                    <strong>3.4 Unicité du compte</strong><br>
                    Chaque utilisateur ne peut posséder qu'un seul compte actif. La création de comptes multiples est interdite
                    et peut entraîner la suppression de l'ensemble des comptes concernés.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">4. Règles de conduite et obligations des utilisateurs</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    En utilisant ShareTime, vous vous engagez à :<br><br>
                    • Respecter les lois et réglementations françaises et européennes en vigueur ;<br>
                    • Ne pas publier de contenu à caractère haineux, discriminatoire, violent, pornographique ou illégal ;<br>
                    • Ne pas harceler, menacer ou intimider d'autres membres ;<br>
                    • Ne pas utiliser la Plateforme à des fins commerciales ou publicitaires sans autorisation préalable ;<br>
                    • Ne pas tenter d'accéder aux systèmes informatiques de la Plateforme de manière non autorisée ;<br>
                    • Ne pas publier d'activités fictives ou trompeuses dans le but d'induire d'autres membres en erreur ;<br>
                    • Ne pas collecter les données personnelles d'autres membres sans leur consentement.<br><br>
                    Tout manquement à ces règles peut entraîner la suspension ou la suppression définitive du compte, sans préavis.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">5. Activités et responsabilité des organisateurs</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    <strong>5.1 Responsabilité de l'organisateur</strong><br>
                    L'utilisateur qui crée une activité (ci-après « l'Organisateur ») en est seul responsable.
                    Il garantit que l'activité est conforme aux lois applicables, qu'elle ne présente pas de danger
                    injustifié pour les participants et que les informations publiées sont exactes.<br><br>

                    <strong>5.2 Assurance</strong><br>
                    Il appartient à chaque Organisateur de vérifier que son assurance responsabilité civile couvre
                    les activités qu'il propose. ShareTime ne saurait être tenu responsable des dommages corporels
                    ou matériels survenus lors d'une activité.<br><br>

                    <strong>5.3 Annulation</strong><br>
                    En cas d'annulation d'une activité, l'Organisateur s'engage à en informer les participants
                    inscrits dans les meilleurs délais via la messagerie de la Plateforme.<br><br>

                    <strong>5.4 Participation</strong><br>
                    Tout participant s'engage à respecter les conditions fixées par l'Organisateur et à se comporter
                    de manière respectueuse envers les autres membres lors de l'activité.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">6. Propriété intellectuelle</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    <strong>6.1 Contenu de la Plateforme</strong><br>
                    L'ensemble des éléments constituant la Plateforme (logo, design, code source, textes, etc.) sont la
                    propriété exclusive de ShareTime et sont protégés par le droit de la propriété intellectuelle.
                    Toute reproduction ou utilisation sans autorisation est interdite.<br><br>

                    <strong>6.2 Contenu des utilisateurs</strong><br>
                    En publiant du contenu sur la Plateforme (photos de profil, descriptions d'activités, messages),
                    vous concédez à ShareTime une licence non exclusive, mondiale et gratuite pour afficher,
                    reproduire et adapter ce contenu dans le seul but de faire fonctionner le service.
                    Vous garantissez détenir les droits nécessaires sur les contenus publiés.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">7. Données personnelles et RGPD</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    <strong>7.1 Responsable de traitement</strong><br>
                    ShareTime est responsable du traitement de vos données personnelles conformément au Règlement
                    Général sur la Protection des Données (RGPD – Règlement UE 2016/679).<br><br>

                    <strong>7.2 Données collectées</strong><br>
                    Dans le cadre de votre utilisation de la Plateforme, nous collectons notamment :
                    nom, prénom, adresse e-mail, date de naissance, ville, photo de profil, et les données
                    relatives à votre activité sur la Plateforme.<br><br>

                    <strong>7.3 Finalités</strong><br>
                    Vos données sont utilisées pour : gérer votre compte, vous mettre en relation avec d'autres membres,
                    vous envoyer des notifications liées au service, assurer la sécurité de la Plateforme,
                    et améliorer nos fonctionnalités.<br><br>

                    <strong>7.4 Durée de conservation</strong><br>
                    Vos données sont conservées pendant toute la durée de votre inscription, puis supprimées
                    dans un délai de 30 jours suivant la clôture de votre compte, sauf obligation légale contraire.<br><br>

                    <strong>7.5 Vos droits</strong><br>
                    Conformément au RGPD, vous disposez d'un droit d'accès, de rectification, d'effacement,
                    de portabilité et d'opposition concernant vos données personnelles. Pour exercer ces droits,
                    contactez-nous via la <a href="/sharetime/public/?page=contact" style="color:var(--orange); font-weight:600;">page contact</a>.
                    Vous pouvez également introduire une réclamation auprès de la CNIL (www.cnil.fr).
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">8. Modération et sanctions</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    ShareTime se réserve le droit de modérer, modifier ou supprimer tout contenu qui contreviendrait
                    aux présentes CGU, sans préavis ni indemnité.<br><br>
                    En cas de violation grave ou répétée des CGU, ShareTime peut :<br><br>
                    • Suspendre temporairement votre compte ;<br>
                    • Supprimer définitivement votre compte et l'ensemble de vos données ;<br>
                    • Signaler les faits aux autorités compétentes si la loi l'exige.<br><br>
                    Vous pouvez signaler tout contenu inapproprié ou tout comportement abusif en contactant
                    l'équipe ShareTime via la <a href="/sharetime/public/?page=contact" style="color:var(--orange); font-weight:600;">page contact</a>.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">9. Limitation de responsabilité</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    ShareTime met tout en œuvre pour assurer la disponibilité et la sécurité de la Plateforme,
                    mais ne saurait être tenu responsable :<br><br>
                    • Des interruptions de service dues à des opérations de maintenance ou à des incidents techniques ;<br>
                    • Des dommages résultant d'une utilisation frauduleuse de la Plateforme par un tiers ;<br>
                    • Des événements ou activités organisés par les utilisateurs, de leur contenu ou de leurs conséquences ;<br>
                    • Des pertes de données liées à des circonstances indépendantes de notre volonté.<br><br>
                    La responsabilité de ShareTime est limitée aux seuls dommages directs prouvés, à l'exclusion
                    de tout dommage indirect, perte d'exploitation ou préjudice moral.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">10. Modification des CGU et résiliation</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    <strong>10.1 Modification</strong><br>
                    ShareTime se réserve le droit de modifier les présentes CGU à tout moment. Les utilisateurs seront
                    informés de toute modification substantielle par notification sur la Plateforme ou par e-mail.
                    La poursuite de l'utilisation du service après notification vaut acceptation des nouvelles CGU.<br><br>

                    <strong>10.2 Résiliation à l'initiative de l'utilisateur</strong><br>
                    Vous pouvez supprimer votre compte à tout moment depuis votre profil. Cette suppression entraîne
                    l'effacement de vos données personnelles dans un délai de 30 jours.<br><br>

                    <strong>10.3 Résiliation à l'initiative de ShareTime</strong><br>
                    ShareTime peut résilier votre accès en cas de violation des présentes CGU, sans préavis
                    ni indemnité.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">11. Droit applicable et juridiction</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    Les présentes CGU sont soumises au droit français. En cas de litige relatif à leur interprétation
                    ou à leur exécution, les parties s'engagent à rechercher une solution amiable avant tout recours judiciaire.<br><br>
                    À défaut d'accord amiable, tout litige sera soumis à la compétence exclusive des tribunaux français.
                </p>
            </section>

            <section style="background:white; border:1.5px solid var(--gray-200); border-radius:var(--radius-lg); padding:28px;">
                <h2 style="color:var(--navy); margin-bottom:12px; font-size:1.15rem;">12. Contact</h2>
                <p style="color:var(--gray-700); line-height:1.75;">
                    Pour toute question relative aux présentes CGU ou à l'utilisation de vos données personnelles,
                    vous pouvez contacter l'équipe ShareTime via la
                    <a href="/sharetime/public/?page=contact" style="color:var(--orange); font-weight:600;">page contact</a>.
                </p>
            </section>

        </div>
    <?php endif; ?>

    <!-- Lien de retour vers la page d'inscription -->
    <p style="margin-top:36px;">
        <a href="/sharetime/public/?page=inscription" class="btn btn-outline-navy">
            ← Retour à l'inscription
        </a>
    </p>

</main>
