# E2E test image for the Ignite Medical LIS.
# Base already ships: mysqli, gd, exif, intl, zip, opcache, imagick, bcmath, mbstring.
# We add only what the ci-* plugins reach for that the base lacks.
# D156: pinned by digest. Without it `docker compose build` re-resolves the tag
# on every run, so the build is not reproducible and a silently-changed base
# image reads as a product failure. Digest read from the Docker Hub registry
# manifest for php8.2-apache on 2026-08-24.
FROM wordpress:php8.2-apache@sha256:0f6ba629d199076405582623ec9bda1cd9462acddcbfe25f8e1f62a7021dd846

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libgmp-dev \
        libxslt1-dev \
        libssh2-1-dev \
        default-mysql-client \
        less \
    ; \
    docker-php-ext-install -j"$(nproc)" gmp soap xsl pcntl shmop sysvmsg sysvsem sysvshm; \
    pecl install ssh2-1.4 redis; \
    docker-php-ext-enable ssh2 redis; \
    rm -rf /var/lib/apt/lists/*

# wp-cli, pinned + checksum-verified.
# NB: the URL the reference stack used
# (raw.githubusercontent.com/wp-cli/wp-cli/v2.8.1/wp-cli.phar) now returns 404 —
# measured 2026-08-24. The release asset below is the supported download.
ARG WP_CLI_VERSION=2.8.1
ARG WP_CLI_SHA512=c1d40ee90b330ca1f8ddbed14b938b41ec5d9ff723c7c1cf3f41a2d9a1b271079a51a37ea3d1c9aa9c628fdd43449dba3995a8de150a68abbd505b06b91d9d2b
RUN set -eux; \
    curl -fsSLo /usr/local/bin/wp \
      "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar"; \
    echo "${WP_CLI_SHA512}  /usr/local/bin/wp" | sha512sum -c -; \
    chmod +x /usr/local/bin/wp; \
    wp --allow-root --version

RUN a2enmod rewrite

WORKDIR /var/www/html
