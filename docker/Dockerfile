# Imagen de desarrollo: PHP 8.2 + Apache (idéntico a lo que corre en Hostinger)
FROM php:8.2-apache

# Extensión necesaria para hablar con MySQL/MariaDB
RUN docker-php-ext-install pdo_mysql

# Módulos de Apache usados por el .htaccess
RUN a2enmod rewrite headers expires deflate

# .htaccess debe ser respetado
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Evita el aviso "Could not reliably determine the server's fully qualified domain name"
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
