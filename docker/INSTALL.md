Docker Development Setup
========================

Start from the repository root:

```sh
docker compose -p rdbrr-public -f docker/docker-compose.yml up --build
```

Open:

```text
http://localhost:8080/
```

Useful public routes:

* `http://localhost:8080/register`
* `http://localhost:8080/login`
* `http://localhost:8080/profiles`
* `http://localhost:8080/me`

The compose stack bind-mounts the repository into `/app`, so code changes on
the host are visible immediately. The PHP container writes account and page
data into the local `content/` directory.

Stop the stack:

```sh
docker compose -p rdbrr-public -f docker/docker-compose.yml down
```

Optional local-only admin area and updater:

```sh
docker compose -p rdbrr-admin -f docker/docker-compose.admin.yml up -d --build
```

Open `http://127.0.0.1:8081/admin` and log in with a social admin account. On a
remote server, reach it with:

```sh
ssh -L 8081:127.0.0.1:8081 user@server
```
