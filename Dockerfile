FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
COPY upload ./upload
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --classmap-authoritative

FROM php:8.4-apache-bookworm

ARG LIGHTDOCS_VERSION=development
ENV LIGHTDOCS_SITE_DIR=/var/lib/lightdocs \
    LIGHTDOCS_STATE_DIR=/var/lib/lightdocs/storage \
    LIGHTDOCS_ENV_FILE=/var/lib/lightdocs/lightdocs.env \
    APACHE_DOCUMENT_ROOT=/var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl git libonig-dev libsqlite3-dev libzip-dev unzip \
    && docker-php-ext-install -j"$(nproc)" mbstring pdo_sqlite zip \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . ./
COPY --from=vendor /app/upload/vendor ./upload/vendor
COPY resources/starter-site /usr/share/lightdocs/starter-site
COPY deploy/docker/apache-site.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/docker/entrypoint.sh /usr/local/bin/lightdocs-entrypoint

RUN version="${LIGHTDOCS_VERSION#v}" && printf '%s\n' "$version" > VERSION \
    && php bin/build-css.php \
    && rm -rf content storage \
    && mkdir -p /var/lib/lightdocs/storage/uploads \
    && chown -R www-data:www-data /var/lib/lightdocs \
    && chmod 0755 /usr/local/bin/lightdocs-entrypoint

VOLUME ["/var/lib/lightdocs"]
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl --fail --silent --show-error http://127.0.0.1/healthz || exit 1

ENTRYPOINT ["lightdocs-entrypoint"]
CMD ["apache2-foreground"]
