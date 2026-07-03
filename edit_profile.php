<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

// Auth guard
$userId = auth_current_user_id();
if ($userId === null) {
    header('Location: login.php');
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch current user
$stmt = $pdo->prepare('SELECT id, display_name, email, is_guest FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: logout.php');
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $displayName = trim((string)($_POST['display_name'] ?? ''));

    if ($displayName === '') {
        $error = 'Name is required.';
    } else {
        try {
            // Update display name only
            $upd = $pdo->prepare('UPDATE users SET display_name = :dn WHERE id = :id');
            $upd->execute([':dn' => $displayName, ':id' => $userId]);

            $success = 'Profile updated.';

            // Refresh user
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DVibes - Edit Profile</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= $GLOBALS['__appJsVersion'] ?? time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <div class="app-container">
        <div class="main-content">
            <div class="page-content" style="max-width: 520px; margin: 40px auto; padding: 0 16px;">
                <div class="auth-card" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 20px;">
                    <h2 style="margin-top:0;">Edit Profile</h2>

                    <?php if ($error): ?>
                        <div style="background: rgba(255,0,0,0.15); border: 1px solid rgba(255,0,0,0.25); color:#ffb3b3; padding:10px 12px; border-radius: 12px; margin-bottom: 12px;">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div style="background: rgba(29,185,84,0.14); border: 1px solid rgba(29,185,84,0.35); color:#d6ffe7; padding:10px 12px; border-radius: 12px; margin-bottom: 12px;">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="edit_profile.php">
                        <label style="display:block; margin: 12px 0 6px;">Name</label>
                        <input
                            type="text"
                            name="display_name"
                            required
                            value="<?= htmlspecialchars($user['display_name'] ?? '') ?>"
                            autocomplete="name"
                            style="width:100%; padding:10px 12px; border-radius:12px; border:1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.25); color:#fff;" />

                        <div style="margin-top:16px; display:flex; gap:10px;">
                            <button type="submit" class="primary-btn" style="flex:1;">Save Changes</button>
                            <a href="index.php" class="secondary-btn" style="display:inline-flex; align-items:center; justify-content:center; padding:0 14px; text-decoration:none; border-radius:12px; border:1px solid rgba(255,255,255,0.15); color:#fff;">
                                Back
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>