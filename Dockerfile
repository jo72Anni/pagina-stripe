FROM php:8.2-apache

# Installa dipendenze di sistema + git + zip
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    libpq-dev \
    git \
    unzip \
    zip \
    && docker-php-ext-install zip pdo pdo_pgsql mbstring curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configura Apache (fix per ServerName e abilita rewrite)
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && a2enmod rewrite

# Installa Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copia composer files
COPY composer.json composer.lock ./

# Installa dipendenze (NON come root per evitare warning)
RUN adduser --disabled-password --gecos '' composer-user \
    && chown -R composer-user:composer-user /var/www/html \
    && su composer-user -c "composer install --no-dev --no-scripts --prefer-dist --no-interaction --optimize-autoloader"

# Copia il resto dell'app
COPY . .

# Imposta permessi (SOLO sulla cartella principale)
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]