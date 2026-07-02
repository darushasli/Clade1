<?php
/**
 * Single post template.
 */
get_header();
?>

<div class="container">
  <div class="section" style="margin-top:24px;max-width:820px;">
    <?php while ( have_posts() ) : the_post(); ?>
      <article <?php post_class(); ?>>
        <?php if ( has_post_thumbnail() ) : ?>
          <div style="border-radius:var(--radius);overflow:hidden;margin-bottom:24px;">
            <?php the_post_thumbnail( 'large', [ 'style' => 'width:100%;height:auto;' ] ); ?>
          </div>
        <?php endif; ?>
        <h1 class="section-heading" style="font-size:28px;margin-bottom:14px;"><?php the_title(); ?></h1>
        <div class="text-muted" style="font-size:13px;margin-bottom:24px;">
          <?php echo esc_html( get_the_date() ); ?> · <?php the_author(); ?>
        </div>
        <div class="entry-content" style="color:var(--text-2);line-height:1.9;">
          <?php the_content(); ?>
        </div>
      </article>
      <?php
      if ( comments_open() || get_comments_number() ) {
          comments_template();
      }
      ?>
    <?php endwhile; ?>
  </div>
</div>

<?php get_footer(); ?>
