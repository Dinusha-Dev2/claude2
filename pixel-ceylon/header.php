<?php
/**
 * Document head and the shared site header.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
wp_body_open();
?>
<div class="<?php echo esc_attr( pixel_ceylon_site_class() ); ?>">
<?php
if ( pixel_ceylon_shows( 'header' ) ) {
	pixel_ceylon_section( 'header' );
}
