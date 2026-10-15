<?php
/**
 * Not found.
 */

get_header();
?>
<main class="pct-content pct-404">
	<h1><?php esc_html_e( 'Page not found', 'pixel-ceylon' ); ?></h1>
	<p><?php esc_html_e( 'The page you are looking for has moved or no longer exists.', 'pixel-ceylon' ); ?></p>
	<p><a class="pct-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'pixel-ceylon' ); ?></a></p>
</main>
<?php
get_footer();
