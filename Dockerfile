# syntax=docker/dockerfile:1
# Image de production unique (design D2) : la commande lancée choisit le rôle.
#   web     : frankenphp run --config /etc/caddy/Caddyfile (par défaut)
#   worker  : php artisan queue:work …
#   migrate : php artisan migrate --force
# Image publique : aucun secret au build, toute la configuration arrive par l'environnement.

# FrankenPHP v1.13.0, PHP 8.5.11, Caddy v2.11.7, Debian trixie ; curl présent (healthcheck du web).
# Tag publié le 2026-10-09, choisi le 2026-10-28 (plus récent de plus de deux semaines).
ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1.13.0-php8.5.11-trixie@sha256:2a9c6663f73ad1e401909634206bd25e13bed5b94d86eefa3b5ec64c985b4595
# Composer 2.10.2 et Node 22.23.2 : outils de build seulement, absents de l'image finale.
ARG COMPOSER_IMAGE=composer:2.10.2@sha256:d020706319701a44468968321dccd0fce6620190159a7a9ec195d78e6e971c71
ARG NODE_IMAGE=node:22.23.2-trixie-slim@sha256:c5849ff9c9ebcd66615412f0b548ca5b8ecaef84003dc9ac2e077ebe46aaa3f6

FROM ${COMPOSER_IMAGE} AS composer
FROM ${NODE_IMAGE} AS node

# 1. base : PHP et ses extensions, réglages de production, utilisateur non root.
# imagick : conversion des photos de carte en WebP et réduction avant la lecture par l'IA (ADR 0011).
FROM ${FRANKENPHP_IMAGE} AS base
RUN install-php-extensions pdo_pgsql pcntl intl opcache imagick \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'opcache.validate_timestamps=0\n' > "$PHP_INI_DIR/conf.d/zz-production.ini" \
    && groupadd --gid 1000 app \
    && useradd --uid 1000 --gid app --no-create-home --shell /usr/sbin/nologin app
# Envoi des photos de carte jusqu'à 20 Mo (design D8 de scorecard-ai-import).
COPY docker/php/uploads.ini $PHP_INI_DIR/conf.d/uploads.ini
WORKDIR /app

# 2. vendor : dépendances de production, depuis composer.json et composer.lock seuls (couche en cache).
FROM base AS vendor
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN install-php-extensions zip
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.cache/composer \
    composer install --no-dev --no-scripts --no-interaction --no-progress \
        --prefer-dist --optimize-autoloader --classmap-authoritative

# 3. assets : Vite a besoin de PHP, de vendor et du code (Wayfinder lance php artisan).
FROM base AS assets
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
RUN ln -s ../lib/node_modules/corepack/dist/corepack.js /usr/local/bin/corepack \
    && corepack enable
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml .npmrc ./
RUN --mount=type=cache,target=/root/.local/share/pnpm/store \
    pnpm install --frozen-lockfile
COPY --from=vendor /app/vendor ./vendor
COPY . .
# Valeur d'APP_NAME dans .env.example : .dockerignore écarte .env, et Vite inscrit la variable
# dans le JavaScript compilé. Sans elle, l'onglet de /login affiche « Connexion - Laravel ».
ENV VITE_APP_NAME="Handicaps golf"
# L'autoloader de vendor ne connaît pas encore App\ : le classmap se refait avec le code.
RUN --mount=type=bind,from=composer,source=/usr/bin/composer,target=/usr/local/bin/composer \
    composer dump-autoload --no-dev --no-scripts --classmap-authoritative \
    && pnpm run build

# 4. runtime : le code, vendor et public/build ; rien de Node ni de Composer.
FROM base AS runtime
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint
RUN --mount=type=bind,from=composer,source=/usr/bin/composer,target=/usr/local/bin/composer \
    composer dump-autoload --no-dev --no-scripts --classmap-authoritative \
    && php artisan package:discover --ansi \
    && chown -R app:app storage bootstrap/cache /data/caddy /config/caddy

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr
# Sha complet du commit, passé par la CI ; vide hors CI, et GET /api/version répond commit: null.
ARG GIT_COMMIT=""
ENV APP_COMMIT=$GIT_COMMIT

# Relie le paquet GHCR au dépôt (design D9) : posé au build, l'image testée par la CI le porte déjà.
LABEL org.opencontainers.image.source=https://github.com/emmpaul/golf-handicaps-laravel

USER app
EXPOSE 8080
# Le healthcheck de l'image de base lit l'API d'administration de Caddy, coupée ici :
# chaque service de docker-compose.yml déclare le sien.
HEALTHCHECK NONE
ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
