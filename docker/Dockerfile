FROM php:8.3-cli-bookworm AS base

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libonig-dev \
        libpq-dev \
        libzip-dev \
        libsqlite3-dev \
        lsof \
        postgresql-client \
        pkg-config \
        procps \
        sqlite3 \
        unzip \
    && docker-php-ext-install \
        mbstring \
        pdo_pgsql \
        pdo_sqlite \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/geo.ini
COPY docker/entrypoint.sh /usr/local/bin/geo-entrypoint
COPY docker/scheduler.sh /usr/local/bin/geo-scheduler

RUN chmod +x /usr/local/bin/geo-entrypoint /usr/local/bin/geo-scheduler

COPY admin /var/www/html/admin
COPY assets /var/www/html/assets
COPY bin /var/www/html/bin
COPY includes /var/www/html/includes
COPY archive.php /var/www/html/archive.php
COPY article.php /var/www/html/article.php
COPY category.php /var/www/html/category.php
COPY detail.php /var/www/html/detail.php
COPY index.php /var/www/html/index.php
COPY llms.txt /var/www/html/llms.txt
COPY llms-full.txt /var/www/html/llms-full.txt
COPY router.php /var/www/html/router.php

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/geo-entrypoint"]

FROM base AS web

ENTRYPOINT ["/usr/local/bin/geo-entrypoint"]
CMD ["php", "-S", "0.0.0.0:8080", "router.php"]

FROM base AS scheduler

ENTRYPOINT ["/usr/local/bin/geo-entrypoint"]
CMD ["/usr/local/bin/geo-scheduler"]
