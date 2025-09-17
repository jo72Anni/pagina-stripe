# Usa immagine base PHP con Apache
FROM php:8.2.12-apache

# Installa dipendenze di sistema (solo ciò che serve)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    git \
    unzip \
 && docker-php-ext-install pdo pdo_pgsql curl mbstring \
 && apt-get purge -y --auto-remove git unzip \
 && rm -rf /var/lib/apt/lists/*

# Installa Composer (versione stabile, non latest)
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Abilita mod_rewrite
RUN a2enmod rewrite

# Directory di lavoro
WORKDIR /var/www/html/

# Copia solo i file di composer per caching
COPY composer.json composer.lock* ./

# Installa le dipendenze (Stripe + dotenv)
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --optimize-autoloader

# Copia tutto il resto del progetto (con permessi corretti)
COPY --chown=www-data:www-data . /var/www/html/

# Espone la porta 80
EXPOSE 80
