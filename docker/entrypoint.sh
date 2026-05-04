#!/bin/sh
set -e

php bin/console cache:clear --env="${APP_ENV:-prod}" --no-debug
php bin/console doctrine:migrations:migrate --no-interaction

exec "$@"
