<?php
/**
 * auth.php - admin login, session, roles & permissions.
 *
 *   require_login();                 // top of every admin page
 *   require_perm('news.manage');     // page needs this permission
 *   if (can('donations.export')) {}  // show/hide a button
 */

/** Logged-in user row (with role) or null. Cached per request. */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $row = db_query(
            'SELECT u.id, u.name, u.email, u.role_id, u.status, u.must_change_password,
                    r.slug AS role_slug, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
            [$_SESSION['uid']]
        )->fetch();
        if ($row && $row['status'] === 'active') {
            $user = $row;
        } else {
            unset($_SESSION['uid']);
        }
    }
    return $user;
}

function is_super_admin(): bool
{
    $u = current_user();
    return $u && $u['role_slug'] === 'super_admin';
}

/** All permission codes of the current user (role permissions + personal overrides). */
function user_permissions(): array
{
    static $perms = null;
    if ($perms !== null) return $perms;
    $perms = [];
    $u = current_user();
    if (!$u) return $perms;

    $rows = db_rows(
        'SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
        [$u['role_id']]
    );
    foreach ($rows as $r) $perms[$r['code']] = true;

    $over = db_rows(
        'SELECT p.code, up.granted FROM user_permissions up JOIN permissions p ON p.id = up.permission_id WHERE up.user_id = ?',
        [$u['id']]
    );
    foreach ($over as $o) {
        if ((int) $o['granted'] === 1) $perms[$o['code']] = true;
        else unset($perms[$o['code']]);
    }
    return $perms;
}

function can(string $code): bool
{
    return isset(user_permissions()[$code]);
}

function forbidden(): never
{
    http_response_code(403);
    $page_title = 'Access denied';
    $active = '';
    require ROOT_PATH . '/includes/admin_header.php';
    echo '<div class="card"><h2>Access denied</h2><p>Is page ke liye aapke paas permission nahi hai. Zarurat ho to Super Admin se kahein.</p></div>';
    require ROOT_PATH . '/includes/admin_footer.php';
    exit;
}

function require_perm(string $code): void
{
    require_login();
    if (!can($code)) {
        audit('access_denied', 'permission', $code);
        forbidden();
    }
}

/** Call at the top of every admin page. Redirects to login if needed. */
function require_login(): array
{
    header('Cache-Control: no-store');
    $u = current_user();

    if ($u) {
        $idle = time() - (int) ($_SESSION['last_active'] ?? 0);
        $uaOk = hash_equals((string) ($_SESSION['ua'] ?? ''), hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($idle > SESSION_IDLE || !$uaOk) {
            auth_logout();
            flash('error', 'Session expire ho gaya. Dobara login karein.');
            redirect('admin/login.php');
        }
        $_SESSION['last_active'] = time();
        return $u;
    }
    redirect('admin/login.php');
}

/** Too many recent failures for this email or this IP? */
function login_throttled(string $email, string $ip): bool
{
    $byEmail = (int) db_value(
        "SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND created_at > (NOW() - INTERVAL 15 MINUTE)",
        [$email]
    );
    $byIp = (int) db_value(
        "SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > (NOW() - INTERVAL 15 MINUTE)",
        [$ip]
    );
    return $byEmail >= 5 || $byIp >= 15;
}

/** Try to log in. Returns [bool ok, string message]. */
function auth_login(string $email, string $password): array
{
    $email = strtolower(trim($email));
    $ip = client_ip();

    if ($email === '' || $password === '') return [false, 'Email aur password dono bharein.'];

    if (login_throttled($email, $ip)) {
        audit('login_blocked', 'users', null, null, ['email' => $email]);
        return [false, 'Bahut zyada galat koshish. 15 minute baad dobara try karein.'];
    }

    $user = db_query('SELECT id, password_hash, status FROM users WHERE email = ? LIMIT 1', [$email])->fetch();

    // always run a hash check so response time is the same whether the email exists or not
    $hash = $user['password_hash'] ?? hash_password('dummy-password-for-timing');
    $ok = password_verify($password, $hash) && $user && $user['status'] === 'active';

    db_query('INSERT INTO login_attempts (email, ip, success) VALUES (?,?,?)', [$email, $ip, $ok ? 1 : 0]);

    if (!$ok) {
        audit('login_failed', 'users', null, null, ['email' => $email]);
        return [false, 'Email ya password galat hai.'];
    }

    // success
    session_regenerate_id(true);
    unset($_SESSION['csrf']);
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['last_active'] = time();
    $_SESSION['ua'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

    if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
        db_query('UPDATE users SET password_hash = ? WHERE id = ?', [hash_password($password), $user['id']]);
    }
    db_query('UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [$ip, $user['id']]);
    db_query('DELETE FROM login_attempts WHERE email = ? AND success = 0', [$email]);
    audit('login', 'users', $user['id']);
    return [true, ''];
}

function auth_logout(): void
{
    if (!empty($_SESSION['uid'])) audit('logout', 'users', $_SESSION['uid']);
    $_SESSION = [];
    session_regenerate_id(true);
}
