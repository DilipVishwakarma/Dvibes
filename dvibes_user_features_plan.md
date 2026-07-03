# DVibes user features plan

## New/extended DB objects (to be appended to `dvibes_auth.sql`)
1) `user_listening_history`
- user_id (FK users)
- song_id (FK songs)
- played_at (timestamp)
- last_position_seconds (int, nullable)
- duration_seconds (int, nullable)

2) `user_song_likes`
- user_id (FK users)
- song_id (FK songs)
- created_at
- unique(user_id, song_id)

3) `user_playlists`
- already stub exists; will extend with `is_public`, `updated_at`

4) `user_playlist_songs`
- playlist_id (FK user_playlists)
- song_id (FK songs)
- added_at
- unique(playlist_id, song_id)

5) `user_playback_state`
- user_id (FK users)
- song_id (FK songs)
- last_position_seconds
- updated_at

6) `user_recommendation_seed`
- optional (can skip); recommendations can compute from history/likes.

## PHP/API endpoints to add
- `api/user/history.php` (POST record play, GET last N)
- `api/user/likes.php` (POST like/unlike, GET liked songs)
- `api/user/playlists.php` (CRUD + GET playlists)
- `api/user/playlist_songs.php` (POST add/remove songs)
- `api/user/playback.php` (GET resume state, POST update)
- `api/user/recommendations.php` (GET recommendations)

## Frontend integration points
- In `assets/js/app.js`, on song play:
  - POST playback state periodically (throttled)
  - POST listening history on play start
- On like button toggle:
  - POST likes
- On page load (index/player screen):
  - fetch resume state for logged in user
  - fetch recommendations list (optional)

## UI additions required
- Like button on song cards (if not already present)
- Resume behavior: if user has playback_state, load that song and seek.
- Playlist screen (can be minimal later)

