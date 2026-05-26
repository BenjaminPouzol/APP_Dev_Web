<?php
/**
 * public/pages/faq.php — Foire aux questions
 *
 * Variables disponibles (préparées par index.php routing) :
 *   $faq_items : tableau des questions/réponses lues depuis la table `faq`
 *                (colonnes : question, reponse), triées par idfaq ASC
 *
 * Chaque entrée est affichée dans un élément HTML <details> natif (accordéon CSS pur).
 * Le signe "+" tourne à 45° via CSS pour former un "×" quand l'entrée est ouverte.
 */
?>
<!-- Conteneur principal centré avec une largeur maximale de 720px -->
<main class="container" style="padding:48px 0; max-width:720px; margin:auto;">

    <!-- Bloc d'en-tête : label "Support", titre et sous-titre de la page FAQ -->
    <div style="text-align:center; margin-bottom:40px;">
        <p style="font-size:0.78rem; font-weight:600; color:var(--orange); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">
            Support
        </p>
        <h1 style="color:var(--navy); margin-bottom:10px;">Foire aux questions</h1>
        <p style="color:var(--gray-500);">Vous avez une question ? Trouvez la réponse ici.</p>
    </div>

    <?php if (empty($faq_items)): // Vérifie si aucune question n'est disponible en base de données ?>
        <!-- Message affiché si la table FAQ est vide -->
        <p style="text-align:center; color:var(--gray-500);">
            Aucune question disponible pour le moment.
        </p>
    <?php else: // Sinon, affiche la liste des questions en accordéon ?>
        <!-- Conteneur vertical des questions/réponses -->
        <div style="display:flex; flex-direction:column; gap:10px;">
            <?php foreach ($faq_items as $faq): // Boucle sur chaque entrée de la FAQ ?>
            <!-- Carte blanche avec bordure pour chaque question/réponse -->
            <div style="background:white; border:1.5px solid var(--gray-200); border-radius:12px; overflow:hidden;">
                <!-- Élément accordéon natif HTML5 : cliquable sans JavaScript -->
                <details>
                    <!-- En-tête cliquable : affiche la question et l'icône "+" -->
                    <summary style="padding:18px 20px; cursor:pointer; font-weight:600;
                                    color:var(--gray-900); list-style:none;
                                    display:flex; justify-content:space-between; align-items:center;">
                        <?= htmlspecialchars($faq['question']) // Affiche la question en sécurisant les caractères spéciaux ?>
                        <!-- Icône "+" qui tourne à 45° via CSS pour former un "×" à l'ouverture -->
                        <span class="faq-icon" style="color:var(--orange); font-size:1.3rem; flex-shrink:0; margin-left:12px; transition:transform 0.2s;">+</span>
                    </summary>
                    <!-- Corps de l'accordéon : visible uniquement quand l'élément est ouvert -->
                    <div style="padding:0 20px 18px; border-top:1px solid var(--gray-100);">
                        <p style="color:var(--gray-700); line-height:1.75; margin-top:14px;">
                            <?= nl2br(htmlspecialchars($faq['reponse'])) // Affiche la réponse en convertissant les sauts de ligne en <br> ?>
                        </p>
                    </div>
                </details>
            </div>
            <?php endforeach; // Fin de la boucle sur les entrées de la FAQ ?>
        </div>
    <?php endif; // Fin de la condition liste vide / liste pleine ?>

<style>
/* Fait pivoter l'icône "+" à 45° quand l'accordéon est ouvert, formant un "×" */
details[open] > summary .faq-icon { transform: rotate(45deg); }
/* Masque le triangle natif du navigateur sur le résumé (Chrome/Safari) */
details > summary::-webkit-details-marker { display: none; }
</style>

    <!-- Bloc d'appel à l'action si la réponse n'a pas été trouvée dans la FAQ -->
    <div style="margin-top:48px; text-align:center; background:var(--orange-pale);
                border-radius:14px; padding:32px;">
        <h3 style="color:var(--navy); margin-bottom:8px;">Pas trouvé votre réponse ?</h3>
        <p style="color:var(--gray-600); margin-bottom:16px;">Notre équipe est là pour vous aider.</p>
        <!-- Lien vers la page de contact -->
        <a href="/sharetime/public/?page=contact" class="btn btn-orange">Nous contacter</a>
    </div>
</main>
