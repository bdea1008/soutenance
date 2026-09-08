# API Laravel AndTabbax — PHP 8.4 en PHP-FPM.
#
# Le serveur HTTP n'est pas ici : c'est le conteneur `web` (nginx, image du
# frontend) qui sert les fichiers statiques et relaie /api en FastCGI vers ce
# conteneur. Rien n'est donc exposé publiquement par cette image.

# --- Étape 1 : dépendances Composer -----------------------------------------
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./

# Les dépendances de développement sont installées volontairement : le seeder
# de démonstration, joué au premier démarrage, s'appuie sur les factories, donc
# sur `fakerphp/faker` — que Laravel range par convention dans require-dev. Les
# garder permet aussi de lancer la suite de tests dans le conteneur
# (`docker compose exec backend php artisan test`).
#
# --no-scripts : les scripts Laravel (package:discover) ont besoin du code
# applicatif, absent à ce stade. Le manifeste des paquets est reconstruit tout
# seul au premier démarrage.
RUN composer install \
      --no-scripts \
      --no-autoloader \
      --prefer-dist \
      --no-interaction \
      --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-scripts

# --- Étape 2 : image d'exécution --------------------------------------------
FROM php:8.4-fpm-alpine

# oniguruma/libzip/icu : dépendances de compilation des extensions ci-dessous.
# pdo_mysql est la cible du projet ; pdo_sqlite (fourni par l'image) sert aux
# tests, qui tournent sur une base en mémoire.
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS oniguruma-dev libzip-dev icu-dev \
    && apk add --no-cache libzip icu-libs \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring bcmath zip intl opcache pcntl \
    && apk del .build-deps

COPY docker/php.ini /usr/local/etc/php/conf.d/andtabbax.ini

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/cache/data storage/framework/sessions \
                storage/framework/views storage/logs storage/app/private \
                bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
