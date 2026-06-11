FROM php:8.5-apache

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_oci

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/

# Commandes à lancer :
# docker build -t mon-app .
# docker run -d -p 8080:80 mon-app:latest
