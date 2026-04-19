<h1 style="text-align:center; margin-top:40px;">Connexion</h1>

<?php if (!empty($error)): ?>
    <p style="
        text-align:center;
        color:#c62828;
        font-weight:600;
        margin-top:20px;
    ">
        <?= htmlspecialchars($error) ?>
    </p>
<?php endif; ?>

<form method="POST" action="/APP_Dev_Web/public/index.php?page=connexion" style="
    max-width:400px;
    margin:40px auto;
    display:flex;
    flex-direction:column;
    gap:15px;
">
    <input type="email" name="email" placeholder="Email" required style="padding:10px;">
    
    <input type="password" name="password" placeholder="Mot de passe" required style="padding:10px;">

    <button type="submit" style="
        padding:12px;
        background:#FF7A00;
        color:white;
        border:none;
        border-radius:6px;
        cursor:pointer;
    ">
        Se connecter
    </button>
</form>