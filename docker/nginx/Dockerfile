# Production nginx: bakes in the static public/ directory (incl. Vite build)
# from the prod app image so no bind mount is needed.
ARG APP_IMAGE=bdlg-app:prod
FROM ${APP_IMAGE} AS app

FROM nginx:1.27-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
