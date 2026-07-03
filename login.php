<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$error = null;

$prefillEmail = $_COOKIE['dvibes_last_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $remember = isset($_POST['remember_me']) && ($_POST['remember_me'] === '1' || $_POST['remember_me'] === 'on');
    $res = auth_login_user($pdo, $email, $password, $remember);
    // If remember-me is enabled, prefill login email for next visit
    // (No password stored; only email + remember flag)
    if ($remember && !empty($email)) {
        setcookie('dvibes_last_email', $email, [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'httponly' => false,
            'samesite' => 'Lax',
            'secure' => false,
        ]);
    }


    if ($res['ok']) {

        header('Location: index.php');
        exit;
    }
    $error = $res['error'] ?? 'Login failed.';
}

$css = isset($GLOBALS['__appJsVersion']) ? '' : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DVibes - Login</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= $GLOBALS['__appJsVersion'] ?? time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <div class="app-container">
        <div class="main-content">
            <div class="page-content" style="max-width: 420px; margin: 40px auto; padding: 0 16px;">
                <div class="auth-card" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 20px;">
                    <h2 style="margin-top:0;">Login</h2>

                    <?php if ($error): ?>
                        <div style="background: rgba(255,0,0,0.15); border: 1px solid rgba(255,0,0,0.25); color:#ffb3b3; padding:10px 12px; border-radius: 12px; margin-bottom: 12px;">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="login.php">
                        <label style="display:block; margin: 12px 0 6px;">Email</label>
                        <input type="email" name="email" required autocomplete="email" value="<?= htmlspecialchars($prefillEmail) ?>" style="width:100%; padding:10px 12px; border-radius:12px; border:1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.25); color:#fff;" />

                        <label style="display:block; margin: 12px 0 6px;">Password</label>
                        <input type="password" name="password" required autocomplete="current-password" style="width:100%; padding:10px 12px; border-radius:12px; border:1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.25); color:#fff;" />

                        <div style="margin-top:12px; display:flex; align-items:center; gap:10px;">
                            <?php $rememberChecked = !empty($_COOKIE['dvibes_remember_uid']); ?>
                            <input type="checkbox" name="remember_me" id="rememberMe" value="1" style="width:18px; height:18px;" <?= $rememberChecked ? 'checked' : '' ?>>
                            <label for="rememberMe" style="color:#b3b3b3; font-size:0.92rem; cursor:pointer;">Remember me</label>
                        </div>

                        <div style="margin-top:16px; display:flex; gap:10px;">
                            <button type="submit" class="primary-btn" style="flex:1;">Login</button>
                            <a href="signup.php" class="secondary-btn" style="display:inline-flex; align-items:center; justify-content:center; padding:0 14px; text-decoration:none; border-radius:12px; color:#fff;">Sign up</a>
                        </div>
                    </form>


                    <div style="margin-top:18px;">
                        <form method="post" action="guest_login.php">
                            <button type="submit" class="secondary-btn btn-block" style="padding:12px 14px; border-radius:12px; border:1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.03); color:#fff; display:flex; gap:10px; align-items:center; justify-content:center;">
                                <i class="fas fa-user-secret"></i> Continue as Guest
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>