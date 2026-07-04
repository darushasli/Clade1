<?php
/**
 * Template Name: المنتور — تمام‌عرض
 *
 * Full-width canvas WITH the site header + footer. Calls the_content()
 * with no container constraint, so Elementor sections span edge-to-edge.
 * Use this to design a page with Elementor while keeping the site chrome.
 */
get_header();

while ( have_posts() ) :
    the_post();
    the_content();
endwhile;

get_footer();
