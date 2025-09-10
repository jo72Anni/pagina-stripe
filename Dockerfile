# Usa l'immagine ufficiale di PHP con Apache
FROM php:8.2-apache

# Installa le dipendenze di sistema
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring zip gd

# Abilita il mod_rewrite di Apache
RUN a2enmod rewrite

# Copia i file dell'applicazione
COPY . /var/www/html/

# Imposta i permessi
RUN chown -R www-data:www-data /var/www/html

# Esponi la porta 80
EXPOSE 80

# Avvia Apache
CMD ["apache2-foreground"]