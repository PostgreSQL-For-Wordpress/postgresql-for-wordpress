FROM wordpress:php7.4-apache
LABEL maintainer="Nerd4ever Desenvolvimento Tecnlógico e Inovação Ltda <repo@nerd4ever.com.br>"
# persistent dependencies
RUN set -eux; \
# Required do install pg4wp \    
    apt-get update; \
    apt-get install -y --no-install-recommends  \
        unzip \
        libpq-dev \
	; \
    docker-php-ext-install pgsql pdo pdo_pgsql ;\
	rm -rf /var/lib/apt/lists/*
RUN set -ex; \
# added support to postgresql
    curl -o postgresql-for-wordpress.zip -fL https://github.com/kevinoid/postgresql-for-wordpress/archive/refs/heads/wordpress4-compat.zip; \
    \
    unzip postgresql-for-wordpress.zip -d /usr/src/; \
    \
	rm postgresql-for-wordpress.zip; \
    mv -f /usr/src/postgresql-for-wordpress-wordpress4-compat/pg4wp /usr/src/wordpress/wp-content; \
    cp /usr/src/wordpress/wp-content/pg4wp/db.php /usr/src/wordpress/wp-content; \
    \
	chown -R www-data:www-data  /usr/src/wordpress/wp-content; \
	chmod -R 777  /usr/src/wordpress/wp-content
