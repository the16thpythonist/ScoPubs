#!/bin/bash
# This script will completely nuke all the containers involved with this project and set up everything again. This
# involves the data volumes the container itself etc.

# First we should check in which directory we am. Because executing these from within the docker folder will not
# work?

sudo docker-compose -f docker/local.yml down --volumes --remove-orphans --rmi=local
sudo docker-compose -f docker/local.yml build --force-rm --no-cache
sudo docker-compose -f docker/local.yml up --force-recreate --build --renew-anon-volumes --remove-orphans