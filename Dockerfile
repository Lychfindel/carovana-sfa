FROM php:8.3-apache

# GD (con JPEG e WebP) per elaborare le foto, EXIF per raddrizzarle
RUN apt-get update && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd exif \
    && apt-get purge -y --auto-remove libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && apt-get install -y --no-install-recommends libjpeg62-turbo libpng16-16 libwebp7 \
    && rm -rf /var/lib/apt/lists/*

RUN { echo 'upload_max_filesize = 10M'; echo 'post_max_size = 64M'; echo 'max_file_uploads = 20'; \
      echo 'memory_limit = 256M'; echo 'date.timezone = Europe/Rome'; echo 'expose_php = Off'; \
      echo 'display_errors = Off'; echo 'log_errors = On'; echo 'error_log = /dev/stderr'; } \
      > /usr/local/etc/php/conf.d/carovana.ini \
    && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Apache gira con un utente che ha lo stesso UID/GID del proprietario di ./data sul server,
# così può scrivere CSV e foto nel volume
ARG UID=1000
ARG GID=1000
RUN groupadd -o -g "$GID" carovana && useradd -o -u "$UID" -g "$GID" -M -s /usr/sbin/nologin carovana
ENV TZ=Europe/Rome \
    APACHE_RUN_USER=carovana \
    APACHE_RUN_GROUP=carovana
COPY --chown=root:root . /var/www/html/
EXPOSE 80
