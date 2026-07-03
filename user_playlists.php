<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

if (auth_current_user_id() === null) {
    header('Location: login.php');
    exit;
}

$userRow = auth_current_user_row($pdo);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DVibes - My Playlists</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="page-content" style="max-width: 980px; margin: 0 auto; padding: 24px;">
        <h2 style="margin: 0 0 16px;">My Playlists</h2>

        <div style="display:flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-bottom: 18px;">
            <input id="playlistNameInput" type="text" placeholder="New playlist name" style="flex: 1; min-width: 260px; padding:10px 12px; border-radius:12px; border:1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.25); color:#fff;" />
            <button id="createPlaylistBtn" class="primary-btn" style="padding: 10px 14px;">Create</button>
        </div>

        <div id="playlistsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px;">
            <!-- populated by JS -->
        </div>

        <div id="playlistsError" style="margin-top: 16px; display:none; background: rgba(255,0,0,0.15); border:1px solid rgba(255,0,0,0.25); color:#ffb3b3; padding:10px 12px; border-radius:12px;"></div>
    </div>

    <script>
        const dvibesUserId = <?php echo (int)auth_current_user_id(); ?>;

        async function apiGetPlaylists() {
            const res = await fetch('api/user_playlists.php', {
                method: 'GET'
            });
            if (!res.ok) {
                const txt = await res.text();
                throw new Error(`Failed to load playlists (${res.status}). ${txt}`);
            }
            return res.json();
        }

        async function apiCreatePlaylist(name) {
            const form = new FormData();
            form.append('action', 'create');
            form.append('name', name);
            const res = await fetch('api/user_playlists.php', {
                method: 'POST',
                body: form
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.error) {
                throw new Error(data.error || `Failed to create playlist (${res.status})`);
            }
            return data;
        }

        async function apiDeletePlaylist(playlistId) {
            const form = new FormData();
            form.append('action', 'delete');
            form.append('playlist_id', String(playlistId));
            const res = await fetch('api/user_playlists.php', {
                method: 'POST',
                body: form
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.error) {
                throw new Error(data.error || `Failed to delete playlist (${res.status})`);
            }
            return data;
        }

        async function apiRenamePlaylist(playlistId, name) {
            const form = new FormData();
            form.append('action', 'rename');
            form.append('playlist_id', String(playlistId));
            form.append('name', name);
            const res = await fetch('api/user_playlists.php', {
                method: 'POST',
                body: form
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.error) {
                throw new Error(data.error || `Failed to rename playlist (${res.status})`);
            }
            return data;
        }

        function showError(msg) {
            const el = document.getElementById('playlistsError');
            el.style.display = 'block';
            el.textContent = msg;
        }

        function showToast(message, type = 'success') {
            let toast = document.getElementById('playlistToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'playlistToast';
                toast.style.position = 'fixed';
                toast.style.right = '20px';
                toast.style.bottom = '20px';
                toast.style.background = 'rgba(0,0,0,0.8)';
                toast.style.color = '#fff';
                toast.style.padding = '10px 14px';
                toast.style.borderRadius = '8px';
                toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.4)';
                toast.style.zIndex = 9999;
                document.body.appendChild(toast);
            }
            toast.textContent = message;
            toast.style.opacity = '1';
            clearTimeout(window._playlistToastTimer);
            window._playlistToastTimer = setTimeout(() => {
                toast.style.opacity = '0';
            }, 2500);
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, (c) => ({
                '&': '&amp;',
                '<': '<',
                '>': '>',
                '"': '"',
                "'": '&#39;'
            } [c]));
        }

        function renderPlaylists(playlists) {
            const grid = document.getElementById('playlistsGrid');
            if (!Array.isArray(playlists) || playlists.length === 0) {
                grid.innerHTML = '<div style="grid-column: 1 / -1; opacity: 0.8;">No playlists yet.</div>';
                return;
            }

            grid.innerHTML = playlists.map(p => {
                return `
                <div style="border:1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.04); border-radius: 16px; padding: 14px;">
                    <div style="display:flex; justify-content: space-between; align-items: start; gap: 10px;">
                        <div>
                            <div style="font-weight: 700; margin-bottom: 6px;">${escapeHtml(p.name)}</div>
                            <div style="font-size: 0.9rem; opacity: 0.75;">Created: ${escapeHtml(p.created_at || '')}</div>
                        </div>
                            <div style="display:flex; gap: 8px; align-items: center;">
                                <button class="renamePlaylistBtn" data-playlist-id="${(int)p.id}" title="Rename" style="border:none; background: rgba(255,255,255,0.08); color:#fff; padding: 8px 10px; border-radius: 12px; cursor:pointer;">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="deletePlaylistBtn" data-playlist-id="${(int)p.id}" title="Delete" style="border:none; background: rgba(255,255,255,0.08); color:#fff; padding: 8px 10px; border-radius: 12px; cursor:pointer;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                    </div>
                </div>
            `;
            }).join('');

            grid.querySelectorAll('.deletePlaylistBtn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.getAttribute('data-playlist-id');
                    const ok = confirm('Delete this playlist?');
                    if (!ok) return;
                    try {
                        await apiDeletePlaylist(id);
                        await loadAndRender();
                    } catch (e) {
                        showError(e.message);
                    }
                });
            });

                grid.querySelectorAll('.renamePlaylistBtn').forEach(btn => {
                    btn.addEventListener('click', async (e) => {
                        const id = btn.getAttribute('data-playlist-id');
                        const card = btn.closest('div');
                        const titleEl = card.querySelector('div');
                        // Replace the title with an inline input
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.value = titleEl ? titleEl.textContent.trim() : '';
                        input.style.padding = '6px 8px';
                        input.style.borderRadius = '8px';
                        input.style.width = '220px';

                        const saveBtn = document.createElement('button');
                        saveBtn.textContent = 'Save';
                        saveBtn.style.marginLeft = '8px';
                        const cancelBtn = document.createElement('button');
                        cancelBtn.textContent = 'Cancel';
                        cancelBtn.style.marginLeft = '6px';

                        // Insert input and buttons after the title element
                        if (titleEl && titleEl.parentNode) {
                            titleEl.parentNode.insertBefore(input, titleEl.nextSibling);
                            titleEl.parentNode.insertBefore(saveBtn, input.nextSibling);
                            titleEl.parentNode.insertBefore(cancelBtn, saveBtn.nextSibling);
                            titleEl.style.display = 'none';
                        }

                        input.focus();

                        cancelBtn.addEventListener('click', () => {
                            if (titleEl) titleEl.style.display = '';
                            input.remove(); saveBtn.remove(); cancelBtn.remove();
                        });

                        saveBtn.addEventListener('click', async () => {
                            const newName = input.value.trim();
                            if (!newName) return;
                            try {
                                await apiRenamePlaylist(id, newName);
                                showToast('Playlist renamed');
                                await loadAndRender();
                            } catch (err) {
                                showError(err.message || 'Unable to rename playlist');
                            }
                        });
                    });
                });
        }

        async function loadAndRender() {
            document.getElementById('playlistsError').style.display = 'none';
            const playlists = await apiGetPlaylists();
            renderPlaylists(playlists);
        }

        document.getElementById('createPlaylistBtn').addEventListener('click', async () => {
            const input = document.getElementById('playlistNameInput');
            const name = input.value.trim();
            if (!name) {
                showError('Playlist name is required.');
                return;
            }
            try {
                await apiCreatePlaylist(name);
                input.value = '';
                await loadAndRender();
            } catch (e) {
                showError(e.message);
            }
        });

        document.getElementById('playlistNameInput').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('createPlaylistBtn').click();
            }
        });

        // Initial load
        loadAndRender().catch(e => showError(e.message));
    </script>
</body>

</html>