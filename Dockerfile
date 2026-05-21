FROM ghcr.io/gitmavera/laravel-base:php85-node22 AS builder

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts

COPY --chown=www-data:www-data package.json package-lock.json ./
RUN npm ci

COPY --chown=www-data:www-data . /var/www/html
#RUN node bin/build.js
RUN NODE_TLS_REJECT_UNAUTHORIZED=0 npm run build

FROM ghcr.io/gitmavera/laravel-base:php85-node22

ENV TZ="Europe/Istanbul"
ENV PHP_DATE_TIMEZONE="Europe/Istanbul"
ENV PHP_OPCACHE_ENABLE=1
ENV PHP_POST_MAX_SIZE="128M"
ENV PHP_UPLOAD_MAX_FILE_SIZE="128M"
ENV PHP_MEMORY_LIMIT="256M"
ENV PHP_MAX_INPUT_VARS="1000"
ENV PHP_MAX_EXECUTION_TIME="300"
ENV HEALTHCHECK_PATH="/up"

USER root
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/laravel.conf

USER www-data
WORKDIR /var/www/html

COPY --chmod=755 ./entrypoint.d/ /etc/entrypoint.d/
COPY --chmod=755 ./docker/nginx/server-opts.d/ /etc/nginx/server-opts.d/

RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY --chown=www-data:www-data . /var/www/html
COPY --from=builder /var/www/html/vendor /var/www/html/vendor
COPY --from=builder /var/www/html/public/build /var/www/html/public/build
#COPY --from=builder /var/www/html/public/js /var/www/html/public/js