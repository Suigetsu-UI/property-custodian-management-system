#!/bin/sh

set -eu

role="${APP_ROLE:-web}"
port="${PORT:-8080}"

case "$port" in
    ''|*[!0-9]*)
        echo "PORT must be a positive integer." >&2
        exit 1
        ;;
esac

if [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
    echo "PORT must be between 1 and 65535." >&2
    exit 1
fi

case "$role" in
    web)
        exec php \
            -d session.save_path=/tmp/pcms-sessions \
            -S "0.0.0.0:${port}" \
            -t /srv \
            /srv/property-custodian-management-system/deploy/hostforge_web_router.php
        ;;
    procurement)
        exec php \
            -S "0.0.0.0:${port}" \
            services/procurement/router.php
        ;;
    property_core)
        exec php \
            -S "0.0.0.0:${port}" \
            services/property_core/router.php
        ;;
    *)
        echo "Unsupported APP_ROLE: ${role}" >&2
        exit 1
        ;;
esac
