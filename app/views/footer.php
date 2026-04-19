<footer>
    <div class="footer-column">
        <div>
            <a href="/sharetime/public/">
                <span class="logo-Share">Share</span><span class="logo-Time">Time</span>
            </a>
            <p>La plateforme de partage et d'organisation d'activités entre particuliers. Partageons l'instant.</p>
        </div>
        <div>
            <h3>Activités</h3>
            <p><a href="/sharetime/public/?page=activites">Toutes les activités</a></p>
            <p><a href="/sharetime/public/?page=creer">Créer une activité</a></p>
        </div>
        <div>
            <h3>Compte</h3>
            <?php if (isset($_SESSION['user'])): ?>
                <p><a href="/sharetime/public/?page=profil">Mon profil</a></p>
                <p><a href="/sharetime/public/?page=logout">Déconnexion</a></p>
            <?php else: ?>
                <p><a href="/sharetime/public/?page=inscription">Inscription</a></p>
                <p><a href="/sharetime/public/?page=connexion">Connexion</a></p>
            <?php endif; ?>
        </div>
        <div>
            <h3>Aide</h3>
            <p><a href="/sharetime/public/?page=faq">FAQ</a></p>
            <p><a href="/sharetime/public/?page=contact">Contact</a></p>
        </div>
        <div>
            <h3>Légal</h3>
            <p><a href="/sharetime/public/?page=cgu">CGU</a></p>
            <p><a href="/sharetime/public/?page=mentions">Mentions légales</a></p>
        </div>
    </div>
    <p style="margin-top: 20px; color: #aaa; font-size: 0.85rem;">
        © <?= date('Y') ?> ShareTime — Tous droits réservés
    </p>
</footer>

</body>
</html>