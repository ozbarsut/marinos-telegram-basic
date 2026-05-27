# AGENTS.md

## Cursor Cloud specific instructions

### Project overview

This is **Marinos Chatbot** (v3.1.0) — a WordPress plugin that bridges website live chat with Telegram. The plugin has no build step, no package manager dependencies, and no automated tests. All code is self-contained PHP, JavaScript, and CSS.

### Development environment

The dev environment runs via Docker Compose (`docker-compose.yml` in the repo root):

- **WordPress** (PHP 8.2 + Apache) on `http://localhost:8080`
- **MySQL 8.0** on port 3306

Start the environment:

```
sudo dockerd &>/tmp/dockerd.log &
sleep 3
sudo docker compose up -d
```

Wait for the MySQL healthcheck to pass (~15–30 s) before interacting.

WordPress admin credentials: `admin` / `admin123`

### Plugin location

The plugin source lives at `extracted_project/marinos-chatbot-tg/` and is bind-mounted into the WordPress container at `wp-content/plugins/marinos-chatbot-tg`. Changes to plugin files on the host are immediately reflected in the running WordPress site.

### Linting

Run PHP syntax checks against all plugin files:

```
sudo docker compose exec wordpress bash -c 'for f in /var/www/html/wp-content/plugins/marinos-chatbot-tg/*.php /var/www/html/wp-content/plugins/marinos-chatbot-tg/includes/*.php; do php -l "$f"; done'
```

### Testing

There are no automated tests in this codebase. Manual testing is done through the browser:

- **Admin settings page**: WordPress Admin → Marinos Chatbot (in sidebar)
- **Frontend widget**: Visit `http://localhost:8080/` — chat widget appears in bottom-right corner
- **AJAX endpoint**: `POST /wp-admin/admin-ajax.php` with `action=marinos_chat`
- **REST webhook**: `POST /wp-json/marinos-chatbot/v1/telegram`

### Gotchas

- **Permalink structure must be set** for the REST API webhook to work. Run: `sudo docker compose exec wordpress wp rewrite structure '/%postname%/' --allow-root`
- The chat AJAX handler requires Telegram bot token and chat ID to be configured in the admin panel before messages can be fully processed (sent to Telegram). Without these, the handler returns a JSON error about missing Telegram configuration — this is expected behavior.
- **WP-CLI** is installed inside the WordPress container at `/usr/local/bin/wp`. Use `--allow-root` flag since the container runs as root.
- The `docker-compose.yml` version attribute warning is cosmetic and can be ignored.
