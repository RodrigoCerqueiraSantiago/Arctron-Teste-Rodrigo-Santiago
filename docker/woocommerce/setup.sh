#!/bin/sh
set -eu

: "${WP_ADMIN_USER:?missing WP_ADMIN_USER}"
: "${WP_ADMIN_PASSWORD:?missing WP_ADMIN_PASSWORD}"
: "${WP_ADMIN_EMAIL:?missing WP_ADMIN_EMAIL}"
: "${WP_URL:?missing WP_URL}"
: "${WOOCOMMERCE_WEBHOOK_SECRET:?missing WOOCOMMERCE_WEBHOOK_SECRET}"

attempt=0
until wp db check --quiet; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 60 ]; then
        echo "WordPress database did not become ready" >&2
        exit 1
    fi
    sleep 2
done

if ! wp core is-installed; then
    wp core install --url="$WP_URL" --title='Arctron - loja de teste' \
        --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASSWORD" \
        --admin_email="$WP_ADMIN_EMAIL" --skip-email
fi

if ! wp plugin is-installed woocommerce; then
    wp plugin install woocommerce --version=9.9.5 --activate
else
    wp plugin activate woocommerce
fi

if ! wp language core is-installed pt_BR; then
    wp language core install pt_BR
fi
if ! wp language plugin is-installed woocommerce pt_BR; then
    wp language plugin install woocommerce pt_BR
fi
wp site switch-language pt_BR

wp theme activate arctron-store
wp option update woocommerce_currency BRL
wp option update woocommerce_currency_pos left_space
wp option update woocommerce_price_decimal_sep ','
wp option update woocommerce_price_thousand_sep '.'
wp option update woocommerce_price_num_decimals 2
wp option update woocommerce_default_country BR:SP
wp option update woocommerce_cod_settings '{"enabled":"yes","title":"Pagamento de teste na entrega","description":"Simulação: nenhum pagamento real será cobrado.","instructions":"Pedido de teste. Nenhum pagamento será cobrado.","enable_for_methods":[]}' --format=json
wp option update woocommerce_onboarding_profile '{"completed":true,"skipped":true}' --format=json
wp eval '
$hidden = get_option("woocommerce_task_list_hidden_lists", array());
if (!is_array($hidden)) {
    WP_CLI::error("Invalid hidden task lists option.");
}
if (!in_array("setup", $hidden, true)) {
    $hidden[] = "setup";
    if (!update_option("woocommerce_task_list_hidden_lists", $hidden)) {
        WP_CLI::error("Could not hide the setup task list.");
    }
}
'
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

for item in 'CAMISA:Camisa de teste:99.95' 'BOLA:Bola de treino:80.00' 'BONE:Boné esportivo:45.00' 'MOCHILA:Mochila:120.00' 'GARRAFA:Garrafa esportiva:35.00'; do
    sku="arctron-demo-${item%%:*}"
    rest="${item#*:}"
    name="${rest%:*}"
    price="${rest##*:}"
    existing="$(wp post list --post_type=product --post_status=any --meta_key=_sku --meta_value="$sku" --format=ids)"
    if [ -z "$existing" ]; then
        wp wc product create --user="$WP_ADMIN_USER" --name="$name" --type=simple \
            --status=publish --sku="$sku" --regular_price="$price" --porcelain
    fi
done

ensure_webhook() {
    topic="$1"
    option="$2"
    webhook_id="$(wp option get "$option" 2>/dev/null || true)"
    if [ -n "$webhook_id" ] && wp wc webhook get "$webhook_id" --user="$WP_ADMIN_USER" --format=json >/dev/null 2>&1; then
        wp wc webhook update "$webhook_id" --user="$WP_ADMIN_USER" \
            --name="Arctron $topic" --status=active --topic="$topic" \
            --secret="$WOOCOMMERCE_WEBHOOK_SECRET"
        delivery_url="$(wp wc webhook get "$webhook_id" --user="$WP_ADMIN_USER" --field=delivery_url)"
        if [ "$delivery_url" != 'http://nginx/api/integrations/woocommerce/webhook' ]; then
            WEBHOOK_ID="$webhook_id" wp eval '$webhook = new WC_Webhook((int) getenv("WEBHOOK_ID")); $webhook->set_delivery_url("http://nginx/api/integrations/woocommerce/webhook"); if (!$webhook->save()) { WP_CLI::error("Could not update webhook delivery URL"); }'
        fi
    else
        webhook_id="$(wp wc webhook create --user="$WP_ADMIN_USER" \
            --name="Arctron $topic" --status=active --topic="$topic" \
            --delivery_url='http://nginx/api/integrations/woocommerce/webhook' \
            --secret="$WOOCOMMERCE_WEBHOOK_SECRET" --porcelain)"
        wp option update "$option" "$webhook_id"
    fi
}

ensure_webhook order.created arctron_created_webhook_id
ensure_webhook order.updated arctron_updated_webhook_id
echo "WooCommerce configurado."
