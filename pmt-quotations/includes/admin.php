<?php
/**
 * Admin: menus, list pages and assets.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** The document type a list page is showing (?pq_type=), or '' for all documents. */
function pq_list_type() {
	$t = isset( $_GET['pq_type'] ) ? sanitize_key( wp_unslash( $_GET['pq_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	return isset( pq_types()[ $t ] ) ? $t : '';
}

/** "Tax invoices", "Delivery notes"... */
function pq_type_plural( $type, $s = null ) {
	return pq_doc_label( $type, $s ) . 's';
}

/** Link to the list of one type, or of all documents. */
function pq_list_url( $type = '' ) {
	$url = 'edit.php?post_type=' . PQ_CPT;
	return $type ? $url . '&pq_type=' . $type : $url;
}

add_action( 'admin_menu', function () {
	$parent = pq_list_url();
	$cap    = pq_cap();
	// Each type has its own list, with its own numbers, totals and statuses.
	foreach ( array_keys( pq_types() ) as $t ) {
		add_submenu_page( $parent, pq_type_plural( $t ), pq_type_plural( $t ), $cap, pq_list_url( $t ) );
	}
	add_submenu_page( $parent, 'New document', 'New document', $cap, 'pq-editor', 'pq_render_editor_page' );
	add_submenu_page( $parent, 'Quotation settings', 'Settings', $cap, 'pq-settings', 'pq_render_settings_page' );
}, 20 );

/* Highlight the type's own menu item on its list page. */
add_filter( 'submenu_file', function ( $file ) {
	global $typenow, $pagenow;
	if ( 'edit.php' === $pagenow && PQ_CPT === $typenow && pq_list_type() ) { return pq_list_url( pq_list_type() ); }
	return $file;
} );

/* Menu order: All documents, one list per type, New document, Customers, Settings. */
add_action( 'admin_menu', function () {
	global $submenu;
	$parent = pq_list_url();
	if ( empty( $submenu[ $parent ] ) ) { return; }
	$order = array( $parent );
	foreach ( array_keys( pq_types() ) as $t ) { $order[] = pq_list_url( $t ); }
	$order = array_merge( $order, array( 'pq-editor', 'edit.php?post_type=' . PQ_CUSTOMER, 'pq-settings' ) );
	$items = array();
	foreach ( $submenu[ $parent ] as $item ) { $items[ $item[2] ] = $item; }
	$sorted = array();
	foreach ( $order as $slug ) {
		if ( isset( $items[ $slug ] ) ) { $sorted[] = $items[ $slug ]; unset( $items[ $slug ] ); }
	}
	foreach ( $items as $item ) { $sorted[] = $item; }
	$submenu[ $parent ] = $sorted; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
}, 999 );

/* Saving settings goes through options.php, which asks for manage_options unless told otherwise. */
add_filter( 'option_page_capability_pq_settings_group', 'pq_cap' );

add_action( 'admin_init', function () {
	register_setting( 'pq_settings_group', 'pq_settings', array(
		'type'              => 'array',
		'sanitize_callback' => 'pq_sanitize_settings',
		'default'           => array(),
	) );
} );

function pq_is_our_screen() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
	if ( in_array( $page, array( 'pq-editor', 'pq-settings' ), true ) ) { return $page; }
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && in_array( $screen->post_type, array( PQ_CPT, PQ_CUSTOMER ), true ) ) { return 'list'; }
	return '';
}

add_action( 'admin_enqueue_scripts', function () {
	$where = pq_is_our_screen();
	if ( ! $where ) { return; }
	wp_enqueue_style( 'pq-admin', PQ_URL . 'assets/admin.css', array(), PQ_VERSION );

	if ( 'pq-editor' === $where ) {
		wp_enqueue_media();
		wp_enqueue_script( 'pq-editor', PQ_URL . 'assets/editor.js', array( 'jquery', 'jquery-ui-sortable' ), PQ_VERSION, true );
		wp_localize_script( 'pq-editor', 'PQ', pq_editor_boot() );
	}
	if ( 'pq-settings' === $where ) {
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'pq-settings', PQ_URL . 'assets/settings.js', array( 'jquery', 'wp-color-picker' ), PQ_VERSION, true );
		wp_localize_script( 'pq-settings', 'PQS', array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'pq_admin' ),
		) );
	}
	if ( 'list' === $where ) {
		$lt    = pq_list_type();
		$label = $lt ? 'New ' . strtolower( pq_doc_label( $lt ) ) : 'New document';
		$url   = pq_editor_url( 0, $lt ? array( 'type' => $lt ) : array() );
		wp_add_inline_script( 'jquery-core', 'jQuery(function($){var t=$(".wrap .wp-heading-inline").first();if(t.length&&!$(".pq-newbtn").length){t.after(\' <a class="page-title-action pq-newbtn" href="' . esc_js( $url ) . '">' . esc_js( $label ) . '</a>\');}});' );
	}
} );

/* A type's list page is titled after it ("Tax invoices"); the All / Published counts would mix types, so they are hidden. */
add_action( 'load-edit.php', function () {
	global $typenow, $wp_post_types;
	if ( PQ_CPT !== $typenow || ! pq_list_type() || ! isset( $wp_post_types[ PQ_CPT ] ) ) { return; }
	$wp_post_types[ PQ_CPT ]->labels->name = pq_type_plural( pq_list_type() );
	add_filter( 'views_edit-' . PQ_CPT, '__return_empty_array' );
} );
