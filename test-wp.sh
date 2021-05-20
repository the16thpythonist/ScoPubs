#!/bin/bash

echo "=== RUNNING UNIT TESTS FOR SCOPUBS ==="
sudo docker-compose -f docker/local.yml run --rm scopubs-wordpress-web bash -c \
     "/var/www/html/wp-content/plugins/scopubs/bin/run-tests.sh"

