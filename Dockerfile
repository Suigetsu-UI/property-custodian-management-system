FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libpq-dev \
        libsodium-dev \
    && docker-php-ext-install -j"$(nproc)" curl pdo_pgsql sodium \
    && rm -rf /var/lib/apt/lists/* \
    && php -r "exit(extension_loaded('pdo_pgsql') && extension_loaded('sodium') && function_exists('curl_init') ? 0 : 1);"

RUN groupadd --gid 10001 pcms \
    && useradd --uid 10001 --gid pcms --create-home --shell /usr/sbin/nologin pcms \
    && mkdir -p /srv/property-custodian-management-system /tmp/pcms-sessions \
    && chown -R pcms:pcms /srv/property-custodian-management-system /tmp/pcms-sessions

WORKDIR /srv/property-custodian-management-system
COPY --chown=pcms:pcms . .

RUN chmod 0555 deploy/hostforge-entrypoint.sh

USER pcms

ENV APP_ROLE=web
ENV PORT=8080

EXPOSE 8080

ENTRYPOINT ["./deploy/hostforge-entrypoint.sh"]
