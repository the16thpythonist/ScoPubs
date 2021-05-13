===============================================
ScoPubs - Scientific Publications for Wordpress
===============================================

ScoPubs is a Wordpress plugin. It is created to integrate scientific publications into Wordpress installations with
ease. By relying on the Scopus scientific database, publications of various selected authors can be automatically
imported to the wordpress site as a custom post type.

Development
===========

Wordpress Docker
----------------

To develop this wordpress plugin, one obviously needs a wordpress installation. Actually installing wordpress on the
local system might be suboptimal, because it might collide with already existing wordpress installations or other
web servers. Thus, this project uses docker-compose to provide a development container, which hosts a blank wordpress
installation, which only has this very plugin installed.

It will be required to install `docker-compose <>`_.
To build the necessary containers run the following command from the root folder of the repo:

.. code-block:: console

    sudo docker-compose -f docker/local.yml build

The container can then be started using the "up" command:

.. code-block:: console

    sudo docker-compose -f docker/local.yml up

The web server will be attempted to be bound to the HTTP port 80 of the local machine. If this port should already be
taken otherwise, it can be changed in the ``docker/local.yml`` config file.

Vue Dev Server
--------------

For the frontend, this plugin utilizes the VueJS framework. For the production version of this frontend the source
files will usually have to be compiled first. During development it can be tiresome to recompile the code for every
small change. This is why this project supports hot reloading of frontend code by using the Vue frontend server.

The wordpress docker container is automatically configured to attempt and use the development server for the frontend
JS files instead of the compiled production version. But this development server has to be started first. This can be
done by using the "serve" command:

.. code-block:: console

    cd js
    npm run serve

This will start the development server and enable the JS frontend to be properly loaded by the wordpress container.

To build a production version of the frontend code, use the following command:

.. code-block:: console

    cd js
    npm run build:production

Testing
-------

There are unit tests which were created with `PHPUnit <https://phpunit.de/getting-started/phpunit-9.html>`_ these are
located in the ``/tests`` folder. To run all the unit tests use the phpunit executable which was installed by composer
into the ``/vendor`` folder:

.. code-block:: console

    ./vendor/bin/phpunit ./tests/*


Credits
=======

This RST file was created with lots of help from
`RST Cheatsheet <https://github.com/ralsina/rst-cheatsheet/blob/master/rst-cheatsheet.rst>`_
