# Immagine base aggiornata con PHP 8.2, Apache e Composer
FROM php:8.2-apache

# Installa estensioni PHP necessarie
RUN apt-get update && apt-get install -y \
    unzip zip git libzip-dev libonig-dev libssl-dev \
    && docker-php-ext-install zip \
    && apt-get clean

# Abilita mod_rewrite (utile per Laravel, Symfony ecc.)
RUN a2enmod rewrite

# Installa Composer (dall'immagine ufficiale)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Imposta la directory di lavoro
WORKDIR /var/www/html

# Copia i file composer prima per cache build
COPY composer.json composer.lock ./

# Installa le dipendenze PHP
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Copia il resto dei file del progetto
COPY . .

# Espone la porta 80
EXPOSE 80

# Comando per avviare Apache
CMD ["apache2-foreground"]






