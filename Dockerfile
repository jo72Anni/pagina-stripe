# Immagine base: PHP 8.2 con Apache
FROM php:8.2-apache

# Installa estensioni PHP e pacchetti di sistema necessari
RUN apt-get update && apt-get install -y \
    unzip zip git libzip-dev libonig-dev libssl-dev libpq-dev \
    && docker-php-ext-install zip pdo pdo_pgsql pgsql \
    && apt-get clean

# Abilita mod_rewrite per Apache (utile per Laravel, Symfony, ecc.)
RUN a2enmod rewrite

# Copia Composer dall'immagine ufficiale
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Imposta la cartella di lavoro
WORKDIR /var/www/html

# Copia i file di Composer separatamente per ottimizzare la cache Docker
COPY composer.json composer.lock ./

# Installa le dipendenze PHP in modalità produzione
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Copia tutti gli altri file dell'applicazione
COPY . .

# Imposta i permessi (opzionale ma consigliato per Laravel/Symfony)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Espone la porta HTTP 80
EXPOSE 80

# Comando di avvio del server Apache
CMD ["apache2-foreground"]






