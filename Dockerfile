ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-cli-bookworm

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN apt-get update \
    && apt-get install --no-install-recommends -y \
        git \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" dom mbstring xml zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN groupadd --gid "${GROUP_ID}" app \
    && useradd --uid "${USER_ID}" --gid app --create-home --shell /bin/bash app \
    && mkdir -p /home/app/.composer/cache \
    && chown -R app:app /home/app/.composer

ENV COMPOSER_HOME=/home/app/.composer
ENV COMPOSER_CACHE_DIR=/home/app/.composer/cache

USER app
WORKDIR /app

CMD ["php", "-v"]
