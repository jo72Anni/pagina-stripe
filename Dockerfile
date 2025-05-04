# Usa un'immagine base con PHP e Apache
FROM php:7.4-apache

# Installa le dipendenze di sistema necessarie
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    zip \
    git \
    unzip \
    libssl-dev \
    && apt-get clean

# Configura ed installa le estensioni PHP necessarie (GD e OpenSSL)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && docker-php-ext-install openssl

# Copia i file del tuo progetto nel container
COPY . /var/www/html/

# Imposta la directory di lavoro
WORKDIR /var/www/html

# Installa Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Esegui composer install per installare le dipendenze PHP
RUN composer install --no-interaction

# Esponi la porta 4242 per il traffico Stripe
EXPOSE 4242

# Avvia Apache in primo piano per mantenere il container attivo
CMD ["apache2-foreground"]




