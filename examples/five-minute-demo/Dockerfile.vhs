FROM composer:2.10.2 AS composer

FROM ghcr.io/charmbracelet/vhs:v0.11.0

RUN apt-get update \
    && apt-get install --no-install-recommends -y \
        ca-certificates \
        git \
        php-cli \
        php-mbstring \
        php-xml \
        php-zip \
        unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /vhs
