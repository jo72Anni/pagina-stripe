FROM php:8.2-apache

# Installa le dipendenze di sistema e il client PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Abilita mod_rewrite
RUN a2enmod rewrite

# Copia i file dell'applicazione
COPY . /var/www/html/

# Imposta i permessi appropriati
RUN chown -R www-data:www-data /var/www/html