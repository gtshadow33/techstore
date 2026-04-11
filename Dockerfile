FROM php:8.2-apache

# Instalar el driver de MySQL para PDO
RUN docker-php-ext-install pdo pdo_mysql

# Opcional: habilitar reescritura de URLs para Apache
RUN a2enmod rewrite