# -------------------------------------------------------------
# 1. Base Runtime: FrankenPHP + Bun + Composer + PHP Extensions
# -------------------------------------------------------------
FROM dunglas/frankenphp:latest AS base

WORKDIR /app

# Copy Bun and Composer binaries
COPY --from=oven/bun:latest /usr/local/bin/bun /usr/local/bin/bun
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install system dependencies including ffmpeg and exiftool
RUN apt-get update && apt-get install -y --no-install-recommends \
    ffmpeg \
    libimage-exiftool-perl \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions
RUN install-php-extensions \
    pdo_pgsql \
    redis \
    gd \
    exif \
    intl \
    zip \
    pcntl \
    opcache

# Copy application source code
COPY . .
COPY docker/app/Caddyfile /etc/caddy/Caddyfile
COPY docker/app/Caddyfile /etc/frankenphp/Caddyfile

# Install PHP and JS dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader
RUN bun install

# Build frontend assets
RUN bun run build

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

ENV APP_BASE_PATH="/app"
ENV FRANKENPHP_CONFIG="worker ./vendor/laravel/octane/bin/frankenphp-worker.php"

EXPOSE 80 443 443/udp

# -------------------------------------------------------------
# 2. Development Stage: zsh, Oh My Zsh, nano, curl, htop, aliases
# -------------------------------------------------------------
FROM base AS dev

RUN apt-get update && apt-get install -y --no-install-recommends \
    zsh \
    curl \
    nano \
    htop \
    procps \
    iputils-ping \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Install Oh My Zsh unattended for root and set zsh as default shell
RUN sh -c "$(curl -fsSL https://raw.githubusercontent.com/ohmyzsh/ohmyzsh/master/tools/install.sh)" "" --unattended \
    && chsh -s $(which zsh)

# Configure useful aliases in .zshrc
RUN echo '\n\
alias pa="php artisan"\n\
alias pest="./vendor/bin/pest"\n\
alias pint="./vendor/bin/pint"\n\
alias stan="./vendor/bin/phpstan analyse --memory-limit=1G"\n\
alias check="bun run check && bun run types:check"\n\
' >> /root/.zshrc

ENV SHELL=/bin/zsh

# -------------------------------------------------------------
# 3. Production Stage: Clean minimal image without dev tools
# -------------------------------------------------------------
FROM base AS production
