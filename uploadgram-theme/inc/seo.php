<?php
/**
 * Lightweight professional SEO layer:
 *  - <title> via title-tag, meta description, canonical
 *  - Open Graph + Twitter cards
 *  - JSON-LD: Organization + WebSite (front), Product (products),
 *    BreadcrumbList, FAQPage (FAQ page)
 *
 * Complements (does not replace) a dedicated SEO plugin — if Yoast/RankMath
 * is active, we defer to it to avoid duplicate tags.
 *
 * @package UploadGram
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Is a full SEO plugin handling meta already? */
function ug_seo_has_plugin(): bool {
    return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'SEOPRESS_VERSION' );
}

/** Site defaults (override via options or the ug_seo_* filters). */
function ug_seo_site_name(): string {
    return apply_filters( 'ug_seo_site_name', get_bloginfo( 'name' ) ?: 'آپلودگرام' );
}
function ug_seo_default_desc(): string {
    $d = get_option( 'ug_seo_description', '' );
    if ( ! $d ) {
        $d = 'آپلودگرام؛ فروشگاه خدمات دیجیتال — خرید ممبر تلگرام و اینستاگرام، اکانت پرمیوم اصل (ChatGPT، اسپاتیفای، نتفلیکس و…) و شماره مجازی از ۳۰+ کشور با تحویل آنی، قیمت رقابتی و پشتیبانی ۲۴ ساعته.';
    }
    return apply_filters( 'ug_seo_default_desc', $d );
}
function ug_seo_social_image(): string {
    $img = get_option( 'ug_seo_social_image', '' );
    if ( ! $img && function_exists( 'ug_asset' ) ) {
        $img = ug_asset( 'brand/logo.png' );
    }
    return apply_filters( 'ug_seo_social_image', $img );
}

/** Compute the description for the current view. */
function ug_seo_description(): string {
    if ( is_singular() ) {
        $post = get_queried_object();
        $custom = get_post_meta( $post->ID, '_ug_meta_desc', true );
        if ( $custom ) {
            return $custom;
        }
        if ( function_exists( 'is_product' ) && is_product() ) {
            $p = wc_get_product( $post->ID );
            if ( $p ) {
                $s = $p->get_short_description() ?: $p->get_description();
                if ( $s ) {
                    return wp_trim_words( wp_strip_all_tags( $s ), 32, '…' );
                }
            }
        }
        if ( has_excerpt( $post ) ) {
            return wp_strip_all_tags( get_the_excerpt( $post ) );
        }
        if ( ! empty( $post->post_content ) ) {
            return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 32, '…' );
        }
    }
    if ( is_tax() || is_category() || is_tag() ) {
        $t = get_queried_object();
        if ( $t && ! empty( $t->description ) ) {
            return wp_strip_all_tags( $t->description );
        }
    }
    return ug_seo_default_desc();
}

/** The canonical URL for the current view. */
function ug_seo_canonical(): string {
    if ( is_front_page() ) {
        return home_url( '/' );
    }
    if ( is_singular() ) {
        return get_permalink();
    }
    if ( function_exists( 'wc_get_page_permalink' ) && is_shop() ) {
        return wc_get_page_permalink( 'shop' );
    }
    if ( is_tax() || is_category() || is_tag() ) {
        $l = get_term_link( get_queried_object() );
        if ( ! is_wp_error( $l ) ) {
            return $l;
        }
    }
    global $wp;
    return home_url( add_query_arg( [], $wp->request ) );
}

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
} );

/**
 * Output meta + Open Graph + Twitter in the head.
 */
add_action( 'wp_head', function () {
    if ( ug_seo_has_plugin() ) {
        return;
    }
    $desc  = esc_attr( ug_seo_description() );
    $canon = esc_url( ug_seo_canonical() );
    $title = wp_get_document_title();
    $img   = ug_seo_social_image();
    $type  = ( function_exists( 'is_product' ) && is_product() ) ? 'product' : ( is_singular( 'post' ) ? 'article' : 'website' );

    echo "\n<!-- UploadGram SEO -->\n";
    echo '<meta name="description" content="' . $desc . '">' . "\n";
    echo '<link rel="canonical" href="' . $canon . '">' . "\n";
    echo '<meta name="robots" content="index, follow, max-image-preview:large">' . "\n";

    // Open Graph
    echo '<meta property="og:site_name" content="' . esc_attr( ug_seo_site_name() ) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
    echo '<meta property="og:description" content="' . $desc . '">' . "\n";
    echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
    echo '<meta property="og:url" content="' . $canon . '">' . "\n";
    echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">' . "\n";
    if ( $img ) {
        echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
    }

    // Twitter
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . $desc . '">' . "\n";
    if ( $img ) {
        echo '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n";
    }
}, 5 );

/**
 * JSON-LD structured data.
 */
add_action( 'wp_head', function () {
    if ( ug_seo_has_plugin() ) {
        return;
    }
    $blocks = [];

    // Organization + WebSite on the front page.
    if ( is_front_page() ) {
        $org = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => ug_seo_site_name(),
            'url'      => home_url( '/' ),
        ];
        $logo = function_exists( 'ug_asset' ) ? ug_asset( 'brand/logo-small.png' ) : '';
        if ( $logo ) {
            $org['logo'] = $logo;
        }
        $socials = array_filter( (array) get_option( 'ug_seo_socials', [] ) );
        if ( $socials ) {
            $org['sameAs'] = array_values( $socials );
        }
        $blocks[] = $org;

        $blocks[] = [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            'name'            => ug_seo_site_name(),
            'url'             => home_url( '/' ),
            'inLanguage'      => 'fa-IR',
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => home_url( '/?s={search_term_string}' ),
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    // Product schema on product pages.
    if ( function_exists( 'is_product' ) && is_product() ) {
        $p = wc_get_product( get_the_ID() );
        if ( $p ) {
            $img = get_the_post_thumbnail_url( $p->get_id(), 'large' );
            if ( ! $img && class_exists( 'UG_WC_Integration' ) ) {
                $img = UG_WC_Integration::icon_url( $p->get_id() );
            }
            $blocks[] = [
                '@context'    => 'https://schema.org',
                '@type'       => 'Product',
                'name'        => $p->get_name(),
                'image'       => $img ? [ $img ] : [],
                'description' => wp_strip_all_tags( $p->get_short_description() ?: $p->get_description() ),
                'sku'         => (string) $p->get_id(),
                'brand'       => [ '@type' => 'Brand', 'name' => ug_seo_site_name() ],
                'offers'      => [
                    '@type'         => 'Offer',
                    'price'         => (string) $p->get_price(),
                    'priceCurrency' => 'IRR',
                    'availability'  => 'https://schema.org/InStock',
                    'url'           => get_permalink( $p->get_id() ),
                ],
            ];
        }
    }

    // Breadcrumbs on inner pages.
    if ( is_singular() && ! is_front_page() ) {
        $items = [ [ '@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => home_url( '/' ) ] ];
        $pos   = 2;
        if ( function_exists( 'is_product' ) && is_product() ) {
            $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => 'فروشگاه', 'item' => wc_get_page_permalink( 'shop' ) ];
        }
        $items[] = [ '@type' => 'ListItem', 'position' => $pos, 'name' => get_the_title(), 'item' => get_permalink() ];
        $blocks[] = [ '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items ];
    }

    // FAQPage schema on the FAQ page.
    if ( is_page( 'faq' ) && function_exists( 'ug_faq_schema_items' ) ) {
        $qa = ug_faq_schema_items();
        if ( $qa ) {
            $main = [];
            foreach ( $qa as $item ) {
                $main[] = [
                    '@type'          => 'Question',
                    'name'           => $item[0],
                    'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $item[1] ],
                ];
            }
            $blocks[] = [ '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $main ];
        }
    }

    foreach ( $blocks as $b ) {
        echo '<script type="application/ld+json">' . wp_json_encode( $b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
    }
}, 6 );
