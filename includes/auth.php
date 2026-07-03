<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    // Secure flags are environment-dependent; safe defaults for local XAMPP.
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function auth_current_user_id(): ?int
{
    if (isset($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }

    // If session is not present, try remember-me cookie.
    if (!empty($_COOKIE['dvibes_remember_uid'])) {
        $uid = (int)$_COOKIE['dvibes_remember_uid'];
        if ($uid > 0) {
            $_SESSION['user_id'] = $uid;
            return $uid;
        }
    }

    return null;
}


function auth_current_user_row($pdo): ?array
{
    $uid = auth_current_user_id();
    if (!$uid) return null;
    $stmt = $pdo->prepare('SELECT id, display_name, email, is_guest FROM users WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $uid, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    return $row ?: null;
}

function auth_require_login()
{
    if (auth_current_user_id() === null) {
        header('Location: login.php');
        exit;
    }
}

function auth_login_user($pdo, string $email, string $password, bool $remember = false): array
{
    $email = trim($email);
    if ($email === '') {
        return ['ok' => false, 'error' => 'Email is required.'];
    }

    $stmt = $pdo->prepare('SELECT id, password_hash, display_name, email, is_guest FROM users WHERE email = :email AND is_guest = 0 LIMIT 1');
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch();

    if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Invalid email or password.'];
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];

    if ($remember) {
        // Lightweight remember-me (session id based). For production use a token table + rotation.
        // Here we store user_id in a cookie for convenience.
        $cookieValue = (string)(int)$user['id'];
        setcookie('dvibes_remember_uid', $cookieValue, [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => false,
        ]);
    }

    return ['ok' => true, 'user' => $user];
}

function auth_signup_user($pdo, string $displayName, string $email, string $password): array
{
    $displayName = trim($displayName);
    $email = trim($email);

    if ($displayName === '') {
        return ['ok' => false, 'error' => 'Name is required.'];
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'A valid email is required.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare('INSERT INTO users (display_name, email, password_hash, is_guest) VALUES (:dn, :email, :ph, 0)');
        $stmt->bindValue(':dn', $displayName, PDO::PARAM_STR);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':ph', $passwordHash, PDO::PARAM_STR);
        $stmt->execute();
        $userId = (int)$pdo->lastInsertId();

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;

        $stmt2 = $pdo->prepare('SELECT id, display_name, email, is_guest FROM users WHERE id = :id LIMIT 1');
        $stmt2->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt2->execute();
        $user = $stmt2->fetch();

        return ['ok' => true, 'user' => $user];
    } catch (PDOException $e) {
        // 23000 = integrity constraint violation (duplicate email)
        if (isset($e->errorInfo[0]) && (string)$e->errorInfo[0] === '23000') {
            return ['ok' => false, 'error' => 'Email already exists. Please login.'];
        }
        return ['ok' => false, 'error' => 'Signup failed.'];
    }
}

function auth_guest_login($pdo): array
{
    // Guest identification per browser session. We create a guest user row for each new session.
    // If you prefer reusing one global guest, we can change this.
    $displayName = 'Guest';

    $guestEmail = 'guest+' . session_id() . '@dvibes.local';

    // Try to find existing guest by current session-specific email.
    $stmt = $pdo->prepare('SELECT id, display_name, email, is_guest FROM users WHERE email = :email AND is_guest = 1 LIMIT 1');
    $stmt->bindValue(':email', $guestEmail, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch();

    if (!$user) {
        $stmtIns = $pdo->prepare('INSERT INTO users (display_name, email, password_hash, is_guest) VALUES (:dn, :email, NULL, 1)');
        $stmtIns->bindValue(':dn', $displayName, PDO::PARAM_STR);
        $stmtIns->bindValue(':email', $guestEmail, PDO::PARAM_STR);
        $stmtIns->execute();
        $userId = (int)$pdo->lastInsertId();

        $stmt2 = $pdo->prepare('SELECT id, display_name, email, is_guest FROM users WHERE id = :id LIMIT 1');
        $stmt2->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt2->execute();
        $user = $stmt2->fetch();
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];

    return ['ok' => true, 'user' => $user];
}

function auth_logout()
{
    $_SESSION = [];

    // Clear remember-me + prefill cookies
    // (Also safe if they were never set.)
    setcookie('dvibes_remember_uid', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => false,
    ]);

    setcookie('dvibes_last_email', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => false,
        'samesite' => 'Lax',
        'secure' => false,
    ]);

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool)$params['secure'],
            (bool)$params['httponly']
        );
    }

    session_destroy();
}
