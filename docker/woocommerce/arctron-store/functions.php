<?php

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
});

add_action('wp_enqueue_scripts', function () {
    $stylesheet = get_template_directory() . '/assets/store.css';
    wp_enqueue_style('arctron-store', get_template_directory_uri() . '/assets/store.css', [], filemtime($stylesheet));
    if (function_exists('is_cart') && (is_cart() || is_checkout())) {
        $script = get_template_directory() . '/assets/cart.js';
        wp_enqueue_script('arctron-cart', get_template_directory_uri() . '/assets/cart.js', ['wp-data', 'wc-blocks-data-store'], filemtime($script), true);
    }
    wp_enqueue_script('wp-i18n');
    wp_add_inline_script('wp-i18n', 'wp.hooks.addFilter("i18n.gettext", "arctron/store-translation", (translation, text, domain) => {
        const translations = {
            "Your cart is currently empty!": "Seu carrinho está vazio!",
            "New in store": "Novidades na loja",
            "+ Add %s": "+ Adicionar %s"
        };
        return domain && domain.startsWith("woocommerce") ? (translations[text] || translation) : translation;
    });', 'after');
});

add_filter('gettext', function ($translation, $text) {
    $translations = [
        'Your cart is currently empty!' => 'Seu carrinho está vazio!',
        'New in store' => 'Novidades na loja',
        '+ Add %s' => '+ Adicionar %s',
    ];

    return $translations[$text] ?? $translation;
}, 20, 2);

add_filter('render_block', function ($content) {
    return str_replace(
        ['Your cart is currently empty!', 'New in store'],
        ['Seu carrinho está vazio!', 'Novidades na loja'],
        $content
    );
}, 20);

add_filter('woocommerce_show_page_title', function ($show) {
    return is_shop() ? false : $show;
});

add_filter('woocommerce_product_get_image', function ($image, $product, $size, $attr, $placeholder) {
    if ($product->get_image_id() || ! $placeholder) {
        return $image;
    }

    $artwork = [
        'arctron-demo-CAMISA' => 'camisa.svg',
        'arctron-demo-BOLA' => 'bola.svg',
        'arctron-demo-BONE' => 'bone.svg',
        'arctron-demo-MOCHILA' => 'mochila.svg',
        'arctron-demo-GARRAFA' => 'garrafa.svg',
    ][$product->get_sku()] ?? null;

    if (! $artwork) {
        return $image;
    }

    return sprintf(
        '<img src="%s" alt="%s" class="arctron-product-image" loading="lazy" width="480" height="480">',
        esc_url(get_template_directory_uri() . '/assets/' . $artwork),
        esc_attr($product->get_name())
    );
}, 10, 5);

function arctron_cart_summary() {
    $cart = function_exists('WC') ? WC()->cart : null;
    $count = $cart ? $cart->get_cart_contents_count() : 0;
    $total = $cart ? $cart->get_total() : wc_price(0);

    return '<span class="cart-summary"><span class="cart-count">' . esc_html($count) . '</span><span class="cart-total">' . wp_kses_post($total) . '</span></span>';
}

add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    $fragments['span.cart-summary'] = arctron_cart_summary();
    return $fragments;
});

add_filter('document_title_parts', function ($title) {
    if (function_exists('is_shop') && is_shop()) {
        $title['title'] = 'Loja';
    } elseif (function_exists('is_cart') && is_cart()) {
        $title['title'] = 'Carrinho';
    } elseif (function_exists('is_checkout') && is_checkout()) {
        $title['title'] = is_order_received_page() ? 'Pedido recebido' : 'Finalizar pedido';
    }

    return $title;
});
