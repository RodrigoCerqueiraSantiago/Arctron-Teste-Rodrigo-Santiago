<?php get_header(); ?>
<main id="conteudo" class="store-content inner-content store-page">
    <?php while (have_posts()) : the_post(); ?>
        <h1 class="page-heading"><?php echo esc_html(is_cart() ? 'Carrinho' : (is_checkout() ? 'Finalizar pedido' : get_the_title())); ?></h1>
        <?php the_content(); ?>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
