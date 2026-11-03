#!/bin/sh
set -e

# Default connection to QUEUE_CONNECTION env or 'database'
CONNECTION="${QUEUE_CONNECTION:-database}"

# Default queue names to QUEUE_NAME env or 'default'
QUEUE="${QUEUE_NAME:-default}"

# Worker timeout, retries, and sleep intervals
TIMEOUT="${QUEUE_TIMEOUT:-90}"
TRIES="${QUEUE_TRIES:-3}"
SLEEP="${QUEUE_SLEEP:-3}"
MAX_TIME="${QUEUE_MAX_TIME:-3600}"
MAX_JOBS="${QUEUE_MAX_JOBS:-1000}"

echo "Starting queue worker for connection '${CONNECTION}' on queue '${QUEUE}' (timeout: ${TIMEOUT}s, tries: ${TRIES})..."

exec php /var/www/html/artisan queue:work "${CONNECTION}" \
    --queue="${QUEUE}" \
    --sleep="${SLEEP}" \
    --tries="${TRIES}" \
    --timeout="${TIMEOUT}" \
    --max-time="${MAX_TIME}" \
    --max-jobs="${MAX_JOBS}"

