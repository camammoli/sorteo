# YouTube Comment Picker

Pick random winners from YouTube comment sections — transparently and verifiably. Generates a certificate signed with HMAC-SHA256 that anyone can verify against the server.

**[→ Try it at mammoli.ar/sorteo](https://mammoli.ar/sorteo/)**

---

## Features

- **Multi-video** — up to 5 videos combined in a single draw
- **Filters** — by keyword, date range, minimum likes
- **Entry cap** — maximum participations per user (1, 2, 3, 5 or unlimited)
- **Backup winners** — up to 20 ranked alternates
- **User exclusion** — channel owner is auto-detected; add more manually
- **Verifiable certificate** — HMAC-SHA256 hash, QR points to the public verification page
- **Official draw lock** — organizer can lock the video set to prevent parallel draws (1, 3, 7 or 30 days)
- **Transparent history** — if multiple draws are made for the same video set, all are listed on the verification page
- **Bilingual** — Spanish and English interface (persisted in localStorage)
- **Theme** — light and dark mode

## Stack

`PHP 8` · `SQLite` (PDO, WAL mode) · `YouTube Data API v3` · `Server-Sent Events` · no frameworks or external dependencies

## How it works

1. Enter the URL of one or more YouTube videos
2. Comments are downloaded in real time via SSE streaming
3. Configured filters are applied
4. Winners are picked using PHP's `shuffle()` (Mersenne Twister)
5. A certificate is generated with an HMAC-SHA256 hash and a verification QR code

## Self-hosting

### Requirements

- PHP 8.0+
- PDO SQLite extension
- fileinfo extension
- YouTube Data API v3 key ([console.cloud.google.com](https://console.cloud.google.com))

### Setup

```bash
git clone https://github.com/camammoli/sorteo.git
cd sorteo/web
cp config.example.php config.php
```

Edit `config.php` with your keys:

```php
define('YT_API_KEY',        'your_youtube_api_key');
define('SORTEO_ADMIN_KEY',  'generated_admin_key');
define('SORTEO_HMAC_SECRET','generated_hmac_secret');
```

Generate the local keys:

```bash
# HMAC secret (certificate signing)
openssl rand -hex 32

# Admin key (panel at /admin.php?key=...)
openssl rand -hex 16
```

The `web/data/` directory needs write permissions for SQLite:

```bash
chmod 750 web/data/
```

### Admin panel

Accessible at `/sorteo/admin.php?key=SORTEO_ADMIN_KEY` — full table of all draws with channel, hashed IP, options and status.

## Certificate verification

The `/sorteo/verificar.php` page shows server-side data for comparison with the presented document. The hash covers: draw UUID, video ID, UTC timestamp, winner comment IDs and authors.

Certificates issued before v2.3 use an MD5 hash (16 characters) and remain verifiable.

## License

MIT · [Carlos Ariel Mammoli](https://mammoli.ar)
