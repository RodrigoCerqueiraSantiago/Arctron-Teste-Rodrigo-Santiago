<?php get_header(); ?>
<main id="conteudo" class="store-content inner-content">
    <?php if (is_front_page()) : ?>
        <div class="home-intro"><span class="section-eyebrow">BEM-VINDO À LOJA DEMONSTRATIVA</span><h1>O esporte nos move<span>.</span></h1><p>Conheça nossa coleção inspirada na energia do Brasil.</p><a class="hero-button" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Explorar coleção <span aria-hidden="true">↗</span></a></div>
    <?php else : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article><h1><?php the_title(); ?></h1><?php the_content(); ?></article>
        <?php endwhile; ?>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
