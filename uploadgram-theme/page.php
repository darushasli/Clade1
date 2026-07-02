<?php
/**
 * Default page template.
 */
get_header();
?>

<div class="container">
  <div class="section" style="margin-top:24px;">
    <?php while ( have_posts() ) : the_post(); ?>
      <article <?php post_class( 'ug-page-content' ); ?>>
        <h1 class="section-heading" style="font-size:26px;margin-bottom:20px;"><?php the_title(); ?></h1>
        <div class="entry-content" style="color:var(--text-2);line-height:1.9;">
          <?php the_content(); ?>
        </div>
      </article>
    <?php endwhile; ?>
  </div>
</div>

<?php get_footer(); ?>
