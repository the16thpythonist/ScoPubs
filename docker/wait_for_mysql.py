#!/usr/bin/env python3
"""
This is a convenience script. It's entire purpose is to halt the foreground execution until a database connection could
successfully be established from the credentials provided with the env variables of the container.
"""
import os
import sys
import time
import pymysql
import pymysql.err
import pymysql.cursors

TIMEOUT = 60
start_time = time.time()

config = {
    "host": os.getenv("WORDPRESS_DB_HOSTNAME", ""),
    "port": os.getenv("WORDPRESS_DB_PORT", ""),
    "user": os.getenv("WORDPRESS_DB_USER", ""),
    "password": os.getenv("WORDPRESS_DB_PASSWORD", ""),
    "dbname": os.getenv("WORDPRESS_DB_NAME", "")
}


def db_ready(host, port, user, password, dbname):
    while time.time() - start_time < TIMEOUT:
        try:
            connection = pymysql.connect(host=host,
                                         user=user,
                                         password=password,
                                         database=dbname,
                                         port=int(port))
            with connection:
                print("DB is ready!")
                sys.exit(0)
        except pymysql.err.OperationalError as e:
            print("waiting for DB...")
        except Exception as e:
            print(type(e))
            print(str(e))
        finally:
            time.sleep(1)


db_ready(**config)
sys.exit(0)