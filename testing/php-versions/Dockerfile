# An image for testing the package on a specific version of PHP. Used by testing/run-all-php-versions.sh.
# Build with e.g.: docker build --build-arg PHP_VERSION=8.2 -t phpmailer-wrapper-php8.2-test testing/php-versions
# The official images already include the mbstring and openssl extensions, which are all the package and its tests
# need (openssl for the fake SMTP server's TLS).
ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli
