<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
$browseShuffleSeed = bin2hex(random_bytes(16));
$headerUserRow = auth_current_user_id() !== null ? auth_current_user_row($pdo) : null;
include 'includes/header.php';
?>

<div class="hero-section">
    <?php
    $headerUserRow = $headerUserRow ?? (auth_current_user_id() !== null ? auth_current_user_row($pdo) : null);
    if ($headerUserRow && empty($headerUserRow['is_guest'])): ?>
        <h2 class="user-greeting">Hello <?= htmlspecialchars($headerUserRow['display_name'] ?? 'User') ?></h2>
    <?php endif; ?>
    <h1 class="welcome-title">Welcome to DVibes</h1>

    <p>Discover and stream your favorite music</p>
</div>

<div class="content-grid">
    <div class="section">
        <h2>Popular Songs</h2>
        <div class="songs-grid" id="popularSongs">
            <?php
            $songs = getAllSongs($pdo, 20, 0, $browseShuffleSeed);
            foreach ($songs as $song): ?>
                <div class="song-card" data-id="<?= $song['id'] ?>" data-title="<?= htmlspecialchars($song['title']) ?>"
                    data-artist="<?= htmlspecialchars($song['artists'] ?? '') ?>" data-src="<?= htmlspecialchars($song['audio_url'] ?? '') ?>" data-thumbnail="<?= htmlspecialchars($song['thumbnail_url'] ?? '') ?>">

                    <div class="song-image">
                        <img src="<?= htmlspecialchars($song['thumbnail_url'] ?? 'assets/images/default-album.jpg') ?>" alt="<?= htmlspecialchars($song['title']) ?>">
                        <div class="play-overlay">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
                    <div class="song-info">
                        <button class="song-menu-btn" type="button" title="More options"><i class="fas fa-ellipsis-h"></i></button>
                        <div class="song-card-menu">
                            <button class="song-card-option" type="button" data-song-option="play-next" data-song-id="<?= $song['id'] ?>">Play next</button>
                            <button class="song-card-option" type="button" data-song-option="share" data-song-id="<?= $song['id'] ?>">Share</button>
                            <?php
                                $u = auth_current_user_id() !== null ? auth_current_user_row($pdo) : null;
                                if ($u && empty($u['is_guest'])): ?>
                                <button class="song-card-option" type="button" data-song-option="add-to-playlist" data-song-id="<?= $song['id'] ?>">Add to playlist</button>
                            <?php endif; ?>
                        </div>
                        <div class="song-title"><?= htmlspecialchars($song['title']) ?></div>
                        <div class="song-movie"><?= htmlspecialchars($song['artists'] ?? '') ?></div>
                        <div class="song-genre"><?= htmlspecialchars(!empty($song['duration']) ? formatDuration($song['duration']) : '') ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
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
            <?php
            $artists = getArtists($pdo, 6);
            foreach ($artists as $artist): ?>
                <a href="#" class="genre-card artist-card" data-artist-id="<?= $artist['id'] ?>" data-artist-name="<?= htmlspecialchars($artist['name']) ?>">
                    <div class="genre-icon">
                        <i class="fas fa-music"></i>
                    </div>
                    <span><?= htmlspecialchars($artist['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <div style="text-align: center; margin: 20px 0;">
            <button id="loadMoreArtists" class="show-more-btn">Show More Artists</button>
        </div>
    </div>
</div>

<script>
    window.initialSongs = <?php echo json_encode($songs); ?>;
    window.browseShuffleSeed = <?php echo json_encode($browseShuffleSeed); ?>;
</script>

<?php include 'includes/footer.php'; ?>