#!/bin/sh
# Container health: php-fpm answers its ping endpoint. Worker containers
# (horizon, scheduler) override this in docker-compose.yml.
set -e
SCRIPT_NAME=/fpm-ping SCRIPT_FILENAME=/fpm-ping REQUEST_METHOD=GET \
    cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong
