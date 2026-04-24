<main class="container" style="padding:48px 0; max-width:720px; margin:auto;">

    <div style="text-align:center; margin-bottom:40px;">
        <p style="font-size:0.78rem; font-weight:600; color:var(--orange); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">
            Support
        </p>
        <h1 style="color:var(--navy); margin-bottom:10px;">Foire aux questions</h1>
        <p style="color:var(--gray-500);">Vous avez une question ? Trouvez la réponse ici.</p>
    </div>

    <?php if (empty($faq_items)): ?>
        <p style="text-align:center; color:var(--gray-500);">
            Aucune question disponible pour le moment.
        </p>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:10px;">
            <?php foreach ($faq_items as $faq): ?>
            <div style="background:white; border:1.5px solid var(--gray-200); border-radius:12px; overflow:hidden;">
                <details>
                    <summary style="padding:18px 20px; cursor:pointer; font-weight:600;
                                    color:var(--gray-900); list-style:none;
                                    display:flex; justify-content:space-between; align-items:center;">
                        <?= htmlspecialchars($faq['question']) ?>
                        <span class="faq-icon" style="color:var(--orange); font-size:1.3rem; flex-shrink:0; margin-left:12px; transition:transform 0.2s;">+</span>
                    </summary>
                    <div style="padding:0 20px 18px; border-top:1px solid var(--gray-100);">
                        <p style="color:var(--gray-700); line-height:1.75; margin-top:14px;">
                            <?= nl2br(htmlspecialchars($faq['reponse'])) ?>
                        </p>
                    </div>
                </details>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<style>
details[open] > summary .faq-icon { transform: rotate(45deg); }
details > summary::-webkit-details-marker { display: none; }
</style>

    <div style="margin-top:48px; text-align:center; background:var(--orange-pale);
                border-radius:14px; padding:32px;">
        <h3 style="color:var(--navy); margin-bottom:8px;">Pas trouvé votre réponse ?</h3>
        <p style="color:var(--gray-600); margin-bottom:16px;">Notre équipe est là pour vous aider.</p>
        <a href="/sharetime/public/?page=contact" class="btn btn-orange">Nous contacter</a>
    </div>
</main>
