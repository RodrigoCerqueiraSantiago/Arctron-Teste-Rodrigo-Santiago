<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<div class="announcement">
    <span>LOJA DEMONSTRATIVA</span>
    <span>Paixão pelo esporte, dentro e fora de campo.</span>
    <span>SEM PAGAMENTO REAL</span>
</div>
<header class="store-header">
    <div class="store-nav">
        <a class="brand" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" aria-label="Arctron Sport - início">
            <span class="brand-mark">A<span>.</span></span>
            <span class="brand-name">ARCTRON <strong>SPORT</strong><small>LOJA DEMONSTRATIVA</small></span>
        </a>
        <nav class="nav-links" aria-label="Navegação principal">
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Coleção</a>
            <a href="<?php echo esc_url(wc_get_cart_url()); ?>">Carrinho</a>
            <a href="<?php echo esc_url(wc_get_checkout_url()); ?>">Finalizar pedido</a>
        </nav>
        <a class="cart-link" href="<?php echo esc_url(wc_get_cart_url()); ?>" aria-label="Ver carrinho">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M5 8h14l1 12H4L5 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg>
            <span class="cart-label">Meu carrinho</span>
            <?php echo arctron_cart_summary(); ?>
        </a>
    </div>
</header>
