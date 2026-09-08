#!/usr/bin/env bash
# Ensures the `dailzo_test` database exists and the app DB user can use it.
# Needed because docker/mysql/init.sql only runs on a fresh MySQL volume, so an
# already-created container won't have the test database.
#
# Usage: server/bin/setup-test-db.sh
set -euo pipefail

CONTAINER="${DAILZO_MYSQL_CONTAINER:-dailzo-mysql}"
ROOT_PW="${DAILZO_MYSQL_ROOT_PASSWORD:-dailzo}"
APP_USER="${DB_USERNAME:-dailzo}"
TEST_DB="${DB_TEST_DATABASE:-dailzo_test}"

if docker ps --format '{{.Names}}' | grep -qx "$CONTAINER"; then
    echo "==> Creating ${TEST_DB} in container ${CONTAINER}"
    docker exec -i "$CONTAINER" mysql -uroot -p"$ROOT_PW" -e \
        "CREATE DATABASE IF NOT EXISTS \`${TEST_DB}\`;
         GRANT ALL PRIVILEGES ON \`${TEST_DB}\`.* TO '${APP_USER}'@'%';
         FLUSH PRIVILEGES;"
    echo "==> Done"
else
    echo "Container '${CONTAINER}' is not running." >&2
    echo "Start it with: docker compose up -d mysql" >&2
    echo "Or create '${TEST_DB}' manually on your MySQL server and grant '${APP_USER}' access." >&2
    exit 1
fi
