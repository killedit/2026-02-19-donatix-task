FROM php:8.3-fpm

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN usermod -u ${USER_ID} www-data && groupmod -g ${GROUP_ID} www-data

RUN apt-get update \
    && apt-get install -y \
       git curl libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev zip unzip libzip-dev \
       exim4-daemon-light \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo_mysql mbstring exif pcntl bcmath zip

RUN mkdir -p /var/www/html && chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY ./composer.json composer.json
COPY ./composer.lock composer.lock

COPY . /var/www/html/

RUN composer install --no-scripts --optimize-autoloader --prefer-dist

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN chown -R www-data:www-data /var/www/html

RUN echo "alias ll='ls -l'" >> /root/.bashrc

EXPOSE 9000
CMD ["php-fpm"]
