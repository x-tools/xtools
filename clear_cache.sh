#!/usr/bin/env bash
sudo php bin/console cache:clear --env=prod

if [ ! -d "var/cache/prod/annotations" ]; then
    mkdir var/cache/prod/annotations
fi

if [ ! -d "var/cache/prod/twig" ]; then
    mkdir var/cache/prod/twig
fi

sudo chmod -R 777 var/cache/prod/annotations

sudo chmod -R 777 var/cache/prod/twig

sudo chmod -R 777 /var/www/var/cache/prod/jms_serializer
