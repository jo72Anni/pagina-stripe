# Usa un'immagine base con PHP e Apache
FROM php:7.4-apache

# Installa le dipendenze necessarie per il tuo progetto
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Copia i file del tuo progetto nel container
COPY . /var/www/html/

# Imposta la directory di lavoro
WORKDIR /var/www/html

# Installa le dipendenze PHP tramite Composer (Stripe è una di queste)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install

# Esponi la porta 4242 per il traffico Stripe
EXPOSE 4242

# Avvia Apache in primo piano per mantenere il container attivo
CMD ["apache2-foreground"]

