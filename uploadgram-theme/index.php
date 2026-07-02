<?php
/**
 * Fallback index template — blog / archive listing.
 */
get_header();
?>

<div class="container">
  <div class="section" style="margin-top:24px;">
    <?php if ( have_posts() ) : ?>
      <?php the_archive_title( '<h1 class="section-heading" style="font-size:22px;margin-bottom:20px;">', '</h1>' ); ?>
      <div class="service-cats-grid">
        <?php while ( have_posts() ) : the_post(); ?>
          <article class="scat">
            <?php if ( has_post_thumbnail() ) : ?>
              <div class="scat-icon" style="width:100%;height:160px;border-radius:12px;margin-bottom:14px;">
                <?php the_post_thumbnail( 'medium', [ 'style' => 'width:100%;height:100%;object-fit:cover;' ] ); ?>
              </div>
            <?php endif; ?>
            <h2 class="scat-name"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
            <div class="scat-desc"><?php the_excerpt(); ?></div>
            <a class="scat-link" href="<?php the_permalink(); ?>">ادامه مطلب ←</a>
          </article>
        <?php endwhile; ?>
      </div>
      <div style="margin-top:28px;text-align:center;">
        <?php the_posts_pagination( [ 'mid_size' => 2, 'prev_text' => '→ قبلی', 'next_text' => 'بعدی ←' ] ); ?>
      </div>
    <?php else : ?>
      <p class="text-muted">محتوایی یافت نشد.</p>
    <?php endif; ?>
  </div>
</div>

<?php get_footer(); ?>
