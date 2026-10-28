#!/bin/sh
# Point d'entrée commun au web, au worker et aux migrations (design D3).
# Les caches Laravel se construisent ici, une fois l'environnement du conteneur connu :
# au build, config:cache figerait des variables vides.
set -e

php artisan optimize

exec "$@"
