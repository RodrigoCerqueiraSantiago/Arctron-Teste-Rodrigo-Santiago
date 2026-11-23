#!/bin/sh
set -eu

: "${WP_ADMIN_USER:?missing WP_ADMIN_USER}"
product_id="$(wp post list --post_type=product --post_status=publish --meta_key=_sku --meta_value=arctron-demo-CAMISA --format=ids)"
if [ -z "$product_id" ]; then
    echo "Produto de teste ausente. Execute make seed-woocommerce primeiro." >&2
    exit 1
fi

order_id="$(wp wc shop_order create --user="$WP_ADMIN_USER" --status=processing \
    --billing='{"first_name":"Cliente","last_name":"Teste","email":"cliente@example.test"}' \
    --line_items="[{\"product_id\":$product_id,\"quantity\":1}]" \
    --meta_data='[{"key":"_billing_cpf","value":"12345678901"}]' \
    --customer_note='Pedido de teste da integração' --porcelain)"
echo "Pedido WooCommerce criado: $order_id"
echo "Aguarde a entrega automática e confira a referência woocommerce:$order_id em /api/purchases."
