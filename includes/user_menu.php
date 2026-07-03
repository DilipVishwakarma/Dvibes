<?php
require_once __DIR__ . '/auth.php';

function user_avatar_initials(?string $displayName): string
{
    $displayName = trim((string)($displayName ?? ''));
    if ($displayName === '') return 'U';

    $parts = preg_split('/\s+/', $displayName);
    $parts = array_values(array_filter($parts));

    if (count($parts) === 1) {
        $s = mb_substr($parts[0], 0, 2);
        return strtoupper($s);
    }

    $first = mb_substr($parts[0], 0, 1);
    $second = mb_substr($parts[count($parts) - 1], 0, 1);
    return strtoupper($first . $second);
}

function render_user_avatar_dropdown(?array $userRow)
{
    if (!$userRow) return;

    $name = htmlspecialchars($userRow['display_name'] ?? 'User');
    $initials = htmlspecialchars(user_avatar_initials($userRow['display_name'] ?? 'User'));

    // Client-side dropdown; avatar shows a theme-matching pill button.
    echo '<div class="user-menu">'
        . '  <button class="user-avatar" type="button" id="userAvatarBtn" aria-label="User menu" aria-haspopup="true">'
        . '    <span class="user-avatar-initials">' . $initials . '</span>'
        . '  </button>'
        . '  <div class="user-dropdown" id="userDropdown" role="menu" aria-labelledby="userAvatarBtn">'
        . '    <div class="user-dropdown-header">'
        . '      <div class="user-dropdown-name">' . $name . '</div>'
        . '      <div class="user-dropdown-sub">' . (!empty($userRow['is_guest']) ? 'Guest session' : 'Account') . '</div>'
        . '    </div>'
        . '    <button type="button" class="user-dropdown-item" data-user-panel="playlists">Playlists</button>'
        . '    <button type="button" class="user-dropdown-item" data-user-panel="history">History</button>'
        . '    <div class="user-dropdown-divider"></div>'
        . '    <a class="user-dropdown-item" href="edit_profile.php">Edit name</a>'
        . '    <a class="user-dropdown-item user-dropdown-danger" href="logout.php">Logout</a>'
        . '  </div>'
        . '</div>';
}
