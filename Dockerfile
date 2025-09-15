# Usa immagine base PHP con Apache
FROM php:8.2-apache

# Abilita PDO PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql

# Abilita mod_rewrite (utile se in futuro aggiungi routing tipo Laravel)
RUN a2enmod rewrite

# Copia tutto il progetto nella root di Apache
COPY . /var/www/html/

# Imposta la directory di lavoro
WORKDIR /var/www/html/

# Espone la porta 80
EXPOSE 80
