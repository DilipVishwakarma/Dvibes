class MusicStreamApp {
    constructor() {
        this.audio = document.getElementById('audioPlayer');
        this.currentSong = null;
        this.isPlaying = false;
        this.currentSongs = [];
        this.currentIndex = 0;
        this.playbackOrder = [];
        this.playbackIndex = 0;
        this.repeatMode = 'off';
        this.shuffleEnabled = false;
        this.skipCount = 0;
        this.errorCount = 0;
        this.recordedHistorySongIds = new Set();
        this.userPanelMode = null;
        this.userPanelSongId = null;
        this.userPanelPlaylistId = null;
        this.userPanelPlaylistName = '';

        // Dashboard view mode (popular vs user playlist inside main content)
        this.dashboardMode = 'popular';
        this.dashboardPlaylistId = null;
        this.dashboardPlaylistName = '';

        // Share token cache { songId: token }
        this.shareTokenCache = {};

        // Playback save throttle
        this._lastPlaybackSave = 0;

        // Songs queued to play next during current playback
        this.nextUpQueue = [];
        this.queueResumeIndex = null;
        this._playingQueuedSong = false;

        // Toggle this flag to enable the in-page debug console + verbose logging.
        // Set to true while diagnosing issues; keep false in normal use.
        this.debugEnabled = false;

        // Pagination state
        this.currentOffset = 0;
        this.pageSize = 20;
        this.totalSongs = 0;
        // Stable shuffle order for paginated "Popular Songs" (must match PHP first paint)
        this.browseShuffleSeed = this.resolveBrowseShuffleSeed();

        this.init();
    }

    init() {
        this.bindEvents();
        this.loadGenres();
        this.setupAudioEvents();
        this.maybeShowGuestModal();
        // Show the debug console automatically while debug mode is enabled
        if (this.debugEnabled) {
            const dbg = document.getElementById('debugConsole');
            if (dbg) dbg.style.display = 'block';
        }
        this.debug('DVibes App initialized');
    }

    // Safely bind an event listener; logs a warning instead of throwing if the element is missing.
    bindEvent(elementOrId, eventName, handler, options) {
        const el = typeof elementOrId === 'string'
            ? document.getElementById(elementOrId)
            : elementOrId;
        if (!el) {
            this.debug(`bindEvent skipped: missing element "${elementOrId}" for "${eventName}"`, 'error');
            return false;
        }
        el.addEventListener(eventName, handler, options);
        return true;
    }

    createBrowseShuffleSeed() {
        const bytes = new Uint8Array(16);
        if (typeof window !== 'undefined' && window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(bytes);
        } else {
            for (let i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256);
        }
        return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
    }

    resolveBrowseShuffleSeed() {
        const w = typeof window !== 'undefined' ? window.browseShuffleSeed : null;
        if (w && typeof w === 'string' && /^[a-fA-F0-9]{16,64}$/.test(w)) {
            return w;
        }
        const created = this.createBrowseShuffleSeed();
        if (typeof window !== 'undefined') {
            window.browseShuffleSeed = created;
        }
        return created;
    }

    bindEvents() {
        // Player controls
        this.bindEvent('playPauseBtn', 'click', () => this.togglePlay());
        this.bindEvent('prevBtn', 'click', () => this.prevSong());
        this.bindEvent('nextBtn', 'click', () => this.nextSong());
        this.bindEvent('repeatBtn', 'click', () => this.toggleRepeat());
        this.bindEvent('shuffleBtn', 'click', () => this.toggleShuffle());

        // Progress bar
        const progressBar = document.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.addEventListener('click', (e) => {
                e.preventDefault();
                this.seek(e);
                this.updateProgress();
            });
        }

        // Volume
        this.bindEvent('volume', 'input', (e) => {
            this.audio.volume = e.target.value / 100;
        });

        // Search
        this.bindEvent('searchBtn', 'click', (e) => {
            e.preventDefault();
            this.search();
        });
        this.bindEvent('clearSearchBtn', 'click', (e) => {
            e.preventDefault();
            this.clearSearch();
        });
        this.bindEvent('searchInput', 'keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.search();
            }
        });
        this.bindEvent('searchInput', 'input', (e) => {
            const query = e.target.value.trim();
            if (query.length >= 2) {
                this.scheduleSuggestions(query);
            } else {
                this.clearSuggestions();
            }
        });
        this.bindEvent('searchContainer', 'click', (e) => {
            const suggestion = e.target.closest('.search-suggestion-item');
            if (suggestion) {
                e.preventDefault();
                const songId = suggestion.dataset.id;
                const title = suggestion.dataset.title;
                const input = document.getElementById('searchInput');
                if (input) input.value = title;
                this.clearSuggestions();
                if (songId) {
                    this.searchById(songId);
                } else {
                    this.search();
                }
            }
        });
        document.addEventListener('click', (e) => {
            const panelBtn = e.target.closest('[data-user-panel]');
            if (panelBtn) {
                e.preventDefault();
                e.stopPropagation();
                const panel = panelBtn.dataset.userPanel;
                if (panel) {
                    this.openUserPanel(panel);
                }
                return;
            }

            // Toggle user dropdown
            const avatarBtn = e.target.closest('#userAvatarBtn');
            if (avatarBtn) {
                const parent = avatarBtn.closest('.user-menu');
                if (parent) parent.classList.toggle('open');
                return;
            }

            // Close user dropdown when clicking outside
            const userMenu = e.target.closest('.user-menu');
            if (!userMenu) {
                document.querySelectorAll('.user-menu.open').forEach(m => m.classList.remove('open'));
            }

            // Search suggestions
            if (!e.target.closest('#searchContainer')) {
                this.clearSuggestions();
            }
        });

        this.bindEvent('closeUserPanelBtn', 'click', () => this.closeUserPanel());
        this.bindEvent('backToDashboardBtn', 'click', () => this.closeUserPanel());
        this.bindEvent('userPanelOverlay', 'click', (e) => {
            if (e.target.id === 'userPanelOverlay') {
                this.closeUserPanel();
            }
        });

        document.addEventListener('click', (e) => {
            const menuBtn = e.target.closest('.song-menu-btn');
            if (menuBtn) {
                e.preventDefault();
                e.stopImmediatePropagation();
                const card = menuBtn.closest('.song-card');
                const menu = card?.querySelector('.song-card-menu');
                document.querySelectorAll('.song-card-menu.open').forEach((item) => {
                    if (item !== menu) item.classList.remove('open');
                });
                menu?.classList.toggle('open');
                return;
            }

            if (!e.target.closest('.song-card-menu')) {
                document.querySelectorAll('.song-card-menu.open').forEach((item) => item.classList.remove('open'));
            }
        });

        document.addEventListener('click', (e) => {
            const option = e.target.closest('[data-song-option]');
            if (option) {
                e.preventDefault();
                e.stopImmediatePropagation();
                const songId = option.dataset.songId;
                const playlistId = Number(option.dataset.playlistId);
                if (option.dataset.songOption === 'add-to-playlist' && songId) {
                    this.openUserPanel('playlist-picker', songId);
                    return;
                }
                if (option.dataset.songOption === 'play-next' && songId) {
                    this.queueSongNext(Number(songId));
                    return;
                }
                if (option.dataset.songOption === 'share' && songId) {
                    this.shareSong(songId);
                    return;
                }
                if (option.dataset.songOption === 'remove-from-playlist' && Number.isFinite(playlistId) && songId) {
                    this.removeSongFromPlaylist(playlistId, Number(songId));
                    return;
                }
            }
        });

        document.addEventListener('click', (e) => {
            const playlistItem = e.target.closest('[data-playlist-id]');
            if (playlistItem && playlistItem.dataset.playlistId) {
                e.preventDefault();
                e.stopImmediatePropagation();
                const playlistId = Number(playlistItem.dataset.playlistId);
                if (!Number.isFinite(playlistId)) return;

                // If in "add to playlist" mode, keep existing behavior.
                const songId = this.userPanelSongId;
                if (this.userPanelMode === 'playlist-picker' && songId) {
                    this.addSongToPlaylist(playlistId, songId);
                    return;
                }

                // Otherwise: load playlist into main dashboard (Popular Songs section).
                const playlistName = playlistItem.dataset.playlistName || '';
                this.loadDashboardPlaylist(playlistId, playlistName).catch(err => {
                    this.showToast(err.message || 'Unable to load playlist', 'error');
                });
                this.closeUserPanel();
            }
        });

        document.addEventListener('click', (e) => {
            const removeSongBtn = e.target.closest('[data-remove-playlist-song]');
            if (removeSongBtn) {
                e.preventDefault();
                e.stopImmediatePropagation();
                const playlistId = Number(removeSongBtn.dataset.playlistId);
                const songId = Number(removeSongBtn.dataset.songId);
                if (Number.isFinite(playlistId) && Number.isFinite(songId)) {
                    this.removeSongFromPlaylist(playlistId, songId);
                }
            }
        });

        document.addEventListener('click', (e) => {
            const deleteBtn = e.target.closest('[data-delete-playlist]');
            if (deleteBtn) {
                e.preventDefault();
                e.stopImmediatePropagation();
                const playlistId = Number(deleteBtn.dataset.deletePlaylist);
                if (Number.isFinite(playlistId)) {
                    this.deletePlaylist(playlistId);
                }
            }
        });

        document.addEventListener('click', (e) => {
            const playlistSong = e.target.closest('[data-playlist-song-id]');
            if (playlistSong && !e.target.closest('[data-remove-playlist-song]')) {
                e.preventDefault();
                e.stopImmediatePropagation();
                const songId = Number(playlistSong.dataset.playlistSongId);
                if (Number.isFinite(songId)) {
                    this.playSongById(songId);
                }
            }
        });

        document.addEventListener('click', (e) => {
            const createBtn = e.target.closest('[data-create-playlist]');
            if (createBtn) {
                e.preventDefault();
                e.stopPropagation();
                const name = window.prompt('Playlist name');
                if (name && name.trim()) {
                    this.createPlaylist(name.trim());
                }
            }
        });

        document.addEventListener('click', (e) => {
            const historyItem = e.target.closest('[data-history-song-id]');
            if (historyItem) {
                e.preventDefault();
                e.stopPropagation();
                const songId = historyItem.dataset.historySongId;
                if (songId) {
                    this.playSongById(songId);
                }
            }
        });

        // Menu button
        this.bindEvent('menuBtn', 'click', (e) => {
            e.stopPropagation();
            this.toggleMenu();
        });
        this.bindEvent('closeMenuBtn', 'click', () => this.toggleMenu());

        // Backdrop click closes sidebar on mobile
        this.bindEvent('sidebarBackdrop', 'click', () => this.closeSidebar());

        // Close sidebar when clicking outside of it (mobile)
        document.addEventListener('click', (e) => {
            const sidebar = document.querySelector('.sidebar');
            if (!sidebar || !sidebar.classList.contains('open')) return;
            if (e.target.closest('.sidebar') || e.target.closest('#menuBtn')) return;
            this.closeSidebar();
        });

        // Song cards
        document.addEventListener('click', (e) => {
            if (e.target.closest('.song-menu-btn')) return;
            if (e.target.closest('.song-card-menu')) return;
            if (e.target.closest('[data-song-option]')) return;
            if (e.target.closest('.song-card')) {
                this.playSong(e.target.closest('.song-card'));
            }
        });

        // Artist cards in main content
        document.addEventListener('click', (e) => {
            const artistCard = e.target.closest('.artist-card');
            if (artistCard) {
                e.preventDefault();
                const artistId = artistCard.dataset.artistId;
                const artistName = artistCard.dataset.artistName;
                if (artistId) this.loadArtistSongs(artistId, artistName);
            }
        });

        // Load more artists
        this.bindEvent('loadMoreArtists', 'click', () => this.loadMoreArtists());

        // Delegated controls for buttons that may be recreated
        document.addEventListener('click', (e) => {
            const nextBtn = e.target.closest('#nextSongsBtn');
            const prevBtn = e.target.closest('#prevSongsBtn');
            const backBtn = e.target.closest('#backToHomeBtn');
            const loadMoreBtn = e.target.closest('#loadMoreArtists');
            if (nextBtn) {
                e.preventDefault();
                if (this.dashboardMode === 'playlist') {
                    this.nextSong();
                } else {
                    this.loadNextPage();
                }
            }
            if (prevBtn) {
                e.preventDefault();
                if (this.dashboardMode === 'playlist') {
                    this.prevSong();
                } else {
                    this.loadPrevPage();
                }
            }
            if (backBtn) {
                e.preventDefault();
                this.goHome();
            }
            if (loadMoreBtn) {
                e.preventDefault();
                this.loadMoreArtists();
            }
        });

        this.debug('Event listeners bound');

        // Debug
        this.bindEvent('toggleDebug', 'click', () => this.toggleDebug());

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Don't hijack shortcuts when user is typing
            const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
            const isTyping = tag === 'input' || tag === 'textarea' || (e.target && e.target.isContentEditable);
            if (isTyping) return;

            if (e.code === 'Space') {
                e.preventDefault();
                this.togglePlay();
                return;
            }

            // Shift + Arrow => seek like progress bar click (relative)
            if (e.shiftKey && e.code === 'ArrowRight') {
                this.seekBySeconds(20);
                e.preventDefault();
                return;
            }
            if (e.shiftKey && e.code === 'ArrowLeft') {
                this.seekBySeconds(-20);
                e.preventDefault();
                return;
            }

            if (e.code === 'ArrowLeft') this.prevSong();
            if (e.code === 'ArrowRight') this.nextSong();
        });
    }

    async loadGenres() {
        // UI uses the same container id (genresList) but now we load artists
        try {
            const response = await fetch('api/artists.php');
            const artists = await response.json();
            const genresList = document.getElementById('genresList');

            if (!Array.isArray(artists)) {
                this.debug('Artists API returned invalid payload', 'error');
                genresList.innerHTML = '';
                return;
            }

            genresList.innerHTML = artists.map(artist =>
                `<a href="#" class="genre-card artist-card" data-artist-id="${artist.id}" data-artist-name="${artist.name}">
                    <div class="genre-icon"><i class="fas fa-music"></i></div>
                    ${artist.name}
                </a>`
            ).join('');

            // Clicking an artist loads their songs via AJAX without disrupting current playback
            document.querySelectorAll('.artist-card').forEach(card => {
                card.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const artistId = card.dataset.artistId;
                    await this.loadArtistSongs(artistId, card.dataset.artistName);
                });
            });

            this.debug('Artists loaded: ' + artists.length);
        } catch (error) {
            this.debug('Error loading artists: ' + error.message, 'error');
        }
    }

    setupAudioEvents() {
        this.audio.addEventListener('play', () => {
            this.isPlaying = true;
            this.errorCount = 0;
            document.getElementById('playPauseBtn').innerHTML = '<i class="fas fa-pause"></i>';
        });

        this.audio.addEventListener('pause', () => {
            this.isPlaying = false;
            document.getElementById('playPauseBtn').innerHTML = '<i class="fas fa-play"></i>';
        });

        this.audio.addEventListener('timeupdate', () => {
            this.updateProgress();
            this.trackListeningHistory();
            this.savePlaybackStateThrottled();
        });
        this.audio.addEventListener('loadedmetadata', () => this.updateDuration());
        this.audio.addEventListener('ended', () => this.handleAudioEnded());

        this.audio.addEventListener('error', (e) => {
            this.debug('Audio error: ' + e.message, 'error');
            this.errorCount++;
            if (this.errorCount > this.currentSongs.length) {
                this.debug('Too many errors, stopping');
                this.audio.pause();
                return;
            }
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) return;

            const shouldResume = this.audio
                && this.audio.src
                && this.audio.paused
                && !this.audio.ended
                && this.audio.currentTime > 0;
            const isAtEnd = this.audio && this.audio.duration > 0 && this.audio.currentTime >= this.audio.duration - 0.2;

            if (shouldResume) {
                this.audio.play().catch(() => {});
                return;
            }

            if (isAtEnd && this.audio && !this.audio.paused) {
                this.handleAudioEnded();
            }
        });

        window.addEventListener('pageshow', () => {
            if (this.audio && this.audio.src && this.audio.paused && !this.audio.ended && this.audio.currentTime > 0) {
                this.audio.play().catch(() => {});
            }
        });
    }

    async playSong(songCard) {
        const songData = {
            id: songCard.dataset.id,
            title: songCard.dataset.title,
            artist: songCard.dataset.artist,
            src: songCard.dataset.src,
            thumbnail: songCard.dataset.thumbnail || songCard.querySelector('img')?.src || 'assets/images/default-album.jpg'
        };

        // Enforce guest play limit: if user is guest, check remaining plays
        try {
            if (window.DVIBES && window.DVIBES.isLoggedIn && window.DVIBES.isGuest) {
                const allowed = await this.checkGuestLimit();
                if (!allowed) {
                    this.showToast('Guest play limit reached. Please login for unlimited listening.', 'error');
                    return;
                }
            }
        } catch (e) {
            this.debug('Guest limit check failed: ' + (e && e.message), 'error');
        }

        if (String(songData.id) !== String(this.currentSong?.id)) {
            this.recordedHistorySongIds.delete(String(songData.id));
        }

        if (!this.currentSongs.some(song => String(song.id) === String(songData.id))) {
            this.currentSongs = [songData];
            this.currentIndex = 0;
            this.refreshPlaybackOrder();
        } else {
            this.setCurrentIndex(songData);
        }

        this.currentSong = songData;
        this.audio.src = songData.src;
        this.audio.load();
        this.audio.play();

        this.updatePlayerDisplay();
        this.setActiveCard(songData.id);
        this.updateShareUrl(songData.id);
        this.debug(`Playing: ${songData.title}`);
    }

    async fetchSongById(songId) {
        if (!songId) return null;
        try {
            const response = await fetch(`api/song.php?id=${encodeURIComponent(songId)}`);
            const data = await response.json();
            if (!response.ok || data.error) {
                this.debug('Fetch song by ID error: ' + (data.error || response.statusText), 'error');
                return null;
            }
            return data;
        } catch (error) {
            this.debug('Fetch song by ID failed: ' + error.message, 'error');
            return null;
        }
    }

    async playSongById(songId) {
        const song = await this.fetchSongById(songId);
        if (!song) return;
        const songCard = document.createElement('div');
        songCard.dataset.id = song.id;
        songCard.dataset.title = song.title;
        songCard.dataset.artist = song.artists || '';
        songCard.dataset.src = song.audio_url || '';
        songCard.dataset.thumbnail = song.thumbnail_url || '';
        this.playSong(songCard);
    }

    async loadDashboardPlaylist(playlistId, playlistName) {
        // Update UI and queue, but do NOT stop current playback.
        this.dashboardMode = 'playlist';
        this.dashboardPlaylistId = playlistId;
        this.dashboardPlaylistName = playlistName || '';

        // Ensure "home" button always returns to Popular Songs section.
        // (We reuse goHome() which restores popular dashboard.)
        this.currentOffset = 0;

        const container = document.querySelector('.content-grid');
        if (!container) return;

        container.innerHTML = `
            <div class="section">
                <h2>${this.escapeHtml(this.dashboardPlaylistName || 'Playlist')}</h2>
                <button id="backToHomeBtn" class="back-btn" style="margin-bottom: 20px;">← Back to Home</button>
                <div class="songs-grid" id="popularSongs">
                    <div class="user-panel-loading" style="grid-column:1/-1;">Loading playlist...</div>
                </div>
                <div class="pagination-controls" style="text-align: center; margin: 20px 0;">
                    <button id="prevSongsBtn" class="pagination-btn" style="display: none;">← Previous</button>
                    <span id="songPageInfo" style="margin: 0 20px;">Loading...</span>
                    <button id="nextSongsBtn" class="pagination-btn" style="display: none;">Next →</button>
                </div>
            </div>
            <div class="section">
                <h2>Artists</h2>
                <div class="genres-grid" id="genresGrid"></div>
                <div style="text-align: center; margin: 20px 0;">
                    <button id="loadMoreArtists" class="show-more-btn">Show More Artists</button>
                </div>
            </div>
        `;

        const response = await fetch(`api/user_playlist_songs.php?playlist_id=${encodeURIComponent(playlistId)}`);
        const data = await response.json();
        if (!response.ok || !Array.isArray(data)) {
            throw new Error((data && data.error) || 'Unable to load playlist songs');
        }

        const songs = data.map(s => ({
            id: s.id,
            title: s.title,
            artists: s.artists || '',
            audio_url: s.audio_url || '',
            thumbnail_url: s.thumbnail_url || ''
        }));

        this.currentSongs = songs.map(song => ({
            id: song.id,
            title: song.title,
            artists: song.artists,
            audio_url: song.audio_url,
            thumbnail_url: song.thumbnail_url
        }));

        // Set index to current song if it exists in this playlist queue.
        if (this.currentSong && this.currentSongs.some(s => String(s.id) === String(this.currentSong.id))) {
            this.currentIndex = this.currentSongs.findIndex(s => String(s.id) === String(this.currentSong.id));
        } else {
            this.currentIndex = 0;
        }
        this.refreshPlaybackOrder();

        const songsHtml = this.currentSongs.length
            ? this.currentSongs.map(song => this.getSongCardHtml(song)).join('')
            : '<div class="no-results">This playlist is empty.</div>';

        const grid = document.getElementById('popularSongs');
        if (grid) grid.innerHTML = songsHtml;

        // Update active card (if currently playing song exists)
        if (this.currentSong && this.currentSongs.some(s => String(s.id) === String(this.currentSong.id))) {
            this.setActiveCard(this.currentSong.id);
        }

        const songPageInfo = document.getElementById('songPageInfo');
        if (songPageInfo) {
            songPageInfo.textContent = `${this.currentSongs.length} song${this.currentSongs.length === 1 ? '' : 's'}`;
        }

        const nextBtn = document.getElementById('nextSongsBtn');
        const prevBtn = document.getElementById('prevSongsBtn');
        if (nextBtn) nextBtn.style.display = this.currentSongs.length > 1 ? 'inline-block' : 'none';
        if (prevBtn) prevBtn.style.display = this.currentSongs.length > 1 ? 'inline-block' : 'none';

        // Keep Artists section working by reusing renderHomeContent's fetch logic.
        const artists = await this.fetchArtists(6);
        const artistsHtml = artists.map(artist => `
            <a href="#" class="genre-card artist-card" data-artist-id="${artist.id}" data-artist-name="${artist.name}">
                <div class="genre-icon"><i class="fas fa-music"></i></div>
                <span>${artist.name}</span>
            </a>
        `).join('');

        const genresGrid = document.getElementById('genresGrid');
        if (genresGrid) genresGrid.innerHTML = artistsHtml;

        // Rebind artist cards for this view (without affecting playback)
        document.querySelectorAll('.artist-card').forEach(card => {
            card.addEventListener('click', async (e) => {
                e.preventDefault();
                const artistId = card.dataset.artistId;
                const artistName = card.dataset.artistName;
                if (artistId) await this.loadArtistSongs(artistId, artistName);
            });
        });

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async loadSongFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const token = params.get('s');
        const songId = params.get('song');
        if (!token && !songId) return;

        let song = null;
        if (token) {
            try {
                const response = await fetch(`api/song.php?s=${encodeURIComponent(token)}`);
                const data = await response.json();
                if (response.ok && data && !data.error) song = data;
            } catch (e) {}
        }

        if (!song && songId) {
            song = await this.fetchSongById(songId);
        }
        if (!song) {
            this.debug('Shared song not found', 'error');
            return;
        }

        this.displaySearchResults([song]);
        this.currentSongs = [song];
        this.currentIndex = 0;
        this.currentSong = song;
        this.playCurrentSong();
    }

    escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, (c) => ({
            '&': '&amp;',
            '<': '<',
            '>': '>',
            '"': '"',
            "'": '&#39;'
        }[c]));
    }

    shareSong(songId) {
        this.getShareToken(songId).then(token => {
            if (!token) {
                this.showToast('Unable to create share link', 'error');
                return;
            }
            const url = new URL(window.location.href);
            url.searchParams.delete('song');
            url.searchParams.set('s', token);
            const shareUrl = url.toString();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shareUrl).then(() => {
                    this.debug('Song link copied to clipboard: ' + shareUrl);
                    this.showToast('Link copied to clipboard');
                }).catch(() => {
                    this.fallbackCopyText(shareUrl);
                });
            } else {
                this.fallbackCopyText(shareUrl);
            }
        });
    }

    fallbackCopyText(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        let copied = false;
        try {
            copied = document.execCommand('copy');
            this.debug('Song link copied via fallback.');
        } catch (err) {
            this.debug('Copy fallback failed: ' + err.message, 'error');
        }
        document.body.removeChild(textarea);
        if (copied) {
            this.showToast('Link copied to clipboard');
        } else {
            this.showToast('Unable to copy link', 'error');
        }
    }

    showToast(message, type = 'success') {
        let toast = document.getElementById('appToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'appToast';
            toast.className = 'app-toast';
            document.body.appendChild(toast);
        }
        const iconClass = type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check';
        toast.innerHTML = `<i class="fas ${iconClass}"></i><span>${message}</span>`;
        toast.offsetHeight;
        toast.classList.add('show');
        if (this._toastTimer) clearTimeout(this._toastTimer);
        this._toastTimer = setTimeout(() => {
            toast.classList.remove('show');
        }, 2000);
    }

    maybeShowGuestModal() {
        try {
            if (!window.DVIBES) return;
            if (window.DVIBES.isLoggedIn) return;
            if (localStorage.getItem('dvibes_guest_choice_shown')) return;
            const modal = document.getElementById('guestChoiceModal');
            if (!modal) return;
            modal.style.display = 'flex';

            const continueBtn = document.getElementById('guestLoginBtn');
            const loginBtn = document.getElementById('guestGoLoginBtn');
            if (continueBtn) {
                continueBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    // Redirect to server-side guest login which creates a guest user and reloads
                    localStorage.setItem('dvibes_guest_choice_shown', '1');
                    window.location.href = 'guest_login.php';
                });
            }
            if (loginBtn) {
                loginBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    window.location.href = 'login.php';
                });
            }
        } catch (e) {
            this.debug('maybeShowGuestModal error: ' + e.message, 'error');
        }
    }

    async checkGuestLimit() {
        try {
            const resp = await fetch('api/guest_remaining.php');
            if (!resp.ok) return true;
            const data = await resp.json();
            if (!data) return true;
            if (typeof data.remaining === 'number') {
                if (data.remaining === -1) return true; // unlimited
                return data.remaining > 0;
            }
            return true;
        } catch (e) {
            this.debug('checkGuestLimit failed: ' + e.message, 'error');
            return true;
        }
    }

    openUserPanel(panel, songId = null, playlistId = null, playlistName = '') {
        const overlay = document.getElementById('userPanelOverlay');
        if (!overlay) return;

        this.userPanelMode = panel;
        this.userPanelSongId = songId;
        this.userPanelPlaylistId = playlistId;
        this.userPanelPlaylistName = playlistName;
        overlay.classList.add('open');

        if (panel === 'playlists' || panel === 'playlist-picker') {
            this.renderPlaylistPanel(panel === 'playlist-picker' ? 'Add to playlist' : 'My playlists');
            this.loadUserPlaylists();
        } else if (panel === 'playlist-detail') {
            this.renderPlaylistDetailPanel(playlistName || 'Playlist');
            if (Number.isFinite(this.userPanelPlaylistId)) {
                this.loadPlaylistSongs(this.userPanelPlaylistId);
            }
        } else if (panel === 'history') {
            this.renderHistoryPanel();
            this.loadListeningHistory();
        }
    }

    closeUserPanel() {
        const overlay = document.getElementById('userPanelOverlay');
        if (!overlay) return;
        overlay.classList.remove('open');
        this.userPanelMode = null;
        this.userPanelSongId = null;
        this.userPanelPlaylistId = null;
        this.userPanelPlaylistName = '';
    }

    renderPlaylistPanel(title) {
        const titleEl = document.getElementById('userPanelTitle');
        const subtitleEl = document.getElementById('userPanelSubtitle');
        const bodyEl = document.getElementById('userPanelBody');
        if (titleEl) titleEl.textContent = title;
        if (subtitleEl) subtitleEl.textContent = this.userPanelMode === 'playlist-picker' && this.userPanelSongId
            ? 'Choose where to save this song'
            : 'Create and manage your playlists';
        if (bodyEl) bodyEl.innerHTML = '<div class="user-panel-loading">Loading playlists...</div>';
    }

    renderPlaylistDetailPanel(title) {
        const titleEl = document.getElementById('userPanelTitle');
        const subtitleEl = document.getElementById('userPanelSubtitle');
        const bodyEl = document.getElementById('userPanelBody');
        if (titleEl) titleEl.textContent = title;
        if (subtitleEl) subtitleEl.textContent = 'Songs in this playlist';
        if (bodyEl) bodyEl.innerHTML = '<div class="user-panel-loading">Loading playlist...</div>';
    }

    renderHistoryPanel() {
        const titleEl = document.getElementById('userPanelTitle');
        const subtitleEl = document.getElementById('userPanelSubtitle');
        const bodyEl = document.getElementById('userPanelBody');
        if (titleEl) titleEl.textContent = 'Listening history';
        if (subtitleEl) subtitleEl.textContent = 'Recently played songs';
        if (bodyEl) bodyEl.innerHTML = '<div class="user-panel-loading">Loading history...</div>';
    }

    async loadUserPlaylists() {
        try {
            const response = await fetch('api/user_playlists.php');
            const data = await response.json();
            if (!response.ok || !Array.isArray(data)) {
                throw new Error((data && data.error) || 'Unable to load playlists');
            }

            const body = document.getElementById('userPanelBody');
            if (!body) return;
            if (!data.length) {
                body.innerHTML = `
                    <div class="user-panel-empty">
                        <p>No playlists yet.</p>
                        <button class="playlist-create-inline" data-create-playlist="true">+ New playlist</button>
                    </div>
                `;
                return;
            }

            body.innerHTML = `
                <div class="user-panel-actions">
                    <button class="playlist-create-inline" data-create-playlist="true">+ New playlist</button>
                </div>
                <div class="playlist-panel-list">
                    ${data.map(playlist => `
                        <div class="playlist-panel-row" style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin:6px 0;">
                            <button class="playlist-panel-item" data-playlist-id="${playlist.id}" data-playlist-name="${playlist.name}" style="flex:1; text-align:left;">
                                <span>${playlist.name}</span>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <button class="rename-playlist-btn" data-playlist-id="${playlist.id}" title="Rename" style="border:none; background:transparent; color:inherit; padding:6px; cursor:pointer;"><i class="fas fa-pen"></i></button>
                                <button class="delete-playlist-btn" data-playlist-id="${playlist.id}" title="Delete" style="border:none; background:transparent; color:inherit; padding:6px; cursor:pointer;"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;

            // wire up rename/delete buttons inside the panel
            body.querySelectorAll('.delete-playlist-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const id = Number(btn.dataset.playlistId);
                    if (!Number.isFinite(id)) return;
                    const ok = window.confirm('Delete this playlist?');
                    if (!ok) return;
                    await this.deletePlaylist(id);
                    await this.loadUserPlaylists();
                });
            });

            body.querySelectorAll('.rename-playlist-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const id = Number(btn.dataset.playlistId);
                    if (!Number.isFinite(id)) return;
                    const name = window.prompt('Enter new playlist name');
                    if (!name || !name.trim()) return;
                    try {
                        await this.renamePlaylist(id, name.trim());
                        await this.loadUserPlaylists();
                        this.showToast('Playlist renamed');
                    } catch (err) {
                        this.showToast(err.message || 'Unable to rename playlist', 'error');
                    }
                });
            });
        } catch (error) {
            this.debug('Error loading playlists: ' + error.message, 'error');
            const body = document.getElementById('userPanelBody');
            if (body) {
                body.innerHTML = '<div class="user-panel-empty">Unable to load playlists.</div>';
            }
        }
    }

    async loadPlaylistSongs(playlistId) {
        try {
            const response = await fetch(`api/user_playlist_songs.php?playlist_id=${playlistId}`);
            const data = await response.json();
            if (!response.ok || !Array.isArray(data)) {
                throw new Error((data && data.error) || 'Unable to load playlist songs');
            }

            const body = document.getElementById('userPanelBody');
            if (!body) return;

            body.innerHTML = `
                <div class="playlist-detail-actions">
                    <button class="playlist-delete-btn" data-delete-playlist="${playlistId}" type="button">Delete playlist</button>
                </div>
                ${data.length ? `
                    <div class="playlist-song-list">
                        ${data.map(song => `
                            <div class="playlist-song-row">
                                <button class="history-panel-item playlist-song-item" type="button" data-playlist-song-id="${song.id}">
                                    <img src="${song.thumbnail_url || 'assets/images/default-album.jpg'}" alt="${song.title || ''}">
                                    <div>
                                        <div class="history-panel-title">${song.title || 'Unknown song'}</div>
                                        <div class="history-panel-sub">${song.artists || ''}</div>
                                    </div>
                                </button>
                                <button class="playlist-song-remove" type="button" data-remove-playlist-song="true" data-playlist-id="${playlistId}" data-song-id="${song.id}" title="Remove from playlist">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `).join('')}
                    </div>
                ` : '<div class="user-panel-empty">This playlist is empty.</div>'}
            `;
        } catch (error) {
            this.debug('Error loading playlist songs: ' + error.message, 'error');
            const body = document.getElementById('userPanelBody');
            if (body) {
                body.innerHTML = '<div class="user-panel-empty">Unable to load playlist.</div>';
            }
        }
    }

    async loadListeningHistory() {
        try {
            const response = await fetch('api/user_history.php?limit=30');
            const data = await response.json();
            if (!response.ok || !Array.isArray(data)) {
                throw new Error((data && data.error) || 'Unable to load history');
            }

            const body = document.getElementById('userPanelBody');
            if (!body) return;
            if (!data.length) {
                body.innerHTML = '<div class="user-panel-empty">No listening history yet.</div>';
                return;
            }

            // Remove consecutive duplicate song entries (only show the first in a run)
            const filtered = [];
            let lastSongId = null;
            for (const item of data) {
                const sid = item.song_id || item.songId || item.song || null;
                if (sid == null) continue;
                if (String(sid) === String(lastSongId)) continue;
                filtered.push(item);
                lastSongId = sid;
            }

            const listHtml = filtered.length
                ? filtered.map(item => `
                    <button class="history-panel-item" data-history-song-id="${item.song_id}">
                        <img src="${item.thumbnail_url || 'assets/images/default-album.jpg'}" alt="${item.title || ''}">
                        <div>
                            <div class="history-panel-title">${item.title || 'Unknown song'}</div>
                            <div class="history-panel-sub">${item.artists || ''}</div>
                        </div>
                    </button>
                `).join('')
                : '<div class="user-panel-empty">No listening history yet.</div>';

            body.innerHTML = `
                <div class="history-panel-list">
                    ${listHtml}
                </div>
            `;
        } catch (error) {
            this.debug('Error loading history: ' + error.message, 'error');
            const body = document.getElementById('userPanelBody');
            if (body) {
                body.innerHTML = '<div class="user-panel-empty">Unable to load history.</div>';
            }
        }
    }

    async createPlaylist(name) {
        try {
            const form = new FormData();
            form.append('action', 'create');
            form.append('name', name.trim());
            const response = await fetch('api/user_playlists.php', { method: 'POST', body: form });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error((data && data.error) || 'Unable to create playlist');
            }
            await this.loadUserPlaylists();
            this.showToast('Playlist created');
        } catch (error) {
            this.showToast(error.message, 'error');
        }
    }

    async deletePlaylist(playlistId) {
        if (!window.confirm('Delete this playlist?')) {
            return;
        }

        try {
            const form = new FormData();
            form.append('action', 'delete');
            form.append('playlist_id', String(playlistId));
            const response = await fetch('api/user_playlists.php', { method: 'POST', body: form });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error((data && data.error) || 'Unable to delete playlist');
            }
            this.closeUserPanel();
            this.showToast('Playlist deleted');
        } catch (error) {
            this.showToast(error.message, 'error');
        }
    }

    async renamePlaylist(playlistId, name) {
        try {
            const form = new FormData();
            form.append('action', 'rename');
            form.append('playlist_id', String(playlistId));
            form.append('name', name);
            const response = await fetch('api/user_playlists.php', { method: 'POST', body: form });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error((data && data.error) || 'Unable to rename playlist');
            }
            return true;
        } catch (error) {
            throw error;
        }
    }

    async removeSongFromPlaylist(playlistId, songId) {
        try {
            const form = new FormData();
            form.append('action', 'remove');
            form.append('playlist_id', String(playlistId));
            form.append('song_id', String(songId));
            const response = await fetch('api/user_playlist_songs.php', { method: 'POST', body: form });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error((data && data.error) || 'Unable to remove song');
            }

            if (this.dashboardMode === 'playlist' && Number(this.dashboardPlaylistId) === Number(playlistId)) {
                await this.loadDashboardPlaylist(playlistId, this.dashboardPlaylistName);
            } else if (this.userPanelMode === 'playlist-detail') {
                await this.loadPlaylistSongs(playlistId);
            }

            this.showToast('Song removed from playlist');
        } catch (error) {
            this.showToast(error.message, 'error');
        }
    }

    async addSongToPlaylist(playlistId, songId) {
        const playlistBtn = document.querySelector(`[data-playlist-id="${playlistId}"]`);
        if (playlistBtn) {
            playlistBtn.disabled = true;
            playlistBtn.classList.add('is-saving');
            playlistBtn.innerHTML = '<span>Adding...</span>';
        }

        try {
            const form = new FormData();
            form.append('action', 'add');
            form.append('playlist_id', String(playlistId));
            form.append('song_ids', JSON.stringify([songId]));
            const response = await fetch('api/user_playlist_songs.php', { method: 'POST', body: form });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error((data && data.error) || 'Unable to add song');
            }
            this.closeUserPanel();
            this.showToast('Song added to playlist');
        } catch (error) {
            this.showToast(error.message, 'error');
            if (playlistBtn) {
                playlistBtn.disabled = false;
                playlistBtn.classList.remove('is-saving');
                playlistBtn.innerHTML = `<span>${playlistBtn.dataset.playlistName || 'Playlist'}</span><i class="fas fa-chevron-right"></i>`;
            }
        }
    }

    trackListeningHistory() {
        if (!this.currentSong || !this.audio || !this.audio.duration || !isFinite(this.audio.duration)) {
            return;
        }

        const songId = this.currentSong.id;
        if (!songId || this.recordedHistorySongIds.has(String(songId))) {
            return;
        }
        if (this.audio.currentTime < 10) {
            return;
        }

        this.recordedHistorySongIds.add(String(songId));
        const form = new FormData();
        form.append('song_id', String(songId));
        form.append('last_position_seconds', String(Math.floor(this.audio.currentTime)));
        form.append('duration_seconds', String(Math.floor(this.audio.duration)));
        fetch('api/user_history.php', {
            method: 'POST',
            body: form
        }).catch(() => {});
    }

    // Save current playback state to server (or localStorage as fallback)
    async savePlaybackState() {
        try {
            if (!this.currentSong || !this.currentSong.id) return;
            const pos = Math.floor(this.audio.currentTime || 0);
            const form = new FormData();
            form.append('song_id', String(this.currentSong.id));
            form.append('last_position_seconds', String(pos));
            const response = await fetch('api/user_playback.php', { method: 'POST', body: form });
            if (!response.ok) {
                // fallback to localStorage
                localStorage.setItem('dvibes_playback', JSON.stringify({ song_id: this.currentSong.id, pos }));
            } else {
                // also mirror to localStorage for quick restore
                localStorage.setItem('dvibes_playback', JSON.stringify({ song_id: this.currentSong.id, pos }));
            }
        } catch (err) {
            try { localStorage.setItem('dvibes_playback', JSON.stringify({ song_id: this.currentSong.id, pos: Math.floor(this.audio.currentTime || 0) })); } catch(e){}
        }
    }

    savePlaybackStateThrottled() {
        const now = Date.now();
        if (now - (this._lastPlaybackSave || 0) < 15000) return; // 15s
        this._lastPlaybackSave = now;
        this.savePlaybackState().catch(() => {});
    }

    async restorePlaybackState() {
        try {
            const response = await fetch('api/user_playback.php');
            if (response.ok) {
                const data = await response.json();
                if (data && data.song_id) {
                    const song = await this.fetchSongById(data.song_id);
                    if (song) {
                        this.currentSongs = [song];
                        this.currentIndex = 0;
                        this.currentSong = song;
                        this.audio.src = song.audio_url || '';
                        try { this.audio.currentTime = Number(data.last_position_seconds || 0); } catch(e){}
                        this.updatePlayerDisplay();
                        this.setActiveCard(song.id);
                        return;
                    }
                }
            }
        } catch (err) {
            // fallback to localStorage
        }

        try {
            const raw = localStorage.getItem('dvibes_playback');
            if (!raw) return;
            const obj = JSON.parse(raw);
            if (obj && obj.song_id) {
                const song = await this.fetchSongById(obj.song_id);
                if (song) {
                    this.currentSongs = [song];
                    this.currentIndex = 0;
                    this.currentSong = song;
                    this.audio.src = song.audio_url || '';
                    try { this.audio.currentTime = Number(obj.pos || 0); } catch(e){}
                    this.updatePlayerDisplay();
                    this.setActiveCard(song.id);
                }
            }
        } catch (e) {}
    }

    // Share token helpers
    async getShareToken(songId) {
        if (!songId) return null;
        if (this.shareTokenCache[songId]) return this.shareTokenCache[songId];
        try {
            const res = await fetch(`api/share_song.php?song_id=${encodeURIComponent(songId)}`);
            const data = await res.json();
            if (!res.ok || data.error) throw new Error(data.error || 'Unable to get token');
            this.shareTokenCache[songId] = data.token;
            return data.token;
        } catch (e) {
            return null;
        }
    }

    closeSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar) sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('show');
    }

    getShareUrl(songId) {
        if (!songId) return null;
        const url = new URL(window.location.href);
        url.searchParams.delete('song');
        url.searchParams.delete('s');
        if (this.shareTokenCache[songId]) {
            url.searchParams.set('s', this.shareTokenCache[songId]);
        } else {
            url.searchParams.set('song', songId);
        }
        return url.toString();
    }

    updateShareUrl(songId) {
        if (!songId) return;
        this.getShareToken(songId).then(token => {
            const url = new URL(window.location.href);
            url.searchParams.delete('song');
            if (token) {
                url.searchParams.delete('s');
                url.searchParams.set('s', token);
            } else {
                url.searchParams.delete('s');
                url.searchParams.set('song', songId);
            }
            window.history.replaceState({}, '', url.toString());
        }).catch(() => {
            const url = new URL(window.location.href);
            url.searchParams.delete('s');
            url.searchParams.set('song', songId);
            window.history.replaceState({}, '', url.toString());
        });
    }

    setActiveCard(songId) {
        document.querySelectorAll('.song-card.playing').forEach(card => card.classList.remove('playing'));
        const activeCard = document.querySelector(`.song-card[data-id="${songId}"]`);
        if (activeCard) {
            activeCard.classList.add('playing');
        }
    }

    setCurrentIndex(song) {
        const index = this.currentSongs.findIndex(s => s.id == song.id);
        if (index > -1) {
            this.currentIndex = index;
        }
        this.debug('Set current index to: ' + this.currentIndex);
    }

    setCurrentSongs(songs) {
        this.currentSongs = Array.isArray(songs) ? songs : [];
        this.refreshPlaybackOrder();
        this.currentIndex = 0;
        this.playbackIndex = 0;
        this.nextUpQueue = [];
        this.queueResumeIndex = null;
        this.debug('Set current songs: ' + this.currentSongs.length);
    }

    async queueSongNext(songId) {
        if (!songId) return;
        if (!this.currentSong || !this.currentSong.id) {
            return this.playSongById(songId);
        }

        if (String(this.currentSong.id) === String(songId)) {
            this.showToast('This song is already playing', 'info');
            return;
        }

        let song = this.currentSongs.find(s => String(s.id) === String(songId));
        if (!song) {
            const card = document.querySelector(`.song-card[data-id="${songId}"]`);
            if (card) {
                song = {
                    id: card.dataset.id,
                    title: card.dataset.title,
                    artists: card.dataset.artist,
                    audio_url: card.dataset.src,
                    thumbnail_url: card.dataset.thumbnail
                };
            }
        }

        if (!song) {
            song = await this.fetchSongById(songId);
        }
        if (!song) {
            this.showToast('Unable to queue song', 'error');
            return;
        }

        const queuedSong = {
            id: song.id,
            title: song.title,
            artists: song.artists || song.artist || song.artist_names || '',
            audio_url: song.audio_url || song.file_path || '',
            thumbnail_url: song.thumbnail_url || song.thumbnail || song.thumbnail_path || ''
        };

        if (this.nextUpQueue.some(q => String(q.id) === String(queuedSong.id))) {
            this.showToast('Song is already queued next', 'info');
            return;
        }

        const existingIndex = this.currentSongs.findIndex(s => String(s.id) === String(queuedSong.id));
        if (existingIndex > -1) {
            this.currentSongs.splice(existingIndex, 1);
            if (existingIndex < this.currentIndex) {
                this.currentIndex = Math.max(0, this.currentIndex - 1);
            }
            this.refreshPlaybackOrder();
        }

        if (!this.shuffleEnabled && this.nextUpQueue.length === 0) {
            this.queueResumeIndex = this.currentIndex + 1;
        }
        this.nextUpQueue.push(queuedSong);

        this.showToast(`Queued next: ${song.title}`);
    }

    refreshPlaybackOrder() {
        if (!this.currentSongs.length) {
            this.playbackOrder = [];
            this.playbackIndex = 0;
            return;
        }

        if (this.shuffleEnabled) {
            const order = [...this.currentSongs];
            for (let i = order.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [order[i], order[j]] = [order[j], order[i]];
            }
            this.playbackOrder = order;
        } else {
            this.playbackOrder = [...this.currentSongs];
        }

        if (this.currentSong) {
            const currentId = String(this.currentSong.id);
            const foundIndex = this.playbackOrder.findIndex(s => String(s.id) === currentId);
            this.playbackIndex = foundIndex !== -1 ? foundIndex : 0;
        } else {
            this.playbackIndex = 0;
        }
    }

    updatePlaybackModeUI() {
        const repeatBtn = document.getElementById('repeatBtn');
        const shuffleBtn = document.getElementById('shuffleBtn');

        if (repeatBtn) {
            repeatBtn.classList.toggle('active', this.repeatMode === 'one');
            repeatBtn.dataset.mode = this.repeatMode;
            repeatBtn.title = this.repeatMode === 'one'
                ? 'Repeat one'
                : 'Repeat off';
        }

        if (shuffleBtn) {
            shuffleBtn.classList.toggle('active', this.shuffleEnabled);
            shuffleBtn.title = this.shuffleEnabled ? 'Shuffle on' : 'Shuffle off';
        }
    }

    toggleRepeat() {
        this.repeatMode = this.repeatMode === 'one' ? 'off' : 'one';
        this.updatePlaybackModeUI();
        this.debug('Repeat mode: ' + this.repeatMode);
    }

    toggleShuffle() {
        this.shuffleEnabled = !this.shuffleEnabled;
        this.refreshPlaybackOrder();
        this.updatePlaybackModeUI();
        this.debug('Shuffle enabled: ' + this.shuffleEnabled);
    }

    togglePlay() {
        if (this.isPlaying) {
            this.audio.pause();
        } else {
            this.audio.play();
        }
    }

    handleAudioEnded() {
        if (this.repeatMode === 'one') {
            this.audio.currentTime = 0;
            this.audio.play();
            return;
        }

        this.nextSong();
    }

    prevSong() {
        if (this.currentSongs.length === 0) {
            this.debug('No songs in playlist', 'error');
            return;
        }

        if (this.shuffleEnabled && this.playbackOrder.length > 1) {
            const prevIndex = (this.playbackIndex - 1 + this.playbackOrder.length) % this.playbackOrder.length;
            this.playbackIndex = prevIndex;
            this.currentSong = this.playbackOrder[this.playbackIndex];
            this.currentIndex = this.currentSongs.findIndex(s => String(s.id) === String(this.currentSong.id));
        } else {
            this.currentIndex = (this.currentIndex - 1 + this.currentSongs.length) % this.currentSongs.length;
        }

        this.skipCount = 0;
        this.errorCount = 0;
        this.debug('Playing previous song (index: ' + this.currentIndex + ')');
        this.playCurrentSong();
    }

    async nextSong() {
        if (this.nextUpQueue.length > 0) {
            const nextUpSong = this.nextUpQueue.shift();
            if (nextUpSong && nextUpSong.id) {
                if (this.nextUpQueue.length === 0 && this.queueResumeIndex !== null) {
                    this.debug('Queue finished, will resume normal playback from index ' + this.queueResumeIndex);
                }
                this._playingQueuedSong = true;
                this.currentSong = nextUpSong;
                this.skipCount = 0;
                this.errorCount = 0;
                this.debug('Playing queued next song: ' + nextUpSong.id);
                return this.playQueuedSong(nextUpSong);
            }
        }

        if (this.currentSongs.length === 0) {
            this.debug('No songs in playlist', 'error');
            return;
        }

        if (this.shuffleEnabled && this.playbackOrder.length > 1) {
            const nextIndex = (this.playbackIndex + 1) % this.playbackOrder.length;
            this.playbackIndex = nextIndex;
            this.currentSong = this.playbackOrder[this.playbackIndex];
            this.currentIndex = this.currentSongs.findIndex(s => String(s.id) === String(this.currentSong.id));
        } else {
            if (this.queueResumeIndex !== null) {
                this.currentIndex = Math.min(this.queueResumeIndex, this.currentSongs.length - 1);
                this.queueResumeIndex = null;
                this._playingQueuedSong = false;
            } else {
                const nextIndex = this.currentIndex + 1;
                if (nextIndex >= this.currentSongs.length) {
                    const canLoadMore = this.dashboardMode === 'popular' && (this.currentOffset + this.pageSize < this.totalSongs);
                    if (canLoadMore) {
                        await this.loadNextPage();
                        this.currentIndex = 0;
                    } else {
                        this.audio.pause();
                        return;
                    }
                } else {
                    this.currentIndex = nextIndex;
                }
            }
        }

        this.skipCount = 0;
        this.errorCount = 0;
        this.debug('Playing next song (index: ' + this.currentIndex + ')');
        this.playCurrentSong();
    }

    async playQueuedSong(songData) {
        this.audio.src = songData.audio_url || songData.file_path || '';
        this.audio.load();
        this.audio.play();
        this.currentSong = songData;
        this.updatePlayerDisplay();
        this.setActiveCard(songData.id);
        this.updateShareUrl(songData.id);
        this.debug(`Playing queued song: ${songData.title}`);
    }

    playCurrentSong() {
        const song = this.currentSongs[this.currentIndex];
        this.currentSong = song;
        if (song?.id) this.recordedHistorySongIds.delete(String(song?.id || ''));

        if (!song || !song.audio_url || song.audio_url === '') {
            this.debug('Skipping song without audio: ' + (song ? song.title : 'unknown'));
            this.skipCount++;
            if (this.skipCount > this.currentSongs.length) {
                this.debug('All songs skipped, stopping playback');
                this.audio.pause();
                return;
            }
            this.currentIndex = (this.currentIndex + 1) % this.currentSongs.length;
            return this.playCurrentSong();
        }

        let audioSrc = song.audio_url;
        if (audioSrc && audioSrc.startsWith('music/storage/music/')) {
            audioSrc = audioSrc.replace('music/storage/music/', 'storage/music/');
        }
        if (audioSrc) {
            this.audio.src = audioSrc;
        } else {
            this.audio.src = song.file_path || '';
        }

        this.debug('Playing current song: ' + song.title + ' src: ' + this.audio.src);
        this.audio.load();
        this.audio.play();
        this.updatePlaybackModeUI();
        this.updatePlayerDisplay();
        this.setActiveCard(song.id);
        this.updateShareUrl(song.id);
    }

    updateProgress() {
        if (this.audio.duration) {
            const progress = (this.audio.currentTime / this.audio.duration) * 100;
            document.getElementById('progress').style.width = progress + '%';
            document.getElementById('currentTime').textContent = this.formatTime(this.audio.currentTime);
        }
    }

    updateDuration() {
        document.getElementById('duration').textContent = this.formatTime(this.audio.duration);
    }

    seek(e) {
        const progressBar = e.currentTarget;
        const rect = progressBar.getBoundingClientRect();
        const pos = (e.clientX - rect.left) / rect.width;
        if (!this.audio || !isFinite(this.audio.duration) || this.audio.duration <= 0) return;

        const clampedPos = Math.max(0, Math.min(1, pos));
        const targetTime = clampedPos * this.audio.duration;
        if (!isFinite(targetTime)) return;
        this.audio.currentTime = targetTime;
    }

    seekFromClientX(clientX) {
        const progressBar = document.querySelector('.progress-bar');
        if (!progressBar) return;
        const rect = progressBar.getBoundingClientRect();
        const pos = (clientX - rect.left) / rect.width;
        if (!this.audio || !isFinite(this.audio.duration) || this.audio.duration <= 0) return;
        const clampedPos = Math.max(0, Math.min(1, pos));
        const targetTime = clampedPos * this.audio.duration;
        if (!isFinite(targetTime)) return;
        this.audio.currentTime = targetTime;
    }

    seekBySeconds(deltaSeconds) {
        if (!this.audio || !isFinite(this.audio.duration) || this.audio.duration <= 0) return;
        const next = this.audio.currentTime + deltaSeconds;
        this.audio.currentTime = Math.max(0, Math.min(this.audio.duration, next));
    }

    updatePlayerDisplay() {
        if (this.currentSong) {
            const titleEl = document.getElementById('playerTitle');
            const artistEl = document.getElementById('playerArtist');
            const imageEl = document.getElementById('playerImage');

            if (titleEl) titleEl.textContent = this.currentSong.title || 'Unknown';
            if (artistEl) artistEl.textContent = this.currentSong.artist || this.currentSong.artists || 'Unknown';
            if (imageEl) {
                const thumbnail = this.currentSong.thumbnail || this.currentSong.thumbnail_url || 'assets/images/default-album.jpg';
                imageEl.src = thumbnail;
                imageEl.onerror = () => { imageEl.src = 'assets/images/default-album.jpg'; };
                imageEl.style.display = 'block';
            }
        }
    }

    formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    async search() {
        const query = document.getElementById('searchInput').value.trim();
        if (!query) return;

        try {
            const response = await fetch(`api/search.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            this.clearSuggestions();

            if (!response.ok || data.error) {
                this.debug('Search API error: ' + (data.error || response.statusText), 'error');
                this.displaySearchResults([]);
                return;
            }

            const songs = Array.isArray(data) ? data : [];
            this.displaySearchResults(songs);
            this.debug(`Search results for "${query}": ${songs.length} songs`);
        } catch (error) {
            this.debug('Search error: ' + error.message, 'error');
            this.displaySearchResults([]);
        }
    }

    async searchById(songId) {
        if (!songId) return;

        try {
            const response = await fetch(`api/song.php?id=${encodeURIComponent(songId)}`);
            const data = await response.json();
            this.clearSuggestions();

            if (!response.ok || data.error) {
                this.debug('Search by ID error: ' + (data.error || response.statusText), 'error');
                this.displaySearchResults([]);
                return;
            }

            const song = data && typeof data === 'object' && data.id ? data : null;
            this.displaySearchResults(song ? [song] : []);
            this.debug(`Search by ID ${songId}: ${song ? '1 song found' : 'no song'}`);
        } catch (error) {
            this.debug('Search by ID error: ' + error.message, 'error');
            this.displaySearchResults([]);
        }
    }

    scheduleSuggestions(query) {
        if (this.suggestionTimer) {
            clearTimeout(this.suggestionTimer);
        }
        this.suggestionTimer = setTimeout(() => this.fetchSuggestions(query), 250);
    }

    async fetchSuggestions(query) {
        try {
            const response = await fetch(`api/search.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            if (!response.ok || data.error) {
                this.clearSuggestions();
                return;
            }
            const songs = Array.isArray(data) ? data : [];
            const filtered = songs.slice(0, 8);
            this.showSuggestions(filtered);
        } catch (error) {
            this.clearSuggestions();
        }
    }

    showSuggestions(songs) {
        const suggestions = document.getElementById('searchSuggestions');
        if (!songs.length) {
            suggestions.style.display = 'none';
            suggestions.innerHTML = '';
            return;
        }

        suggestions.innerHTML = songs.map(song => `
            <div class="search-suggestion-item" data-id="${song.id || ''}" data-title="${song.title}">
                <span class="suggestion-title">${song.title}</span>
                <span class="suggestion-subtitle">${song.artists || song.album || ''}</span>
            </div>
        `).join('');
        suggestions.style.display = 'block';
    }

    getDurationLabel(song) {
        if (song && typeof song.duration === 'number' && isFinite(song.duration) && song.duration > 0) {
            const mins = Math.floor(song.duration / 60);
            const secs = Math.floor(song.duration % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }
        return song.album || '';
    }

    clearSuggestions() {
        const suggestions = document.getElementById('searchSuggestions');
        suggestions.style.display = 'none';
        suggestions.innerHTML = '';
    }

    clearSearch() {
        const input = document.getElementById('searchInput');
        input.value = '';
        this.clearSuggestions();
        document.getElementById('searchInput').focus();
    }

    async loadArtistSongs(artistId, artistName) {
        const isCurrentlyPlaying = !this.audio.paused;
        const currentTime = this.audio.currentTime;
        const currentSrc = this.audio.src;

        this.debug(`Loading songs for artist: ${artistName} (${artistId})`);

        try {
            const response = await fetch(`api/artist_songs.php?artistId=${encodeURIComponent(artistId)}`);
            const data = await response.json();
            const songs = Array.isArray(data) ? data : [];

            this.dashboardMode = 'popular';

            this.currentSongs = songs;
            this.currentIndex = 0;

            const container = document.querySelector('.content-grid');
            const cardsHtml = songs.length
                ? songs.map(song => this.getSongCardHtml(song)).join('')
                : '<div class="no-results">No songs found for this artist.</div>';

            container.innerHTML = `
                <div class="section full-width">
                    <h2>${this.escapeHtml(artistName)}</h2>
                    <button id="backToHomeBtn" class="back-btn" style="margin-bottom: 20px;">← Back to Home</button>
                    <div class="songs-grid" id="artistSongs">
                        ${cardsHtml}
                    </div>
                </div>
            `;

            if (isCurrentlyPlaying && currentSrc && this.audio.src !== currentSrc) {
                this.audio.src = currentSrc;
                this.audio.load();
                this.audio.currentTime = currentTime;
                this.audio.play();
            }

            this.debug(`Loaded ${songs.length} songs for this artist: ${artistName}`);
        } catch (e) {
            this.debug('Error loading artist songs: ' + e.message, 'error');
        }
    }

    async goHome() {
        const currentSongId = this.currentSong ? this.currentSong.id : null;

        this.dashboardMode = 'popular';
        this.dashboardPlaylistId = null;
        this.dashboardPlaylistName = '';

        await this.renderHomeContent();

        this.currentOffset = 0;
        try {
            await this.loadSongsPage();
        } catch (e) {
            this.debug('goHome refresh failed: ' + e.message, 'error');
        }

        if (currentSongId) {
            this.setActiveCard(currentSongId);
        }
    }

    async renderHomeContent() {
        const songs = window.initialSongs || this.currentSongs || [];
        this.currentSongs = songs;
        this.currentIndex = 0;
        this.currentOffset = 0;
        this.totalSongs = Math.max(this.totalSongs || 0, songs.length || 0, 500);

        const artists = await this.fetchArtists(6);
        const songsHtml = songs.map(song => this.getSongCardHtml(song)).join('');
        const artistsHtml = artists.map(artist => `
            <a href="#" class="genre-card artist-card" data-artist-id="${artist.id}" data-artist-name="${artist.name}">
                <div class="genre-icon"><i class="fas fa-music"></i></div>
                <span>${artist.name}</span>
            </a>
        `).join('');

        const homeMarkup = `
            <div class="section">
                <h2>Popular Songs</h2>
                <div class="songs-grid" id="popularSongs">
                    ${songsHtml}
                </div>
                <div class="pagination-controls" style="text-align: center; margin: 20px 0;">
                    <button id="prevSongsBtn" class="pagination-btn" style="display: none;">← Previous</button>
                    <span id="songPageInfo" style="margin: 0 20px;">Page 1</span>
                    <button id="nextSongsBtn" class="pagination-btn">Next →</button>
                </div>
            </div>
            <div class="section">
                <h2>Artists</h2>
                <div class="genres-grid" id="genresGrid">
                    ${artistsHtml}
                </div>
                <div style="text-align: center; margin: 20px 0;">
                    <button id="loadMoreArtists" class="show-more-btn">Show More Artists</button>
                </div>
            </div>
        `;

        const container = document.querySelector('.content-grid');
        container.innerHTML = homeMarkup;

        if (this.currentOffset === 0) {
            const nextBtn = document.getElementById('nextSongsBtn');
            const prevBtn = document.getElementById('prevSongsBtn');
            if (nextBtn) nextBtn.style.display = (this.currentOffset + this.pageSize < this.totalSongs) ? 'inline-block' : 'none';
            if (prevBtn) prevBtn.style.display = 'none';
        }

        this.updatePaginationInfo();
    }

    async fetchArtists(limit = 6) {
        try {
            const response = await fetch('api/artists.php');
            const artists = await response.json();
            return Array.isArray(artists) ? artists.slice(0, limit) : [];
        } catch (e) {
            this.debug('Error fetching artists for home view: ' + e.message, 'error');
            return [];
        }
    }

    getSongCardHtml(song) {
        const isPlaylistMode = this.dashboardMode === 'playlist' && Number.isFinite(this.dashboardPlaylistId);
        const removeButton = isPlaylistMode
            ? `<button class="song-card-option" type="button" data-song-option="remove-from-playlist" data-playlist-id="${this.dashboardPlaylistId}" data-song-id="${song.id}">Remove from playlist</button>`
            : '';
        const addButton = (window.DVIBES && window.DVIBES.isLoggedIn && !window.DVIBES.isGuest && !isPlaylistMode)
            ? `<button class="song-card-option" type="button" data-song-option="add-to-playlist" data-song-id="${song.id}">Add to playlist</button>`
            : '';
        const playNextButton = `<button class="song-card-option" type="button" data-song-option="play-next" data-song-id="${song.id}">Play next</button>`;
        const shareButton = `<button class="song-card-option" type="button" data-song-option="share" data-song-id="${song.id}">Share</button>`;

        return `
            <div class="song-card" data-id="${song.id}" data-title="${song.title}" data-artist="${song.artists || ''}" data-src="${song.audio_url || ''}" data-thumbnail="${song.thumbnail_url || ''}">
                <div class="song-image">
                    <img src="${song.thumbnail_url || 'assets/images/default-album.jpg'}" alt="${song.title}">
                    <div class="play-overlay"><i class="fas fa-play"></i></div>
                </div>
                <div class="song-info">
                    <button class="song-menu-btn" type="button" title="More options"><i class="fas fa-ellipsis-h"></i></button>
                    <div class="song-card-menu">
                        ${playNextButton}
                        ${shareButton}
                        ${addButton}
                        ${removeButton}
                    </div>
                    <div class="song-title">${song.title}</div>
                    <div class="song-movie">${song.artists || ''}</div>
                    <div class="song-genre">${this.getDurationLabel(song)}</div>
                    
                </div>
            </div>
        `;
    }

    updatePaginationInfo() {
        const pageNum = (this.currentOffset / this.pageSize) + 1;
        const songPageInfo = document.getElementById('songPageInfo');
        if (songPageInfo) {
            songPageInfo.textContent = `Page ${pageNum} of ${Math.max(1, Math.ceil(this.totalSongs / this.pageSize))}`;
        }
    }

    displaySearchResults(songs) {
        const container = document.querySelector('.content-grid');
        const resultsHtml = songs.length
            ? songs.map(song => this.getSongCardHtml(song)).join('')
            : '<div class="no-results">No songs found. Try a different search.</div>';

        container.innerHTML = `
            <div class="section full-width">
                <h2>Search Results</h2>
                <button id="backToHomeBtn" class="back-btn" style="margin-bottom: 20px;">← Back to Home</button>
                <div class="songs-grid" id="searchResults">
                    ${resultsHtml}
                </div>
            </div>
        `;
        this.currentSongs = songs;
    }

    async loadMoreArtists() {
        try {
            const response = await fetch('api/artists.php');
            const artists = await response.json();
            if (Array.isArray(artists)) {
                const grid = document.getElementById('genresGrid');
                grid.innerHTML = artists.map(artist =>
                    `<a href="#" class="genre-card artist-card" data-artist-id="${artist.id}" data-artist-name="${artist.name}">
                        <div class="genre-icon"><i class="fas fa-music"></i></div>
                        ${artist.name}
                    </a>`
                ).join('');
                document.getElementById('loadMoreArtists').style.display = 'none';
            }
        } catch (e) {
            this.debug('Error loading more artists: ' + e.message, 'error');
        }
    }

    async loadNextPage() {
        const nextOffset = this.currentOffset + this.pageSize;
        if (nextOffset < this.totalSongs) {
            this.currentOffset = nextOffset;
            await this.loadSongsPage();
        }
    }

    async loadPrevPage() {
        if (this.currentOffset > 0) {
            this.currentOffset = Math.max(0, this.currentOffset - this.pageSize);
            await this.loadSongsPage();
        }
    }

    async loadSongsPage() {
        try {
            const seed = encodeURIComponent(this.browseShuffleSeed || '');
            const response = await fetch(`api/random_songs.php?limit=${this.pageSize}&offset=${this.currentOffset}&seed=${seed}`);
            const data = await response.json();

            if (data.error) {
                this.debug('Error loading songs: ' + data.error, 'error');
                return;
            }

            this.totalSongs = data.total;
            this.currentSongs = data.songs;
            this.currentIndex = 0;

            const grid = document.getElementById('popularSongs');
            const html = data.songs.map(song => this.getSongCardHtml(song)).join('');

            grid.innerHTML = html;

            const pageNum = (this.currentOffset / this.pageSize) + 1;
            document.getElementById('songPageInfo').textContent = `Page ${pageNum} of ${Math.ceil(this.totalSongs / this.pageSize)}`;

            const nextBtn = document.getElementById('nextSongsBtn');
            const prevBtn = document.getElementById('prevSongsBtn');
            if (nextBtn) nextBtn.style.display = (this.currentOffset + this.pageSize < this.totalSongs) ? 'inline-block' : 'none';
            if (prevBtn) prevBtn.style.display = (this.currentOffset > 0) ? 'inline-block' : 'none';

            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (e) {
            this.debug('Error loading songs page: ' + e.message, 'error');
        }
    }

    toggleMenu() {
        const appContainer = document.querySelector('.app-container');
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const isMobile = window.matchMedia('(max-width: 768px)').matches;

        if (isMobile) {
            this.clearSuggestions();
            const willOpen = !sidebar.classList.contains('open');
            sidebar.classList.toggle('open', willOpen);
            if (backdrop) backdrop.classList.toggle('show', willOpen);
        } else {
            appContainer.classList.toggle('sidebar-collapsed');
        }
    }

    toggleDebug() {
        const console = document.getElementById('debugConsole');
        console.style.display = console.style.display === 'block' ? 'none' : 'block';
    }

    debug(message, type = 'log') {
        if (!this.debugEnabled) return;

        const timestamp = new Date().toLocaleTimeString();
        const log = document.createElement('div');
        log.className = `debug-log ${type}`;
        log.innerHTML = `<strong>[${timestamp}]</strong> ${message}`;

        const content = document.getElementById('debugContent');
        content.appendChild(log);
        content.scrollTop = content.scrollHeight;

        console.log(`[${timestamp}] ${message}`);
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', async () => {
    window.musicApp = new MusicStreamApp();

    // If a shared song link is opened (tokenized or id), auto-load and play that song.
    const params = new URLSearchParams(window.location.search);
    const sharedSongToken = params.get('s');
    const sharedSongId = params.get('song');
    if (sharedSongToken || sharedSongId) {
        await window.musicApp.loadSongFromUrl();
        return;
    }

    // Load initial songs from the popular songs displayed on page
    const songCards = document.querySelectorAll('#popularSongs .song-card');
    if (songCards.length > 0) {
        const initialSongs = Array.from(songCards).map(card => ({
            id: card.dataset.id,
            title: card.dataset.title,
            artists: card.dataset.artist,
            audio_url: card.dataset.src,
            thumbnail_url: card.dataset.thumbnail
        }));
        window.musicApp.setCurrentSongs(initialSongs);
        window.musicApp.currentOffset = 0;
        window.musicApp.totalSongs = 500; // Approximate total
        console.log('MusicStream: initialSongs loaded from page -', initialSongs.length);
    }

    // Try to restore previous playback state (last song + position)
    try { await window.musicApp.restorePlaybackState(); } catch(e){}
});

