FROM php:8.1-fpm-alpine


RUN apk update && \
    set -eux; \
	apk add --no-cache --virtual .build-deps \
		$PHPIZE_DEPS \
		icu-dev \
		libzip-dev \
        git \
		zlib-dev \
        openssl-dev\
	; \
	\
	docker-php-ext-configure zip; \
	docker-php-ext-install -j$(nproc) \
		intl \
		zip \
	; \
	runDeps="$( \
		scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
			| tr ',' '\n' \
			| sort -u \
			| awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
	)"; \
	apk add --no-cache --virtual .phpexts-rundeps $runDeps; \
	\
	apk del .build-deps

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

ENV PATH="${PATH}:/root/.composer/vendor/bin"

ENV PATH /var/app/bin:/var/app/vendor/bin:$PATH

WORKDIR /var/app