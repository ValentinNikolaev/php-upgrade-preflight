FROM php-upgrade-preflight-dev:8.3
USER root
RUN apt-get update && apt-get install --no-install-recommends -y time coreutils \
    && rm -rf /var/lib/apt/lists/*
USER app
WORKDIR /harness
ENTRYPOINT ["/bin/sh", "/harness/container.sh"]
