FROM php:8.2-fpm

WORKDIR /app

ARG INSTALL_ADMIN_TOOLS=false

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		libfreetype6-dev \
		libjpeg62-turbo-dev \
		libpng-dev \
		$(if [ "$INSTALL_ADMIN_TOOLS" = "true" ]; then echo git docker.io docker-compose; fi) \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	&& docker-php-ext-install -j"$(nproc)" gd \
	&& rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/conf.d/hotglue-dev.ini
