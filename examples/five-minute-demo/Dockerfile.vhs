FROM composer:2.10.2 AS composer

FROM ghcr.io/charmbracelet/vhs:v0.11.0

RUN apt-get update \
    && apt-get install --no-install-recommends -y \
        bat \
        ca-certificates \
        git \
        jq \
        php-cli \
        php-mbstring \
        php-xml \
        php-zip \
        unzip \
    && rm -rf /var/lib/apt/lists/* \
    && ln -sf /usr/bin/batcat /usr/local/bin/bat

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /vhs
