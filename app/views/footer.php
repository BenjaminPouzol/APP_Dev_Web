<footer style="background:#1E3A6E; color:white; padding:40px 0 20px;">
    <div class="container">
        <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:36px; margin-bottom:32px; flex-wrap:wrap;">
            <div>
                <div style="font-family:'Poppins',sans-serif; font-size:1.3rem; font-weight:800; margin-bottom:12px;">
                    <span style="color:#E8811A;">Share</span><span style="color:white;">Time</span>
                </div>
                <p style="font-size:0.83rem; color:rgba(255,255,255,0.45); line-height:1.6; max-width:260px;">
                    La plateforme de partage et d'organisation d'activités entre particuliers.
                </p>
            </div>
            <div>
                <h4 style="font-size:0.78rem; font-weight:600; color:rgba(255,255,255,0.65); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:14px;">Activités</h4>
                <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:8px;">
                    <li><a href="/sharetime/public/?page=activites" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Toutes les activités</a></li>
                    <li><a href="/sharetime/public/?page=creer" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Créer une activité</a></li>
                </ul>
            </div>
            <div>
                <h4 style="font-size:0.78rem; font-weight:600; color:rgba(255,255,255,0.65); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:14px;">Compte</h4>
                <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:8px;">
                    <?php if (isset($_SESSION['user'])): ?>
                        <li><a href="/sharetime/public/?page=profil" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Mon profil</a></li>
                        <li><a href="/sharetime/public/?page=logout" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Déconnexion</a></li>
                    <?php else: ?>
                        <li><a href="/sharetime/public/?page=inscription" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Inscription</a></li>
                        <li><a href="/sharetime/public/?page=connexion" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Connexion</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div>
                <h4 style="font-size:0.78rem; font-weight:600; color:rgba(255,255,255,0.65); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:14px;">Légal</h4>
                <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:8px;">
                    <li><a href="/sharetime/public/?page=faq" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">FAQ</a></li>
                    <li><a href="/sharetime/public/?page=contact" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Contact</a></li>
                    <li><a href="/sharetime/public/?page=cgu" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">CGU</a></li>
                    <li><a href="/sharetime/public/?page=mentions" style="font-size:0.82rem; color:rgba(255,255,255,0.4); text-decoration:none;" onmouseover="this.style.color='#E8811A'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">Mentions légales</a></li>
                </ul>
            </div>
        </div>
        <div style="border-top:1px solid rgba(255,255,255,0.08); padding-top:18px; font-size:0.78rem; color:rgba(255,255,255,0.25); text-align:center;">
            © <?= date('Y') ?> ShareTime — Tous droits réservés
        </div>
    </div>
</footer>

</body>
</html>
