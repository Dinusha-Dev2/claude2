<?php
/**
 * Pages: the sections listed in inc/routes.php, or the page's own content.
 */

get_header();

$layout = pixel_ceylon_layout();

if ( $layout && $layout['shortcode'] ) {
	echo '<div class="pct-section">' . do_shortcode( $layout['shortcode'] ) . '</div>';
} elseif ( $layout ) {
	foreach ( $layout['sections'] as $section ) {
		pixel_ceylon_section( $section );
	}
} else {
	get_template_part( 'parts/content' );
}

get_footer();
