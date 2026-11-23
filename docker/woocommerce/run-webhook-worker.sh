#!/bin/sh
set -u

while :; do
    if wp core is-installed --quiet && wp plugin is-active woocommerce --quiet; then
        if ! wp action-scheduler run --hooks=woocommerce_deliver_webhook_async --batch-size=100 --batches=5 --quiet; then
            echo "Falha ao processar webhooks do WooCommerce; nova tentativa em 10 segundos." >&2
        fi
    fi
    sleep 10
done
