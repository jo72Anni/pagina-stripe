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

# ESPONI LA PORTA 4242 (QUESTA È LA MODIFICA IMPORTANTE)
EXPOSE 4242

# Configura Apache per ascoltare sulla porta 4242
RUN echo "Listen 4242" > /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:4242>/g' /etc/apache2/sites-available/000-default.conf

# Avvia Apache
CMD ["apache2-foreground"]