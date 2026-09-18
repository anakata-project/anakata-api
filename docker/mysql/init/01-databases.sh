#!/bin/sh
set -e

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<EOSQL
CREATE DATABASE IF NOT EXISTS \`anakata\`;
CREATE DATABASE IF NOT EXISTS \`anakata_test\`;
CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'%' IDENTIFIED BY '${MYSQL_PASSWORD}';
GRANT ALL PRIVILEGES ON \`anakata\`.* TO '${MYSQL_USER}'@'%';
GRANT ALL PRIVILEGES ON \`anakata_test\`.* TO '${MYSQL_USER}'@'%';
SET GLOBAL log_bin_trust_function_creators = 1;
SET PERSIST log_bin_trust_function_creators = 1;
FLUSH PRIVILEGES;
EOSQL
