# Shimlar — Modernized & Dockerized

Source code of [Shimlar.org](https://github.com/KoenVingerhoets/Shimlar) as it was in 2007, updated to run on modern infrastructure.

## What Changed from the Original

- **PHP 8.2 compatible**: All deprecated `mysql_*` functions replaced with `mysqli_*`
- **Dockerized**: Runs in containers (PHP 8.2 Apache + MySQL 8.0 + phpMyAdmin)
- **Config via environment variables**: DB credentials come from `.env`, not hardcoded
- **Database schema reconstructed**: Original repo had no schema dump — extracted from SQL queries across all source files
- **Logging path updated**: Uses configurable `LOG_DIR` instead of hardcoded `/var/www/shimlar.com/logs`

Game logic, JS, HTML, CSS, and visual layout are **untouched**.

## Quick Start

```bash
cd docker
cp .env.example .env   # edit if you want different passwords
docker compose up -d
```

- **Game**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081

## Ports

| Service    | Port | URL                        |
|------------|------|----------------------------|
| Game (web) | 8080 | http://localhost:8080      |
| phpMyAdmin | 8081 | http://localhost:8081      |
| MySQL      | 3306 | localhost:3306 (internal)  |

## Database Reset

See `howtoreset.txt` in the repo root for the original reset instructions. The schema is in `docker/schema.sql` and loads automatically on first start.

## Project Structure

```
├── docker/
│   ├── Dockerfile          — PHP 8.2 Apache image
│   ├── docker-compose.yml  — Full stack (web + db + phpmyadmin)
│   ├── schema.sql          — Reconstructed database schema
│   └── .env                — Environment config
├── httpdocs/               — Web root (served by Apache)
├── incz/                   — PHP includes (game logic)
└── README.md
```

## Original Description

This game was conceived when www.racewarkingdoms.com went to a pay to play model.
The two top players at the time, Toshax and Lord ArPharazon, recreated their world in a new and totally free online game, Shimlar.
It ran from 2000 to 2007 and resulted in a lot of copies/spin-offs.

The code is a complex interaction between code in the webserver (Apache - the httpdocs folder) and php code (in the /incz).
A command resulted in a php file being executed with the result printed into a javascript function call.
The jscripts are on the client side to draw the required lay-out on the screen.
