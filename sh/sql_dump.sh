#!/bin/bash

# 0 22 * * * bash /home/jared/dev/public/webdb/sh/sql_dump.sh

DB_USERNAME=$(head -n 1 ../../../pwd/sql_admin)
DB_PASSWORD=$(tail -1 ../../../pwd/sql_admin)
BACKUP_PATH=../../../sql_backup/
MAX_KEEP_DAYS=7
DATESTAMP=$(date +%Y%m%d)

find ${BACKUP_PATH} -type f -mtime +${MAX_KEEP_DAYS} -exec -rf {} \;

BACKUP_FILENAME=${BACKUP_PATH}sql_backup_${DATESTAMP}.sql.gz
CHECK_FILENAME=${BACKUP_PATH}sql_check_${DATESTAMP}.log

mysqldump -u $DB_USERNAME -p"$DB_PASSWORD" --opt --all-databases --skip-lock-tables --flush-logs | gzip > $BACKUP_FILENAME
mysqlcheck -u $DB_USERNAME -p"$DB_PASSWORD" --all-databases > $CHECK_FILENAME

# gunzip sql_backup_${DATESTAMP}.sql.gz
# mysql -u $DB_USERNAME -p < sql_backup_${DATESTAMP}.sql

exit 0
