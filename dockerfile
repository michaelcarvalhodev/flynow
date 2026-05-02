FROM php:8.1-apache

# Instalar extensão PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Copiar arquivos do projeto
COPY . /var/www/html/

# Configurar DirectoryIndex para priorizar PHP
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    DirectoryIndex index.php index.html\n\
</Directory>' > /etc/apache2/conf-available/custom.conf \
    && a2enconf custom

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html

# Expor porta 80
EXPOSE 80

# Iniciar Apache
CMD ["apache2-foreground"]