<?php
// Cache-busting versions for static assets (based on file mtime).
$__cssPath = __DIR__ . '/../assets/css/style.css';
$__jsPath  = __DIR__ . '/../assets/js/app.js';
$__cssV    = file_exists($__cssPath) ? filemtime($__cssPath) : time();
$__jsV     = file_exists($__jsPath)  ? filemtime($__jsPath)  : time();
$GLOBALS['__appJsVersion'] = $__jsV;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DVibes - Music Streaming</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= $__cssV ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <meta name="description" content="DVibes - Modern music streaming experience">
</head>

<body>
    <div class="app-container">
        <!-- Sidebar (disabled for now - keep code for future re-enable) -->
        <div class="sidebar" style="display:none;">
            <div class="logo">
                <svg class="logo-mark" viewBox="0 0 40 40" width="36" height="36" aria-hidden="true">
                    <defs>
                        <linearGradient id="dvLogoGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#1ed760" />
                            <stop offset="100%" stop-color="#0f8a3d" />
                        </linearGradient>
                    </defs>
                    <circle cx="20" cy="20" r="19" fill="url(#dvLogoGrad)" />
                    <g fill="#0a0a0a">
                        <rect x="9" y="16" width="2.8" height="8" rx="1.4" />
                        <rect x="14" y="13" width="2.8" height="14" rx="1.4" />
                        <rect x="19" y="9" width="2.8" height="22" rx="1.4" />
                        <rect x="24" y="13" width="2.8" height="14" rx="1.4" />
                        <rect x="29" y="16" width="2.8" height="8" rx="1.4" />
                    </g>
                </svg>
                <span>DVibes</span>
            </div>
            <button class="close-btn" id="closeMenuBtn"><i class="fas fa-times"></i></button>
            <nav class="nav-menu">
                <a href="index.php" class="nav-item active"><i class="fas fa-home"></i> Home</a>
                <div class="genres-list" id="genresList"></div>
            </nav>
        </div>

        <!-- Sidebar backdrop (disabled for now) -->
        <div class="sidebar-backdrop" id="sidebarBackdrop" style="display:none;"></div>


        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar (sticky) -->
            <div class="top-bar">
                <div class="header-left">
                    <div class="logo header-logo" aria-label="DVibes">
                        <svg class="logo-mark" viewBox="0 0 40 40" width="36" height="36" aria-hidden="true">
                            <defs>
                                <linearGradient id="dvLogoGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#1ed760" />
                                    <stop offset="100%" stop-color="#0f8a3d" />
                                </linearGradient>
                            </defs>
                            <circle cx="20" cy="20" r="19" fill="url(#dvLogoGrad)" />
                            <g fill="#0a0a0a">
                                <rect x="9" y="16" width="2.8" height="8" rx="1.4" />
                                <rect x="14" y="13" width="2.8" height="14" rx="1.4" />
                                <rect x="19" y="9" width="2.8" height="22" rx="1.4" />
                                <rect x="24" y="13" width="2.8" height="14" rx="1.4" />
                                <rect x="29" y="16" width="2.8" height="8" rx="1.4" />
                            </g>
                        </svg>
                        <span>DVibes</span>
                    </div>
                </div>

                <div class="search-container" id="searchContainer">
                    <input type="text" id="searchInput" placeholder="Search songs, movies..." class="search-input" autocomplete="off">
                    <button id="clearSearchBtn" type="button" class="search-clear-btn" title="Clear search"><i class="fas fa-times"></i></button>
                    <button id="searchBtn" type="button"><i class="fas fa-search"></i></button>
                    <div class="search-suggestions" id="searchSuggestions" style="display: none;"></div>
                </div>

                <div class="top-controls">
                    <button class="btn-icon" id="menuBtn" aria-label="Toggle menu" style="display:none;"><i class="fas fa-bars"></i></button>

                    <?php
                    require_once __DIR__ . '/auth.php';
                    $userRow = null;
                    $isGuestUser = false;
                    if (auth_current_user_id() !== null) {
                        $userRow = auth_current_user_row($pdo);
                        $isGuestUser = !empty($userRow['is_guest']);
                    }
                    ?>

                    <div style="display:flex; align-items:center; gap:12px;">
                        <div class="dev-credit" title="Developed by Dilip Vishwakarma">
                            <i class="fas fa-code"></i>
                            <span>Developed by <strong>Dilip Vishwakarma</strong></span>
                        </div>

                        <div class="auth-links">
                            <?php if ($userRow && !$isGuestUser): ?>
                                <?php require_once __DIR__ . '/user_menu.php';
                                render_user_avatar_dropdown($userRow); ?>
                            <?php else: ?>
                                <a href="login.php" class="secondary-btn header-auth-btn">Login</a>
                                <a href="signup.php" class="primary-btn header-auth-btn">Sign up</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                window.DVIBES = {
                    isLoggedIn: <?= $userRow ? 'true' : 'false' ?>,
                    isGuest: <?= ($userRow && !empty($userRow['is_guest'])) ? 'true' : 'false' ?>
                };
            </script>

            <!-- Page Content -->
            <div class="page-content">