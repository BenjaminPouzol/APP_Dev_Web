<?php

// ── CSRF ─────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_check(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Requête invalide. Veuillez recharger la page et réessayer.');
    }
}

// ── RÔLES ─────────────────────────────────────────────────────
function is_admin(): bool {
    return isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin', 'owner']);
}
function is_owner(): bool {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'owner';
}
function require_admin(): void {
    if (!is_admin()) { header('Location: /sharetime/public/'); exit; }
}
function require_owner(): void {
    if (!is_owner()) { header('Location: /sharetime/public/?page=admin'); exit; }
}

// ── Notifications ─────────────────────────────────────────────
function notify(PDO $pdo, int $user_id, string $type, string $title, string $content, ?int $activity_id = null): void {
    try {
        $pdo->prepare("INSERT INTO notifications (user_id, activity_id, type, title, content) VALUES (:u, :a, :t, :ti, :c)")
            ->execute(['u' => $user_id, 'a' => $activity_id, 't' => $type, 'ti' => $title, 'c' => $content]);
    } catch (\Throwable $e) {}
}

// ── Upload image ──────────────────────────────────────────────
function upload_image(string $field, string $dest_dir): ?string {
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) throw new \RuntimeException("Format non supporté. Utilisez JPG, PNG, GIF ou WebP.");
    if ($_FILES[$field]['size'] > 2 * 1024 * 1024) throw new \RuntimeException("Image trop volumineuse (max 2 Mo).");
    $filename = uniqid('img_', true) . '.' . $allowed[$mime];
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], rtrim($dest_dir, '/') . '/' . $filename)) {
        throw new \RuntimeException("Erreur lors de l'enregistrement de l'image.");
    }
    return $filename;
}

// ── Badge rôle ────────────────────────────────────────────────
function role_badge(string $role, bool $banned = false): string {
    if ($banned) {
        return '<span style="background:#FEE2E2;color:#DC2626;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Suspendu</span>';
    }
    switch ($role) {
        case 'owner': return '<span style="background:#FEF3E2;color:#E8811A;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Propriétaire</span>';
        case 'admin': return '<span style="background:#EBF0F8;color:#1E3A6E;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Admin</span>';
        default:      return '<span style="background:#F3F4F6;color:#6B7280;padding:3px 12px;border-radius:99px;font-size:0.75rem;font-weight:700;">Membre</span>';
    }
}

// ── Nav admin ─────────────────────────────────────────────────
function admin_nav(string $current): void {
    $tabs = [
        'admin'            => ['📊', 'Tableau de bord'],
        'admin_users'      => ['👥', 'Utilisateurs'],
        'admin_activities' => ['🎯', 'Activités'],
        'admin_logs'       => ['📋', 'Logs'],
    ];
    echo '<div style="background:white;border-bottom:2px solid var(--gray-200);margin-bottom:32px;">';
    echo '<div class="container" style="display:flex;gap:0;overflow-x:auto;">';
    foreach ($tabs as $p => [$icon, $label]) {
        $active = $p === $current;
        $style  = 'padding:14px 20px;font-weight:600;font-size:0.9rem;text-decoration:none;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;border-bottom:3px solid ' . ($active ? 'var(--orange)' : 'transparent') . ';color:' . ($active ? 'var(--navy)' : 'var(--gray-500)') . ';transition:all 0.15s;';
        echo "<a href='/sharetime/public/?page={$p}' style='{$style}'>{$icon} {$label}</a>";
    }
    echo '</div></div>';
}

// ── Log d'action admin ────────────────────────────────────────
function log_admin_action(PDO $pdo, string $action, string $target_type, int $target_id, string $details = ''): void {
    if (!isset($_SESSION['user'])) return;
    try {
        $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id, details) VALUES (:a, :ac, :tt, :ti, :d)")
            ->execute(['a' => (int)$_SESSION['user']['id'], 'ac' => $action, 'tt' => $target_type, 'ti' => $target_id, 'd' => $details]);
    } catch (\Throwable $e) {}
}
