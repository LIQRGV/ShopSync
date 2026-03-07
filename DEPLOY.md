# Deploying the Go SSE Server

This document covers building and deploying the Go SSE server binary to production environments. The server communicates via Unix sockets in production and is managed by systemd.

## Servers

### WL (WhiteLabel) Server

| Detail | Value                                                |
|--------|------------------------------------------------------|
| Host | `<redacted>`                                         |
| SSH | `ssh <redacted>`                                     |
| Base path | `/var/www/vhosts/stockbox.tech/apple.stockbox.tech/` |
| Binary | `{base_path}/bin/sse-server`                         |
| Service user | `stockbox` (group: `psacln`)                         |
| Service name | `sse-server@apple.stockbox.tech`                     |
| PHP | `/opt/plesk/php/8.4/bin/php`                         |

### WTM (WatchTheMarket) Server

| Detail | Value                                    |
|--------|------------------------------------------|
| Host | `<redacted>`                             |
| SSH | `ssh <redacted>`                         |
| Base path | `/var/www/thestage.watchthemarket.app`   |
| Binary | `{base_path}/bin/sse-server`             |
| Service user | `www-data`                               |
| Service name | `sse-server@thestage.watchthemarket.app` |

## Build

Cross-compile for Linux AMD64 from any development machine:

```bash
cd sse-server
CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -o sse-server .
```

This produces a statically linked binary with no external dependencies.

## Deploy

Repeat these steps for each target server. Replace `{domain}`, `{host}`, and `{base_path}` with the values from the server tables above.

### 1. Stop the service

Always stop the service before replacing the binary. Copying over a running binary causes a "Text file busy" error.

```bash
systemctl stop sse-server@{domain}
```

### 2. Copy the binary

```bash
scp sse-server root@{host}:{base_path}/bin/sse-server
```

If this is the first deploy, create the directory first:

```bash
ssh root@{host} "mkdir -p {base_path}/bin/"
```

### 3. Set ownership and permissions

**WL server:**

```bash
chown stockbox:psacln {base_path}/bin/sse-server
chmod 755 {base_path}/bin/sse-server
```

**WTM server:**

```bash
chown www-data:www-data {base_path}/bin/sse-server
chmod 755 {base_path}/bin/sse-server
```

### 4. Start the service

```bash
systemctl start sse-server@{domain}
```

### 5. Verify

```bash
systemctl status sse-server@{domain}
```

## Systemd Service

The systemd service template is located at `/etc/systemd/system/sse-server@.service`. The instance name after `@` is the domain (e.g., `sse-server@apple.stockbox.tech`).

The service reads environment variables from the Laravel `.env` file at the base path. See the `config/config.go` source for the full list of supported environment variables and their defaults.

## Deploying PHP Package Changes

If the ShopSync PHP package has been updated (new routes, config changes, etc.), update it on the target server.

**WL server** (Plesk environment -- PHP and Composer are not on PATH):

```bash
cd {base_path}
/opt/plesk/php/8.4/bin/php /usr/lib/plesk-9.0/composer.phar update thediamondbox/shopsync
/opt/plesk/php/8.4/bin/php artisan config:clear
```

**WTM server** (PHP is on PATH):

```bash
cd {base_path}
composer update thediamondbox/shopsync
php artisan config:clear
```

## Important Notes

- **Stop before copy**: Always stop the systemd service before replacing the binary. Overwriting a running binary produces a "Text file busy" error.
- **Published config on WL**: If the WL app has a published config at `config/products-package.php`, changes to the package's default config will not take effect unless the published config file is also updated manually.
- **First deploy**: The `bin/` directory may not exist yet. Create it with `mkdir -p {base_path}/bin/` before copying the binary.
