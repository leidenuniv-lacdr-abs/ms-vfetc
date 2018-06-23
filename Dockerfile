FROM php:7-alpine

MAINTAINER Michael van Vliet (m.s.vanvliet@lacdr.leidenuniv.nl)

RUN apk update && \
    apk --no-cache add curl bash && \
    apk add --update --no-cache libintl icu icu-dev libxml2-dev && \
    docker-php-ext-install intl zip soap && \
    rm -rf /var/cache/apk/*

COPY . /vfetc/
WORKDIR /vfetc/
ENTRYPOINT ["php", "-f", "./src/vfetc.php"]