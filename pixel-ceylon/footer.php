<?php
/**
 * The shared site footer and document close.
 */

if ( pixel_ceylon_shows( 'footer' ) ) {
	pixel_ceylon_section( 'footer' );
}
?>
</div>
<div id="pct-scroll-top" class="pct-scroll-top" tabindex="0" role="button">
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="57 35.171 26 16.043" width="26" height="16" aria-hidden="true"><path d="M57.5,38.193l12.5,12.5l12.5-12.5l-2.5-2.5l-10,10l-10-10L57.5,38.193z"/></svg>
	<span class="screen-reader-text"><?php esc_html_e( 'Scroll to Top', 'pixel-ceylon' ); ?></span>
</div>
<?php wp_footer(); ?>
</body>
</html>
