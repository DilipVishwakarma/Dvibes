            </div>
            </div>

            <!-- Player -->
            <div class="player-container">
                <div class="player" id="player">
                    <div class="player-track">
                        <div class="track-image">
                            <img src="assets/images/default-album.jpg" alt="Track" id="playerImage">
                        </div>
                        <div class="track-info">
                            <div class="track-title" id="playerTitle">Select a song</div>
                            <div class="track-artist" id="playerArtist">--</div>

                        </div>
                    </div>
                    <div class="player-controls">
                        <button class="control-btn" id="prevBtn" title="Previous"><i class="fas fa-step-backward"></i></button>
                        <button class="control-btn" id="shuffleBtn" title="Shuffle"><i class="fas fa-shuffle"></i></button>
                        <button class="control-btn play-pause" id="playPauseBtn" title="Play/Pause">
                            <i class="fas fa-play"></i>
                        </button>
                        <button class="control-btn" id="repeatBtn" title="Repeat"><i class="fas fa-repeat"></i></button>
                        <button class="control-btn" id="nextBtn" title="Next"><i class="fas fa-step-forward"></i></button>
                    </div>
                    <div class="player-progress">
                        <span class="current-time" id="currentTime">0:00</span>
                        <div class="progress-bar">
                            <div class="progress" id="progress"></div>
                        </div>
                        <span class="duration" id="duration">--</span>
                    </div>
                    <div class="player-volume">
                        <i class="fas fa-volume-up"></i>
                        <input type="range" id="volume" min="0" max="100" value="50">
                    </div>
                </div>
            </div>
            </div>

            <audio id="audioPlayer" preload="metadata"></audio>

            <div class="user-panel-overlay" id="userPanelOverlay">
                <div class="user-panel">
                    <div class="user-panel-header">
                        <button class="user-panel-close" id="closeUserPanelBtn" type="button" aria-label="Close panel">
                            <i class="fas fa-times"></i>
                        </button>
                        <div>
                            <div class="user-panel-title" id="userPanelTitle">Panel</div>
                            <div class="user-panel-subtitle" id="userPanelSubtitle"></div>
                        </div>
                        <button class="user-panel-back" id="backToDashboardBtn" type="button">Back to songs</button>
                    </div>
                    <div class="user-panel-body" id="userPanelBody"></div>
                </div>
            </div>

            <!-- Debug Console -->
            <div class="debug-console" id="debugConsole">
                <div class="debug-header">
                    <span>Debug Console</span>
                    <button id="toggleDebug">Hide</button>
                </div>
                <div class="debug-content" id="debugContent"></div>
            </div>

            <!-- Guest / Login Modal -->
            <div id="guestChoiceModal" class="guest-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; z-index:2000;">
                <div style="background:#111; color:#fff; max-width:420px; width:90%; padding:20px; border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.4); text-align:left; border: 1px solid rgba(29,185,84,0.12);">
                    <h3 style="margin-top:0;">You're not logged in</h3>
                    <p>You are visiting DVibes as a guest. Some features require an account.</p>

                    <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
                        <button id="guestLoginBtn" class="secondary-btn">Continue as Guest</button>
                        <button id="guestGoLoginBtn" class="primary-btn">Login</button>
                    </div>
                </div>
            </div>

            <script src="assets/js/app.js?v=<?= $GLOBALS['__appJsVersion'] ?? time() ?>"></script>
            </body>

            </html>