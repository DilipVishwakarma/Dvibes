<?php
require_once 'includes/functions.php';
$genreSlug = $_GET['slug'] ?? '';
$songs = getSongsByGenre($pdo, $genreSlug);
?>
<?php include 'includes/header.php'; ?>


<div class="page-header">
    <h1><?= ucwords(str_replace('-', ' ', $genreSlug)) ?> Songs</h1>
</div>

<div class="songs-grid full-width" id="genreSongs">
    <?php foreach ($songs as $song): ?>
        <div class="song-card" data-id="<?= $song['id'] ?>" data-title="<?= htmlspecialchars($song['title']) ?>"
            data-artist="<?= htmlspecialchars($song['artists'] ?? '') ?>" data-src="<?= htmlspecialchars($song['audio_url'] ?? '') ?>">

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

<script>
    window.initialSongs = <?php echo json_encode($songs); ?>;
</script>

<?php include 'includes/footer.php'; ?>