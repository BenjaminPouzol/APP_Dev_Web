<style>
.msg-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 0;
    height: calc(100vh - 180px);
    min-height: 500px;
    background: white;
    border: 1.5px solid var(--gray-200);
    border-radius: var(--radius-lg);
    overflow: hidden;
}
.msg-sidebar {
    border-right: 1.5px solid var(--gray-200);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.msg-sidebar-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--gray-100);
    font-weight: 700;
    color: var(--navy);
    font-size: 0.95rem;
    flex-shrink: 0;
}
.msg-conv-list {
    flex: 1;
    overflow-y: auto;
}
.msg-conv-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid var(--gray-100);
    transition: background 0.1s;
}
.msg-conv-item:hover  { background: var(--gray-50); }
.msg-conv-item.active { background: #EBF0F8; }
.msg-conv-avatar {
    width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, var(--navy), var(--navy-light));
    color: white; font-size: 1rem; font-weight: 700;
    overflow: hidden;
}
.msg-conv-avatar img { width: 100%; height: 100%; object-fit: cover; }
.msg-conv-info { flex: 1; min-width: 0; }
.msg-conv-name { font-weight: 600; font-size: 0.88rem; color: var(--gray-900); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.msg-conv-preview { font-size: 0.78rem; color: var(--gray-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
.msg-conv-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.msg-conv-time { font-size: 0.72rem; color: var(--gray-400); }
.msg-unread-badge {
    background: var(--orange); color: white;
    font-size: 0.65rem; font-weight: 700;
    min-width: 18px; height: 18px; border-radius: 99px;
    display: flex; align-items: center; justify-content: center; padding: 0 4px;
}

.msg-main {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.msg-conv-header {
    padding: 14px 20px;
    border-bottom: 1px solid var(--gray-100);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}
.msg-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.msg-bubble-row {
    display: flex;
    align-items: flex-end;
    gap: 8px;
}
.msg-bubble-row.sent { flex-direction: row-reverse; }
.msg-bubble {
    max-width: 65%;
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 0.88rem;
    line-height: 1.5;
    word-break: break-word;
}
.msg-bubble.received {
    background: var(--gray-100);
    color: var(--gray-900);
    border-bottom-left-radius: 4px;
}
.msg-bubble.sent {
    background: var(--navy);
    color: white;
    border-bottom-right-radius: 4px;
}
.msg-bubble-time { font-size: 0.7rem; color: var(--gray-400); flex-shrink: 0; margin-bottom: 4px; }
.msg-form-bar {
    padding: 14px 20px;
    border-top: 1px solid var(--gray-100);
    display: flex;
    gap: 10px;
    align-items: flex-end;
    flex-shrink: 0;
}
.msg-form-bar textarea {
    flex: 1;
    padding: 10px 14px;
    border: 1.5px solid var(--gray-300);
    border-radius: 10px;
    font-size: 0.9rem;
    font-family: inherit;
    resize: none;
    max-height: 120px;
    line-height: 1.4;
    transition: border-color 0.15s;
}
.msg-form-bar textarea:focus { outline: none; border-color: var(--navy); }

.msg-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--gray-400);
    gap: 12px;
}

@media (max-width: 768px) {
    .msg-layout {
        grid-template-columns: 1fr;
        height: auto;
        min-height: 0;
    }
    .msg-sidebar { border-right: none; border-bottom: 1.5px solid var(--gray-200); max-height: 260px; }
    <?php if ($conversation_user): ?>
    .msg-sidebar { display: none; }
    <?php endif; ?>
    .msg-main { min-height: 500px; }
}
</style>

<main class="container" style="padding:40px 0;">

    <div style="display:flex; align-items:center; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
        <?php if ($conversation_user): ?>
            <a href="/sharetime/public/?page=messages" style="color:var(--gray-400); text-decoration:none; font-size:1.2rem; line-height:1;" title="Retour">&larr;</a>
        <?php endif; ?>
        <h1 style="color:var(--navy); margin:0;">Messages</h1>
    </div>

    <div class="msg-layout">

        <!-- ── Sidebar : liste des conversations ── -->
        <div class="msg-sidebar">
            <div class="msg-sidebar-header">Conversations</div>
            <div class="msg-conv-list">
                <?php if (empty($conversations)): ?>
                    <div style="padding:32px 16px; text-align:center; color:var(--gray-400); font-size:0.85rem;">
                        Aucune conversation.<br>Visitez un profil pour envoyer un message.
                    </div>
                <?php endif; ?>
                <?php foreach ($conversations as $c):
                    $c_name  = htmlspecialchars($c['pseudo'] ?: $c['prenom']);
                    $c_init  = strtoupper(mb_substr($c['prenom'], 0, 1));
                    $c_time  = '';
                    if ($c['last_time']) {
                        $dt = new DateTime($c['last_time']);
                        $now = new DateTime();
                        $c_time = $dt->format('d/m') === $now->format('d/m')
                            ? $dt->format('H:i')
                            : $dt->format('d/m');
                    }
                    $is_active = ($conversation_user && (int)$conversation_user['idusers'] === (int)$c['idusers']);
                    $preview = mb_strimwidth(htmlspecialchars($c['last_content']), 0, 36, '…');
                    $is_mine = (int)$c['last_sender_id'] === (int)$_SESSION['user']['id'];
                ?>
                <a href="/sharetime/public/?page=messages&with=<?= $c['idusers'] ?>"
                   class="msg-conv-item <?= $is_active ? 'active' : '' ?>">
                    <div class="msg-conv-avatar">
                        <?php if (!empty($c['photo_profil'])): ?>
                            <img src="/sharetime/public/uploads/profils/<?= htmlspecialchars($c['photo_profil']) ?>" alt="">
                        <?php else: ?>
                            <?= $c_init ?>
                        <?php endif; ?>
                    </div>
                    <div class="msg-conv-info">
                        <div class="msg-conv-name"><?= $c_name ?></div>
                        <div class="msg-conv-preview"><?= $is_mine ? 'Vous : ' : '' ?><?= $preview ?></div>
                    </div>
                    <div class="msg-conv-meta">
                        <span class="msg-conv-time"><?= $c_time ?></span>
                        <?php if ($c['unread_count'] > 0 && !$is_active): ?>
                            <span class="msg-unread-badge"><?= min(99, $c['unread_count']) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Zone principale ── -->
        <div class="msg-main">
            <?php if ($conversation_user): ?>

                <!-- En-tête conversation -->
                <div class="msg-conv-header">
                    <div class="msg-conv-avatar" style="width:36px;height:36px;font-size:0.85rem;">
                        <?php if (!empty($conversation_user['photo_profil'])): ?>
                            <img src="/sharetime/public/uploads/profils/<?= htmlspecialchars($conversation_user['photo_profil']) ?>" alt="">
                        <?php else: ?>
                            <?= strtoupper(mb_substr($conversation_user['prenom'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;">
                        <a href="/sharetime/public/?page=profil&id=<?= $conversation_user['idusers'] ?>"
                           style="font-weight:700; color:var(--navy); text-decoration:none; font-size:0.95rem;">
                            <?= htmlspecialchars($conversation_user['prenom'] . ' ' . $conversation_user['nom']) ?>
                        </a>
                        <?php if (!empty($conversation_user['pseudo'])): ?>
                            <div style="font-size:0.78rem; color:var(--gray-400);">@<?= htmlspecialchars($conversation_user['pseudo']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bulles de messages -->
                <div class="msg-body" id="msgBody">
                    <?php if (empty($conversation_messages)): ?>
                        <div style="flex:1; display:flex; align-items:center; justify-content:center; color:var(--gray-400); font-size:0.9rem;">
                            Envoyez votre premier message !
                        </div>
                    <?php endif; ?>
                    <?php
                    $prev_day = null;
                    foreach ($conversation_messages as $msg):
                        $is_sent = (int)$msg['sender_id'] === (int)$_SESSION['user']['id'];
                        $dt      = new DateTime($msg['created_at']);
                        $day     = $dt->format('d/m/Y');
                        $time    = $dt->format('H:i');
                    ?>
                    <?php if ($day !== $prev_day): $prev_day = $day; ?>
                        <div style="text-align:center; margin:8px 0;">
                            <span style="background:var(--gray-100); color:var(--gray-500); font-size:0.73rem;
                                         padding:3px 12px; border-radius:99px;"><?= $day ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="msg-bubble-row <?= $is_sent ? 'sent' : 'received' ?>">
                        <?php if (!$is_sent): ?>
                            <div class="msg-conv-avatar" style="width:28px;height:28px;font-size:0.7rem;flex-shrink:0;">
                                <?php if (!empty($conversation_user['photo_profil'])): ?>
                                    <img src="/sharetime/public/uploads/profils/<?= htmlspecialchars($conversation_user['photo_profil']) ?>" alt="">
                                <?php else: ?>
                                    <?= strtoupper(mb_substr($conversation_user['prenom'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="msg-bubble <?= $is_sent ? 'sent' : 'received' ?>">
                                <?= nl2br(htmlspecialchars($msg['content'])) ?>
                            </div>
                            <div style="text-align:<?= $is_sent ? 'right' : 'left' ?>;">
                                <span class="msg-bubble-time"><?= $time ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Formulaire d'envoi -->
                <form method="post" action="/sharetime/public/?page=envoyer_message" class="msg-form-bar" id="msgForm"
                      onsubmit="this.querySelector('button[type=submit]').disabled=true;">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="receiver_id" value="<?= (int)$conversation_user['idusers'] ?>">
                    <textarea name="content" id="msgInput" placeholder="Votre message…" rows="1"
                              maxlength="1000" data-no-loading="1"
                              onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();document.getElementById('msgForm').submit();}"></textarea>
                    <button type="submit" class="btn btn-orange btn-sm" style="flex-shrink:0;">Envoyer</button>
                </form>

            <?php else: ?>
                <div class="msg-empty">
                    <span style="font-size:3rem;">✉️</span>
                    <p style="font-weight:600; color:var(--gray-600);">Sélectionnez une conversation</p>
                    <p style="font-size:0.85rem;">ou visitez un profil pour démarrer une discussion.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<script>
(function() {
    // Auto-scroll vers le bas de la conversation
    var body = document.getElementById('msgBody');
    if (body) body.scrollTop = body.scrollHeight;

    // Auto-resize du textarea
    var ta = document.getElementById('msgInput');
    if (ta) {
        ta.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
    }
})();
</script>
