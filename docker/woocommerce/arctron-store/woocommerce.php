<?php get_header(); ?>
<main id="conteudo">
    <?php if (is_shop()) : ?>
        <section class="shop-hero">
            <div class="hero-copy">
                <span class="hero-eyebrow"><span></span> COLEÇÃO DEMONSTRATIVA  /  01</span>
                <h1>O esporte<br>nos <em>move.</em></h1>
                <p>Para os treinos, para a torcida, para todos os momentos. Vista essa energia e entre em campo com a gente.</p>
                <a class="hero-button" href="#colecao">Explorar coleção <span aria-hidden="true">↗</span></a>
                <div class="hero-footnote"><span>01 — 05</span><span>INSPIRADO NA ENERGIA DO BRASIL</span></div>
            </div>
            <div class="hero-art" aria-hidden="true">
                <div class="hero-orbit orbit-one"></div><div class="hero-orbit orbit-two"></div>
                <span class="hero-art-label">FEITO PARA<br>O JOGO.</span>
                <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/hero-shirt.svg'); ?>" alt="">
                <span class="hero-stamp">VIVA<br><b>O JOGO</b><i>✦</i></span>
                <span class="hero-side-text">ENERGIA QUE VEM DE DENTRO</span>
            </div>
        </section>
        <div class="benefit-strip">
            <span><b>✦</b> ESPÍRITO DE EQUIPE</span><span><b>✦</b> COLEÇÃO ESPORTIVA</span><span><b>✦</b> COMPRA DE DEMONSTRAÇÃO</span>
        </div>
    <?php endif; ?>
    <div class="store-content <?php echo is_shop() ? 'catalog-content' : 'inner-content'; ?>" id="colecao">
        <?php if (is_shop()) : ?>
            <div class="section-intro"><div><span class="section-eyebrow">ESCOLHIDOS PARA VOCÊ</span><h2>Vista a sua paixão<span>.</span></h2><p>Peças e acessórios para acompanhar o seu ritmo.</p></div><div class="collection-tag">A COLEÇÃO <b>↗</b></div></div>
        <?php endif; ?>
        <?php woocommerce_content(); ?>
    </div>
</main>
<?php get_footer(); ?>
