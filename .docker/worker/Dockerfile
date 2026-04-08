FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    supervisor \
    libzip-dev \
    sqlite-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_sqlite zip bcmath gd

RUN apk add --no-cache --virtual .build-deps autoconf gcc g++ make \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY supervisord.conf /etc/supervisord.conf
COPY entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

WORKDIR /var/www

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
