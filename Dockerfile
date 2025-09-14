# Usa l'immagine ufficiale PHP con Apache
FROM php:8.2-apache

# Aggiorna i pacchetti e installa le dipendenze necessarie
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Installa le estensioni PHP necessarie
RUN docker-php-ext-install pdo pdo_pgsql zip

# Installa Composer dall'immagine ufficiale
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Imposta la directory di lavoro
WORKDIR /var/www/html

# Copia i file di configurazione delle dipendenze
COPY composer.json composer.lock ./

# Copia il codice dell'applicazione
COPY src/ ./src/
COPY public/ ./public/

# Installa le dipendenze PHP (solo production)
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --classmap-authoritative

# Configura Apache per servire file dalla cartella public
RUN a2enmod rewrite && \
    sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Pulisce la cache dei pacchetti per ridurre l'immagine
RUN apt-get clean && \
    rm -rf /var/cache/apt/archives/* /tmp/* /var/tmp/*

# Imposta i permessi corretti per Apache
RUN chown -R www-data:www-data /var/www/html

# Espone la porta 80 (Render gestirà il mapping)
EXPOSE 80

# Avvia Apache in foreground
CMD ["apache2-foreground"]