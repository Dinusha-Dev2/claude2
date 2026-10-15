<?php
/**
 * Pixel Ceylon theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PIXEL_CEYLON_VERSION', '1.0.0' );

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'pixel-ceylon-base',
		get_theme_file_uri( 'assets/css/base.css' ),
		array(),
		PIXEL_CEYLON_VERSION
	);

	wp_enqueue_script(
		'pixel-ceylon-scroll-top',
		get_theme_file_uri( 'assets/js/scroll-top.js' ),
		array(),
		PIXEL_CEYLON_VERSION,
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
}, 20 );

// Hook for the base layer in assets/css/base.css.
add_filter( 'body_class', function ( $classes ) {
	$classes[] = 'pct-body';
	return $classes;
} );

// The sections ship their own icons and fonts; the emoji polyfill only adds weight.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

// The Trip Planner used to live in a Code Snippet. Load the theme copy only while
// that snippet is inactive, so the two never declare the same functions.
add_action( 'after_setup_theme', function () {
	if ( ! function_exists( 'pctp_handle_enquiry' ) ) {
		require get_theme_file_path( 'inc/trip-planner.php' );
	}
} );

/**
 * Layout for the current page from inc/routes.php, or null for a plain page.
 */
function pixel_ceylon_layout() {
	static $routes = null;
	if ( null === $routes ) {
		$routes = require get_theme_file_path( 'inc/routes.php' );
	}

	if ( is_front_page() ) {
		$slug = 'home';
	} elseif ( is_page() ) {
		$slug = get_post_field( 'post_name', get_queried_object_id() );
	} else {
		return null;
	}

	if ( ! isset( $routes[ $slug ] ) ) {
		return null;
	}

	return wp_parse_args(
		$routes[ $slug ],
		array(
			'header'    => true,
			'footer'    => true,
			'entry'     => false,
			'sections'  => array(),
			'shortcode' => '',
		)
	);
}

/**
 * Print one section file from parts/sections/.
 *
 * Sections are trusted theme files of hand-written HTML/CSS/JS, so they are
 * printed as-is, the same way the page builder's HTML widget printed them.
 */
function pixel_ceylon_section( $name ) {
	$file = get_theme_file_path( 'parts/sections/' . sanitize_file_name( $name ) . '.html' );
	if ( ! is_readable( $file ) ) {
		return;
	}

	echo '<div class="pct-section pct-section--' . esc_attr( $name ) . '">';
	readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	echo "</div>\n";
}

/**
 * Print the head links of every section on the page, in page order.
 *
 * Some sections keep their font and icon stylesheet links in parts/head/ so
 * they load from <head>. Printing them last keeps the original stylesheet order
 * (for example, Font Awesome 6 after the footer's Font Awesome 4.7).
 */
add_action( 'wp_head', function () {
	$layout = pixel_ceylon_layout();
	if ( ! $layout ) {
		return;
	}

	$sections = $layout['sections'];
	if ( $layout['header'] ) {
		array_unshift( $sections, 'header' );
	}
	if ( $layout['footer'] ) {
		$sections[] = 'footer';
	}

	foreach ( $sections as $name ) {
		$file = get_theme_file_path( 'parts/head/' . sanitize_file_name( $name ) . '.html' );
		if ( is_readable( $file ) ) {
			readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}
}, PHP_INT_MAX );

/**
 * Classes for the page wrapper.
 */
function pixel_ceylon_site_class() {
	$layout = pixel_ceylon_layout();
	return ( ! $layout || $layout['entry'] ) ? 'pct-site pct-entry' : 'pct-site';
}

/**
 * Whether the shared header/footer should print on this request.
 */
function pixel_ceylon_shows( $part ) {
	$layout = pixel_ceylon_layout();
	return $layout ? (bool) $layout[ $part ] : true;
}
