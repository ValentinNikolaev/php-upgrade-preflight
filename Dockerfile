ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-cli

# Keep this layer free of build arguments. A changed ARG value invalidates the build
# cache for every instruction below its declaration, even instructions that never
# reference it, so declaring USER_ID/GROUP_ID here would force a full apt-get and zip
# extension rebuild for every developer whose host UID is not 1000.
#
# libzip-dev is deliberately left installed: purging it measured at 78 KB on an
# image of 575 MB, which does not justify the removal step or its risk of taking the
# runtime library the compiled extension links against with it.
RUN apt-get update \
    && apt-get install --no-install-recommends -y \
        git \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" zip \
    && php -r "foreach (['dom', 'mbstring', 'xml', 'zip'] as \$extension) { if (!extension_loaded(\$extension)) { fwrite(STDERR, \"Missing PHP extension: {\$extension}\\n\"); exit(1); } }" \
    && git config --system --add safe.directory /app \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN groupadd --gid "${GROUP_ID}" app \
    && useradd --uid "${USER_ID}" --gid app --create-home --shell /bin/bash app \
    && mkdir -p /home/app/.composer/cache \
    && chown -R app:app /home/app/.composer

ENV COMPOSER_HOME=/home/app/.composer
ENV COMPOSER_CACHE_DIR=/home/app/.composer/cache

USER app
WORKDIR /app

CMD ["php", "-v"]
