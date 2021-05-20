PLUGIN_FOLDER="/var/www/html/wp-content/plugins/scopubs"

# Database credentials
DB_USER="root"
DB_PASSWORD="$WORDPRESS_DB_PASSWORD"
DB_HOST="db"
DB_PORT="3306"
DB_NAME="test"

# SETTING UP TEST ENVIRONMENT
# Apparently we need to manually drop the test database first. Actually I think this should be handled by the
# "install-wp-tests.sh" but it just didnt work. Either way since this uses the --force and --silent flags it wont
# cause any problems even if the database does not exist in the first place. So it is just an additional precaution
mysqladmin drop "$DB_NAME" \
           --force --silent \
           --user="$DB_USER" \
           --password="$DB_PASSWORD" \
           --host="$DB_HOST" \
           --port="$DB_PORT"

# This command sets up the whole wordpress environment for the testing.
bash "$PLUGIN_FOLDER/bin/install-wp-tests.sh" "$DB_NAME" "$DB_USER" "$DB_PASSWORD" "$DB_HOST:$DB_PORT" latest


# RUNNING THE UNIT TESTS
# We actually need to use the vendor version of phpunit here.
bash -c "cd $PLUGIN_FOLDER ; vendor/bin/phpunit --configuration=phpunit.xml tests"