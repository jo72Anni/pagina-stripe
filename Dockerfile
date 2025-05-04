# Immagine base con PHP, Apache, e Composer
FROM php:7.4-apache

# Installa solo ciò che serve
RUN apt-get update && apt-get install -y \
    unzip zip git libzip-dev libonig-dev libssl-dev \
    && docker-php-ext-install zip \
    && apt-get clean

# Abilita mod_rewrite (utile per Laravel, Symfony ecc.)
RUN a2enmod rewrite

# Installa Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copia i file del progetto
COPY . /var/www/html

# Imposta la directory di lavoro
WORKDIR /var/www/html

# Installa le dipendenze PHP
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Espone la porta 80
EXPOSE 80

# Comando per avviare Apache
CMD ["apache2-foreground"]





