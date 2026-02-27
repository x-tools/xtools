# Use `docker compose up -d` and then `docker exec -it xtools-php-1 /bin/bash` to get a shell inside the container. Inside that shell, you can type things like `composer update` and `symfony [your command here]`.

# To view the website, don't use `symfony serve`. Visit https://localhost:8085/public instead.

# If you make any changes to this file, don't forget to rebuild the Docker image using the --build flag: docker-compose up -d --build

# Your .env.local file needs to use host.docker.internal instead of 127.0.0.1. It should look something like...
# DATABASE_REPLICA_HOST_S1=host.docker.internal
# DATABASE_REPLICA_PORT_S1=4711
# DATABASE_REPLICA_HOST_S2=host.docker.internal
# DATABASE_REPLICA_PORT_S2=4712
# DATABASE_REPLICA_HOST_S3=host.docker.internal
# DATABASE_REPLICA_PORT_S3=4713
# DATABASE_REPLICA_HOST_S4=host.docker.internal
# DATABASE_REPLICA_PORT_S4=4714
# DATABASE_REPLICA_HOST_S5=host.docker.internal
# DATABASE_REPLICA_PORT_S5=4715
# DATABASE_REPLICA_HOST_S6=host.docker.internal
# DATABASE_REPLICA_PORT_S6=4716
# DATABASE_REPLICA_HOST_S7=host.docker.internal
# DATABASE_REPLICA_PORT_S7=4717
# DATABASE_REPLICA_HOST_S8=host.docker.internal
# DATABASE_REPLICA_PORT_S8=4718
# DATABASE_TOOLSDB_HOST=host.docker.internal
# DATABASE_TOOLSDB_PORT=4720

FROM php:8.2-apache

# Install PHP XDebug, for step debugging and for PHPUnit code coverage report.
# You can leave port 9007 for all your Docker containers. It doesn't conflict across containers like the localhost port does.
# Add this .vscode/launch.json file to your repo, then go to Run and Debug -> press play:
# {
# 	"version": "0.2.0",
# 	"configurations": [
# 		{
# 			"name": "Listen for Xdebug",
# 			"type": "php",
# 			"request": "launch",
# 			"port": 9007,
# 			"pathMappings": {
#				"/var/www/html/": "${workspaceRoot}"
# 			}
# 		}
# 	]
# }
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug
RUN echo "xdebug.mode=debug,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9007" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.idekey=VSCODE" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install composer
RUN apt-get update && apt-get install --no-install-recommends -y git zip unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

# Install Symfony CLI so that the `symfony` command is available inside the container
RUN apt-get update \
    && apt-get install --no-install-recommends -y ca-certificates curl \
    && curl -sS https://get.symfony.com/cli/installer | bash \
        && if [ -f /root/.symfony5/bin/symfony ]; then \
                 mv /root/.symfony5/bin/symfony /usr/local/bin/symfony; \
             elif [ -f /root/.symfony/bin/symfony ]; then \
                 mv /root/.symfony/bin/symfony /usr/local/bin/symfony; \
             else \
                 echo "Symfony binary not found after installer" && exit 1; \
             fi \
    && chmod +x /usr/local/bin/symfony \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /root/.symfony

# Install PHP intl extension
RUN apt-get update \
    && apt-get install --no-install-recommends -y libicu-dev g++ \
    && docker-php-ext-install -j"$(nproc)" intl \
    && apt-get purge -y --auto-remove g++ \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP PDO extension (MySQL flavor)
RUN apt-get update \
    && apt-get install --no-install-recommends -y default-libmysqlclient-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli \
    && apt-get purge -y --auto-remove default-libmysqlclient-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Allow directory listings. Not a security issue since this is a test environment. Makes it easier to navigate.
RUN a2enmod autoindex rewrite
RUN echo "<Directory /var/www/html>\n    Options +Indexes\n    AllowOverride All\n</Directory>" > /etc/apache2/conf-available/directory-listing.conf \
    && a2enconf directory-listing
