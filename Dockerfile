# Usa immagine base PHP con Apache
FROM php:8.2-apache

# Installa le dipendenze di sistema (PER STRIPE + POSTGRES)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    git \
    unzip \
    libcurl4-openssl-dev \  # AGGIUNGI QUESTA RIGA
    && docker-php-ext-install pdo pdo_pgsql curl mbstring \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Installa Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Abilita mod_rewrite
RUN a2enmod rewrite

# Copia PRIMA solo i file di Composer (per caching)
COPY composer.json composer.lock* /var/www/html/

# Installa le dipendenze Composer (Stripe + dotenv)
WORKDIR /var/www/html/
RUN composer install --no-dev --no-interaction --optimize-autoloader

# Copia tutto il resto del progetto
COPY . /var/www/html/

# Imposta i permessi generali
RUN chown -R www-data:www-data /var/www/html

# Espone la porta 80
EXPOSE 80