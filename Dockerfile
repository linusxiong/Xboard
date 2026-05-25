FROM phpswoole/swoole:php8.1-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pcntl bcmath inotify \
    && apk --no-cache add shadow supervisor nginx sqlite nginx-mod-http-brotli mysql-client git patch \
    && addgroup -S -g 1000 www \
    && adduser -S -D -H -G www -u 1000 www

WORKDIR /www

COPY .docker /
COPY patches ./patches
COPY composer.json ./
RUN composer install --no-autoloader --no-cache --no-dev --no-scripts --no-interaction

COPY . /www

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan storage:link \
    && cp /www/.env.example /www/.env \
    && chown -R www:www /www \
    && find /www -type d -exec chmod 755 {} \; \
    && find /www -type f -exec chmod 644 {} \; \
    && chmod -R ug+rwX /www/storage /www/bootstrap/cache \
    && chmod +x /www/artisan

CMD ["/usr/bin/supervisord", "--nodaemon", "-c", "/etc/supervisor/supervisord.conf"]
