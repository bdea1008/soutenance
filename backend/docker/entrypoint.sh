#!/bin/sh
# Démarrage de l'API AndTabbax en conteneur.
#
# Prépare l'arborescence de stockage, garantit un APP_KEY / JWT_SECRET stables,
# attend la base, migre (et amorce au premier lancement seulement), puis passe
# la main à php-fpm.
set -e

cd /var/www/html

# --- Arborescence de stockage ------------------------------------------------
# storage/ est un volume : à sa création il reprend le contenu de l'image, mais
# les dossiers ajoutés par une version ultérieure n'y apparaîtraient pas.
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/private/kyc \
         storage/app/private/reports \
         bootstrap/cache

# --- Secrets applicatifs -----------------------------------------------------
# Fournis par l'environnement en déploiement réel ; sinon engendrés une fois et
# conservés dans le volume, pour que les jetons JWT et les données chiffrées
# survivent à un redémarrage.
secrets_file=storage/app/.docker-secrets
[ -f "$secrets_file" ] || : > "$secrets_file"

# La valeur passée par l'environnement l'emporte toujours sur celle du volume :
# on relit le fichier seulement pour combler un trou, jamais pour écraser.
remembered() {
    sed -n "s/^$1=//p" "$secrets_file" | tail -n 1
}

remember() {
    printf '%s=%s\n' "$1" "$2" >> "$secrets_file"
    chmod 600 "$secrets_file"
}

[ -n "${APP_KEY:-}" ] || APP_KEY=$(remembered APP_KEY)
if [ -z "$APP_KEY" ]; then
    APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    remember APP_KEY "$APP_KEY"
    echo "→ APP_KEY engendrée (conservée dans le volume storage)."
fi
export APP_KEY

[ -n "${JWT_SECRET:-}" ] || JWT_SECRET=$(remembered JWT_SECRET)
if [ -z "$JWT_SECRET" ]; then
    JWT_SECRET="$(php -r 'echo bin2hex(random_bytes(32));')"
    remember JWT_SECRET "$JWT_SECRET"
    echo "→ JWT_SECRET engendré (conservé dans le volume storage)."
fi
export JWT_SECRET

# --- Attente de la base ------------------------------------------------------
# Le service `db` est déjà déclaré `service_healthy` côté compose ; cette boucle
# couvre le cas d'un démarrage hors compose et les quelques secondes qui
# séparent parfois « healthy » d'« accepte vraiment des connexions ».
if [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
    php -r '
        $host = getenv("DB_HOST") ?: "db";
        $port = getenv("DB_PORT") ?: "3306";
        $db   = getenv("DB_DATABASE") ?: "andtabbax";
        $user = getenv("DB_USERNAME") ?: "andtabbax";
        $pass = getenv("DB_PASSWORD") ?: "";
        for ($i = 1; $i <= 60; $i++) {
            try {
                new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
                exit(0);
            } catch (Throwable $e) {
                if ($i === 1) fwrite(STDERR, "Attente de la base ($host:$port)…\n");
                sleep(2);
            }
        }
        fwrite(STDERR, "Base injoignable après 2 min : abandon.\n");
        exit(1);
    '
fi

# --- Schéma et données -------------------------------------------------------
php artisan migrate --force --no-interaction

# Le seeder n'est pas idempotent (courriels uniques) : il ne passe qu'une fois,
# au premier démarrage du volume. Pour repartir de zéro : `docker compose down -v`.
seed_marker=storage/app/.docker-seeded
if [ "${DB_SEED:-false}" = "true" ] && [ ! -f "$seed_marker" ]; then
    echo "→ Amorçage des données de démonstration…"
    php artisan db:seed --force --no-interaction
    : > "$seed_marker"
fi

# --- Caches de production ----------------------------------------------------
# Faits ici, et pas à la construction : la configuration dépend de variables
# d'environnement qui n'existent qu'à l'exécution.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Les commandes ci-dessus tournent en root : on rend la main à php-fpm.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
