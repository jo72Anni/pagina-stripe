FROM php:8.2-apache

# Installa Composer e dipendenze sistema
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl

# Installa Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copia i file
COPY . /var/www/html/

# Installa le dipendenze (questo crea vendor/)
RUN composer install --no-dev --optimize-autoloader

# 👇 CAMBIA LA PORTA DA 80 A 4242
RUN sed -i 's/Listen 80/Listen 4242/g' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:4242>/g' /etc/apache2/sites-available/000-default.conf

# Imposta permessi
RUN chown -R www-data:www-data /var/www/html

# 👇 ESPONI LA PORTA 4242
EXPOSE 4242

CMD ["apache2-foreground"]