FROM php:8.5-apache

RUN apt-get update && apt-get install -y \
    libaio1t64 \
    unzip \
    wget \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /opt/oracle && cd /opt/oracle && \
    wget https://download.oracle.com/otn_software/linux/instantclient/instantclient-basiclite-linuxx64.zip && \
    wget https://download.oracle.com/otn_software/linux/instantclient/instantclient-sdk-linuxx64.zip && \
    unzip instantclient-basiclite-linuxx64.zip && \
    unzip -o instantclient-sdk-linuxx64.zip && \
    rm *.zip

ENV LD_LIBRARY_PATH=/opt/oracle/instantclient_23_26
ENV ORACLE_HOME=/opt/oracle/instantclient_23_26

RUN echo "instantclient,/opt/oracle/instantclient_23_26" | pecl install oci8 && \
    docker-php-ext-enable oci8

RUN echo "instantclient,/opt/oracle/instantclient_23_26" | pecl install pdo_oci && \
    docker-php-ext-enable pdo_oci && \
    docker-php-ext-install pdo

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/

# Commandes à lancer :
# docker build -t mon-app .
# docker run -d -p 8080:80 mon-app:latest
