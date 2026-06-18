# rdbrr

rdbrr is a social fork of Hotglue. Each user has one account and one visual profile page that can be edited like a Hotglue canvas: drag objects, add text/images/video/web embeds, use layers, grid snapping, profile walls, and microblog updates.

## What This Adds

- Social accounts with register/login/logout.
- One profile page per user at `/u/username`.
- Profile editor at `/u/username/edit`.
- A chronological timeline at `/timeline` for the current user and followed users.
- A global feed at `/feed`.
- Follow/unfollow support.
- Profile wall messages.
- Microblog/status updates.
- Avatar upload with cropping.
- Per-user upload quota and image-only uploads.
- Admin role for user management.
- Docker setup for local testing and server deployment.

## Important Storage Notes

Runtime data is stored in `content/` and is intentionally ignored by Git:

- `content/users.json` contains accounts and password hashes.
- `content/social_messages.json` contains profile wall messages.
- `content/social_updates.json` contains microblog updates.
- `content/profile_avatars/` contains uploaded avatars.
- `content/u_<username>/` contains profile pages and uploads.

Do not commit `content/` to a public repo. Move it to a server separately with `tar`, `scp`, `rsync`, or backups.

## Local Docker Start

From the repository root:

```sh
docker compose -f docker/docker-compose.yml up -d --build
```

Open:

```text
http://localhost:8080/
```

Useful routes:

```text
http://localhost:8080/register
http://localhost:8080/login
http://localhost:8080/timeline
http://localhost:8080/feed
http://localhost:8080/profiles
http://localhost:8080/account
http://localhost:8080/admin
```

Stop:

```sh
docker compose -f docker/docker-compose.yml down
```

## Server Deploy

Install Docker and clone the repo:

```sh
git clone https://github.com/daandobber/rdbrr.git
cd rdbrr
cp user-config.inc.php-dist user-config.inc.php
```

Edit `user-config.inc.php` for your domain:

```php
@define('BASE_URL', 'https://your-domain.example/');
@define('AUTH_METHOD', 'social');
@define('SOCIAL_ACCOUNTS', true);
```

Start Docker:

```sh
docker compose -f docker/docker-compose.yml up -d --build
```

If you already have local data, copy `content/` to the server separately:

```sh
tar -czf rdbrr-content.tar.gz content
scp rdbrr-content.tar.gz user@server:/path/to/rdbrr/
```

On the server:

```sh
cd /path/to/rdbrr
tar -xzf rdbrr-content.tar.gz
sudo chown -R 33:33 content
```

## Config

Relevant settings are in `config.inc.php` and can be overridden in `user-config.inc.php`:

```php
@define('SITE_NAME', 'rdbrr');
@define('SOCIAL_ACCOUNTS', true);
@define('SOCIAL_ADMIN_USERS', '');
@define('SOCIAL_USER_UPLOAD_QUOTA', 5*1024*1024);
@define('SOCIAL_ALLOWED_UPLOAD_MIMES', 'image/jpeg image/png image/gif image/webp');
```

The first registered account becomes admin. Extra always-admin usernames can be listed in `SOCIAL_ADMIN_USERS`.

## More Details

See `doc/SOCIAL_FORK.md` for the route and storage overview.
