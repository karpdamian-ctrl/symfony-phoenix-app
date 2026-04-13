FROM php:8.1-cli as base
FROM base as dev
ARG LOCAL_UID=1000
ARG LOCAL_GID=1000

RUN apt-get update && apt-get install -y git unzip libpq-dev libxml2-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql xml \
    && pecl install pcov \
    && docker-php-ext-enable pcov

RUN groupadd --gid "${LOCAL_GID}" appgroup \
    && useradd --uid "${LOCAL_UID}" --gid appgroup --create-home --shell /bin/bash appuser

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY ./entrypoint.sh /entrypoint.sh

WORKDIR /app

USER appuser

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]

FROM base as prod

RUN apt-get update && apt-get install -y git unzip libpq-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_pgsql xml

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install

EXPOSE 8000

ENTRYPOINT ["php"]
CMD ["-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
