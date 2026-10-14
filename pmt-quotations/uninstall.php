<?php
/**
 * Runs when the plugin is deleted. Data is kept unless the owner opted in to delete it.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

$settings = get_option( 'pq_settings', array() );
if ( empty( $settings['general']['delete_on_uninstall'] ) ) { return; }

foreach ( array( 'pq_quote', 'pq_customer' ) as $type ) {
	$ids = get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) { wp_delete_post( $id, true ); }
}
delete_option( 'pq_settings' );
delete_option( 'pq_version' );
