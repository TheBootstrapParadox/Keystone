# HTTPS Setup for Laravel Sail

Passkeys (WebAuthn) **require HTTPS** to function properly. This guide will help you set up HTTPS for your local development environment using Laravel Sail.

## Why HTTPS is Required

WebAuthn/Passkey authentication is only available over HTTPS connections (except for `localhost` which is treated as a secure context). To properly test passkey functionality, you need to set up HTTPS locally.

## Method 1: Using mkcert (Recommended)

[mkcert](https://github.com/FiloSottile/mkcert) is a simple tool for making locally-trusted development certificates.

### Step 1: Install mkcert

**macOS:**
```bash
brew install mkcert
brew install nss # if you use Firefox
```

**Linux:**
```bash
# Ubuntu/Debian
sudo apt install libnss3-tools
wget -O mkcert https://github.com/FiloSottile/mkcert/releases/download/v1.4.4/mkcert-v1.4.4-linux-amd64
chmod +x mkcert
sudo mv mkcert /usr/local/bin/
```

**Windows:**
```powershell
choco install mkcert
```

### Step 2: Install Local CA

```bash
mkcert -install
```

This installs a local Certificate Authority (CA) in your system's trust store.

### Step 3: Generate Certificates

Navigate to your Laravel project root and create an `ssl` directory:

```bash
mkdir -p docker/ssl
cd docker/ssl
```

Generate certificates for your local domain:

```bash
mkcert localhost 127.0.0.1 ::1
```

This creates two files:
- `localhost+2.pem` (certificate)
- `localhost+2-key.pem` (private key)

Rename them for clarity:

```bash
mv localhost+2.pem cert.pem
mv localhost+2-key.pem key.pem
```

### Step 4: Update docker-compose.yml

Modify your `docker-compose.yml` to use HTTPS:

```yaml
services:
    laravel.test:
        build:
            context: ./vendor/laravel/sail/runtimes/8.3
            dockerfile: Dockerfile
            args:
                WWWGROUP: '${WWWGROUP}'
        image: sail-8.3/app
        extra_hosts:
            - 'host.docker.internal:host-gateway'
        ports:
            - '${APP_PORT:-80}:80'
            - '${VITE_PORT:-5173}:${VITE_PORT:-5173}'
            - '443:443'  # Add HTTPS port
        environment:
            WWWUSER: '${WWWUSER}'
            LARAVEL_SAIL: 1
            XDEBUG_MODE: '${SAIL_XDEBUG_MODE:-off}'
            XDEBUG_CONFIG: '${SAIL_XDEBUG_CONFIG:-client_host=host.docker.internal}'
            IGNITION_LOCAL_SITES_PATH: '${PWD}'
        volumes:
            - '.:/var/www/html'
            - './docker/ssl:/etc/nginx/ssl'  # Mount SSL certificates
        networks:
            - sail
        depends_on:
            - mysql
            - redis
```

### Step 5: Update Nginx Configuration

Create a new Nginx configuration file at `docker/nginx/default.conf`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name localhost;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name localhost;
    root /var/www/html/public;

    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Mount this configuration in your `docker-compose.yml`:

```yaml
volumes:
    - '.:/var/www/html'
    - './docker/ssl:/etc/nginx/ssl'
    - './docker/nginx/default.conf:/etc/nginx/sites-available/default'
```

### Step 6: Update .env

Update your `.env` file to use HTTPS:

```env
APP_URL=https://localhost
ASSET_URL=https://localhost
SESSION_SECURE_COOKIE=true
```

### Step 7: Restart Sail

```bash
./vendor/bin/sail down
./vendor/bin/sail up -d
```

Your application should now be accessible at `https://localhost`

## Method 2: Using Caddy (Alternative)

Laravel Sail can also use Caddy, which automatically handles HTTPS:

### Step 1: Create a Caddyfile

Create `docker/caddy/Caddyfile`:

```
localhost {
    reverse_proxy laravel.test:80
    tls internal
}
```

### Step 2: Add Caddy to docker-compose.yml

```yaml
services:
    caddy:
        image: caddy:latest
        ports:
            - "443:443"
            - "443:443/udp"
        volumes:
            - ./docker/caddy/Caddyfile:/etc/caddy/Caddyfile
            - caddy_data:/data
            - caddy_config:/config
        networks:
            - sail
        depends_on:
            - laravel.test

volumes:
    caddy_data:
    caddy_config:
```

### Step 3: Trust Caddy's Root Certificate

```bash
# macOS
./vendor/bin/sail exec caddy caddy trust

# Linux
sudo ./vendor/bin/sail exec caddy caddy trust
```

## Verifying HTTPS Setup

1. Visit `https://localhost` in your browser
2. Check that the connection is secure (padlock icon)
3. Test passkey registration to ensure WebAuthn is working

## Troubleshooting

### Certificate Not Trusted

- **Browser:** Clear browser cache and restart
- **System:** Re-run `mkcert -install`
- **Firefox:** Firefox uses its own certificate store. Install `libnss3-tools` and re-run `mkcert -install`

### Port Already in Use

```bash
# Check what's using port 443
sudo lsof -i :443

# Kill the process or change the port in docker-compose.yml
```

### WebAuthn Still Not Working

- Ensure `APP_URL` is set to `https://localhost`
- Check browser console for errors
- Verify that `window.isSecureContext` returns `true` in browser console

## Production Considerations

For production deployments:

1. Use a real SSL certificate (Let's Encrypt, CloudFlare, etc.)
2. Set up proper DNS records
3. Configure `APP_URL` to your production domain
4. Update `keystone.php` passkey configuration:
   ```php
   'rp_id' => env('PASSKEY_RP_ID', 'yourdomain.com'),
   ```

## Additional Resources

- [mkcert Documentation](https://github.com/FiloSottile/mkcert)
- [Laravel Sail Documentation](https://laravel.com/docs/sail)
- [WebAuthn Guide](https://webauthn.guide/)
- [Caddy Documentation](https://caddyserver.com/docs/)
