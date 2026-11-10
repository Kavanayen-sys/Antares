FROM php:8.2-apache

# Instalar extensión mysqli para conectar con MySQL
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Habilitar mod_rewrite de Apache (para las APIs)
RUN a2enmod rewrite

# Habilitar los puertos adicionales en Apache
RUN sed -i 's|^Listen 80$|Listen 80\nListen 8090\nListen 8091\nListen 8092\nListen 8093\nListen 8094|' /etc/apache2/ports.conf

# Copiar configuración de VirtualHosts
COPY apache-sigeru.conf /etc/apache2/sites-available/sigeru.conf
RUN a2ensite sigeru.conf

# Configurar AllowOverride para el directorio web
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Establecer el directorio raíz del servidor web
WORKDIR /var/www/html
