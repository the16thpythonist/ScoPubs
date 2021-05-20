FROM wordpress:latest

MAINTAINER Jonas Teufel <jonseb1998@gmail.com>

# We'll need python for a convenience script which blocks the foreground execution until we can be sure that the
# database is loaded. This is a docker compose quirk since it starts the database at the same time with the web
# container.
# SVN is apparently needed for the script which initializes the wordpress unittest environment
RUN apt-get update && \
    apt-get install -y git && \
    apt-get install -y subversion && \
    apt-get install -y curl && \
    apt-get install -y mariadb-client && \
    apt-get install -y python3 python3-pip
RUN python3 -m pip install pymysql click

# === BASIC SETUP ===
ENV PLUGIN_NAME="scopubs"
ENV WP_PATH="/usr/src/wordpress"
COPY docker/custom-entrypoint.sh /usr/src/wordpress/custom-entrypoint.sh
COPY docker/wait_for_mysql.py "$WP_PATH/wait_for_mysql.py"
# For some reason the default wordpress docker container does not have a valid wp-config.php just the one with the
# additional docker in its name. But that one is not properly recognized by wordpress
RUN cp "$WP_PATH/wp-config-docker.php" "$WP_PATH/wp-config.php"

# In the docker compose file we supply the database container's hostname and port seperately. Wordpress expects to find
# a env varialbe with this name which combines the two values with a colon. So we combine them here.

# === INSTALL ADDITIONAL DEPENDENCIES ===
ENV HOME_PATH="/home/wordpress"
RUN mkdir $HOME_PATH

# -- INSTALLING COMPOSER --
# Composer we need to install the PHP package dependencies for our project.
# https://getcomposer.org/download/
ENV COMPOSER_PATH="$HOME_PATH/composer.phar"
ENV COMPOSER_COMMAND="php $COMPOSER_PATH"
RUN cd $HOME_FOLDER && \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === '756890a4488ce9024fc62c56153228907f1545c228516cbf63f885e036d37e9a59d27d63f46af1d4d07ee0f76181c7d3') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php --install-dir=$HOME_PATH && \
    php -r "unlink('composer-setup.php');" && \
    $COMPOSER_COMMAND --version

# -- INSTALLING WPCLI --
# wp cli we need to manange wordpress from a command line script. Mainly the initial installation after having built
# the container
# https://make.wordpress.org/cli/handbook/guides/installing/
ENV WPCLI_PATH="$HOME_PATH/wp-cli.phar"
ENV WPCLI_COMMAND="php $WPCLI_PATH --allow-root --path=$WP_PATH"
RUN cd $HOME_PATH && \
    curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    $WPCLI_COMMAND --version && \
    $WPCLI_COMMAND --info

# -- INSTALLING PHPUNIT --
# Phpunit will be used for performing the unittests of wordpress.
# https://github.com/sebastianbergmann/phpunit#installation
ENV PHPUNIT_PATH="$HOME_PATH/phpunit.phar"
ENV PHPUNIT_COMMAND="php $PHPUNIT_PATH"
RUN cd $HOME_PATH && \
    curl "https://phar.phpunit.de/phpunit-7.5.9.phar" > $PHPUNIT_PATH && \
    $PHPUNIT_COMMAND --version

ENTRYPOINT ["/usr/src/wordpress/custom-entrypoint.sh"]

CMD ["apache2-foreground"]