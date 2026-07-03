# MusicStream App Documentation

## Overview
MusicStream is a web-based music streaming application built with PHP, MySQL, HTML, CSS, and JavaScript. It provides a Spotify-like interface for browsing, searching, and playing music tracks. The application is designed to run on a local server environment like XAMPP.

## Application Architecture

### Technology Stack
- **Backend**: PHP 7+ with PDO for database operations
- **Database**: MySQL 5.7+ with InnoDB engine
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **UI Framework**: Custom CSS with Font Awesome icons
- **Audio Playback**: HTML5 Audio API

### File Structure
```
music_stream_app/
├── index.php              # Main homepage
├── genre.php              # Genre-specific song listing
├── songs.php              # (Empty) - Potential song listing page
├── api/                   # REST API endpoints
│   ├── genres.php         # Returns all genres as JSON
│   ├── search.php         # Search songs API
│   ├── player.php         # (Empty) - Potential player API
│   └── songs.php          # (Empty) - Potential songs API
├── assets/                # Static assets
│   ├── css/
│   │   └── style.css      # Main stylesheet
│   ├── js/
│   │   └── app.js         # Main JavaScript application
│   └── images/
│       └── default-album.jpg  # Default album artwork
├── config/
│   └── database.php       # Database connection configuration
├── includes/              # PHP includes
│   ├── header.php         # HTML head and navigation
│   ├── footer.php         # Player controls and closing tags
│   └── functions.php      # Database query functions
├── music/                 # Database setup files
│   ├── music_stream.sql   # Complete database schema and data
│   ├── generate_music_sql.py  # Python script to generate SQL
│   └── database_usage_instructions.txt  # Setup instructions
└── README.MD              # (Empty) - Project documentation
```

## Database Structure

### Database Name: `music_stream`

### Tables

#### `genres` Table
- **Purpose**: Stores music genre categories
- **Columns**:
  - `id` (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
  - `name` (VARCHAR(80), NOT NULL) - Display name
  - `slug` (VARCHAR(80), NOT NULL, UNIQUE) - URL-friendly identifier
  - `description` (VARCHAR(255)) - Optional description

#### `songs` Table
- **Purpose**: Stores individual music tracks
- **Columns**:
  - `id` (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
  - `title` (VARCHAR(255), NOT NULL) - Song title
  - `movie_name` (VARCHAR(255)) - Movie/album name (used as artist)
  - `year` (YEAR) - Release year
  - `genre_id` (INT UNSIGNED, NOT NULL) - Foreign key to genres
  - `file_path` (VARCHAR(512), NOT NULL) - Path to MP3 file
  - `is_active` (TINYINT(1), DEFAULT 1) - Active status flag
  - `created_at` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)
  - `updated_at` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP ON UPDATE)

### Relationships
- `songs.genre_id` → `genres.id` (Foreign Key Constraint)
- Indexes: genre_id, year, title, fulltext on (title, movie_name)

### Data Storage and Fetching

#### Data Storage
- **Location**: MP3 files stored in `music/` directory
- **Naming Convention**: `{Movie Name} ({Year}) - {Song Title}.mp3`
- **Database Records**: File paths stored in `songs.file_path`
- **Genre Assignment**: Automatic categorization based on keywords in filenames

#### Data Fetching Methods

##### PHP Functions (includes/functions.php)
1. `getAllSongs($pdo, $limit, $offset)` - Get paginated song list
2. `searchSongs($pdo, $query, $limit, $offset)` - Full-text search on title and movie_name
3. `getSongsByGenre($pdo, $genreSlug, $limit, $offset)` - Songs filtered by genre
4. `getGenres($pdo)` - All genres list
5. `getSongById($pdo, $id)` - Single song details
6. `getSongUrl($filePath)` - Convert file path to web-accessible URL

##### API Endpoints
1. `api/genres.php` - Returns JSON array of all genres
2. `api/search.php?q={query}` - Returns JSON array of search results

## Features and Functionality

### Core Features

#### 1. Music Playback
- **HTML5 Audio Player**: Native browser audio playback
- **Controls**: Play/Pause, Previous/Next, Progress bar, Volume control
- **Playlist Navigation**: Sequential playback through current song list
- **Progress Tracking**: Real-time progress updates and seeking

#### 2. Browse by Genre
- **Genre Navigation**: Sidebar with clickable genre links
- **Genre Pages**: Dedicated pages showing songs in specific genres
- **URL Structure**: `genre.php?slug={genre-slug}`

#### 3. Search Functionality
- **Real-time Search**: Instant search with suggestions
- **Full-text Search**: Searches across song titles and movie names
- **Search Results**: Dynamic results display replacing main content
- **Keyboard Shortcuts**: Enter to search, suggestions dropdown

#### 4. Responsive Design
- **Mobile Support**: Collapsible sidebar, touch-friendly controls
- **Adaptive Layout**: Desktop sidebar, mobile overlay menu
- **Modern UI**: Gradient backgrounds, blur effects, Font Awesome icons

### User Interface Components

#### Navigation
- **Sidebar**: Logo, Home link, Search toggle, Genre list
- **Top Bar**: Search input (toggleable), Menu button
- **Mobile Menu**: Overlay sidebar with close button

#### Content Areas
- **Hero Section**: Welcome message on homepage
- **Song Grid**: Card-based layout for song display
- **Genre Grid**: Icon-based genre selection
- **Search Results**: Dynamic content replacement

#### Player Interface
- **Track Display**: Current song title, artist, album image
- **Control Buttons**: Previous, Play/Pause, Next
- **Progress Bar**: Clickable seeking, time display
- **Volume Control**: Slider with icon

### JavaScript Application (assets/js/app.js)

#### Main Class: `MusicStreamApp`
- **Initialization**: DOM event binding, audio setup, genre loading
- **State Management**: Current song, playlist, playback status
- **Event Handling**: Player controls, search, navigation

#### Key Methods
- `playSong(songCard)` - Load and play selected song
- `togglePlay()` - Play/pause toggle
- `nextSong()` / `prevSong()` - Playlist navigation
- `search()` - API search with results display
- `loadGenres()` - Populate sidebar genre list
- `updateProgress()` - Progress bar updates

### API Design

#### RESTful Endpoints
- **GET /api/genres.php**: Retrieve all genres
- **GET /api/search.php?q={query}**: Search songs

#### Response Format
- **Success**: JSON arrays of objects
- **Error**: JSON with error message, HTTP status codes

## Setup and Installation

### Prerequisites
- PHP 7.0+
- MySQL 5.7+
- Web server (Apache/Nginx)
- XAMPP/WAMP recommended for local development

### Installation Steps
1. **Database Setup**:
   - Import `music/music_stream.sql`
   - Configure connection in `config/database.php`

2. **File Permissions**:
   - Ensure `music/` directory is readable by web server
   - MP3 files should be accessible via web

3. **Web Server Configuration**:
   - Document root pointing to project directory
   - PHP enabled with PDO MySQL extension

### Configuration
- **Database Credentials**: Update in `config/database.php`
- **File Paths**: MP3 files in `music/` directory
- **URLs**: Relative paths used throughout

## Current Limitations and Empty Files

### Incomplete Features
- `songs.php` - No content (potential all songs page)
- `api/player.php` - Empty (could be for player state management)
- `api/songs.php` - Empty (could be for song CRUD operations)

### Known Issues
- No user authentication or session management
- No playlist creation or favorites
- No audio file upload functionality
- No admin panel for content management
- Debug console always visible (development feature)

## Potential Modifications

### Database Structure Enhancements
- **Artists Table**: Separate artists from movie names
- **Albums Table**: Proper album entity with relationships
- **Playlists Table**: User-created playlists
- **User Tables**: Authentication and user preferences
- **Analytics**: Play counts, user behavior tracking

### File Structure Improvements
- **MVC Pattern**: Separate controllers, models, views
- **API Expansion**: Complete REST API for all operations
- **Asset Organization**: Better CSS/JS modularization
- **Error Handling**: Proper error pages and logging
- **Security**: Input validation, CSRF protection

### Feature Additions
- **User Registration**: Account creation and login
- **Playlist Management**: Create, edit, share playlists
- **Social Features**: Song sharing, comments
- **Offline Mode**: Service worker for caching
- **Audio Quality**: Multiple bitrate options
- **Recommendations**: Algorithm-based suggestions

This documentation provides a comprehensive overview of the MusicStream application for analysis and future modifications.</content>
<parameter name="filePath">c:\xampp\htdocs\music_stream_app\app_documentation.md