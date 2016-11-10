FROM php:7-alpine

MAINTAINER Michael van Vliet (m.s.vanvliet@lacdr.leidenuniv.nl)

COPY . /vfetc/
WORKDIR /vfetc/
ENTRYPOINT ["php", "-f", "./src/vfetc.php"]