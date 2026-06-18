Docker Development Setup
========================

Start from the repository root:

```sh
docker compose -f docker/docker-compose.yml up --build
```

Open:

```text
http://localhost:8080/
```

Useful routes:

* `http://localhost:8080/register`
* `http://localhost:8080/login`
* `http://localhost:8080/profiles`
* `http://localhost:8080/admin`
* `http://localhost:8080/me`

The compose stack bind-mounts the repository into `/app`, so code changes on
the host are visible immediately. The PHP container writes account and page
data into the local `content/` directory.

Stop the stack:

```sh
docker compose -f docker/docker-compose.yml down
```
