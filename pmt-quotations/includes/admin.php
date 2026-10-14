<?php
/**
 * Admin: menus, assets and the overview strip on the documents list.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', function () {
	$parent = 'edit.php?post_type=' . PQ_CPT;
	$cap    = pq_cap();
	add_submenu_page( $parent, 'New quotation', 'New quotation', $cap, 'pq-editor', 'pq_render_editor_page' );
	add_submenu_page( $parent, 'Quotation settings', 'Settings', $cap, 'pq-settings', 'pq_render_settings_page' );
}, 20 );

/* Menu order: All documents, New quotation, Customers, Settings. */
add_action( 'admin_menu', function () {
	global $submenu;
	$parent = 'edit.php?post_type=' . PQ_CPT;
	if ( empty( $submenu[ $parent ] ) ) { return; }
	$order = array( $parent, 'pq-editor', 'edit.php?post_type=' . PQ_CUSTOMER, 'pq-settings' );
	$items = array();
	foreach ( $submenu[ $parent ] as $item ) { $items[ $item[2] ] = $item; }
	$sorted = array();
	foreach ( $order as $slug ) {
		if ( isset( $items[ $slug ] ) ) { $sorted[] = $items[ $slug ]; unset( $items[ $slug ] ); }
	}
	foreach ( $items as $item ) { $sorted[] = $item; }
	$submenu[ $parent ] = $sorted; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
}, 999 );

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
		wp_add_inline_script( 'jquery-core', 'jQuery(function($){var t=$(".wrap .wp-heading-inline").first();if(t.length&&!$(".pq-newbtn").length){t.after(\' <a class="page-title-action pq-newbtn" href="' . esc_js( pq_editor_url() ) . '">New quotation</a>\');}});' );
	}
} );

/** Aggregate figures by currency, never mixing currencies. */
function pq_stats() {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT t.meta_value AS dtype, s.meta_value AS dstatus, c.meta_value AS cur, COUNT(*) AS n,
		        SUM(CAST(tot.meta_value AS DECIMAL(20,4))) AS total, SUM(CAST(b.meta_value AS DECIMAL(20,4))) AS balance
		 FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} t   ON t.post_id = p.ID   AND t.meta_key = '_pq_type'
		 INNER JOIN {$wpdb->postmeta} s   ON s.post_id = p.ID   AND s.meta_key = '_pq_status'
		 INNER JOIN {$wpdb->postmeta} c   ON c.post_id = p.ID   AND c.meta_key = '_pq_currency'
		 INNER JOIN {$wpdb->postmeta} tot ON tot.post_id = p.ID AND tot.meta_key = '_pq_total'
		 INNER JOIN {$wpdb->postmeta} b   ON b.post_id = p.ID   AND b.meta_key = '_pq_balance'
		 WHERE p.post_type = %s AND p.post_status = 'publish'
		 GROUP BY t.meta_value, s.meta_value, c.meta_value",
		PQ_CPT
	) );
	$cards = array(
		'open'     => array( 'label' => 'Open quotations', 'n' => 0, 'sums' => array() ),
		'accepted' => array( 'label' => 'Accepted quotations', 'n' => 0, 'sums' => array() ),
		'unpaid'   => array( 'label' => 'Awaiting payment', 'n' => 0, 'sums' => array() ),
		'paid'     => array( 'label' => 'Paid invoices', 'n' => 0, 'sums' => array() ),
	);
	foreach ( (array) $rows as $r ) {
		$key = '';
		$val = (float) $r->total;
		if ( 'quotation' === $r->dtype && 'sent' === $r->dstatus ) { $key = 'open'; }
		elseif ( 'quotation' === $r->dtype && 'accepted' === $r->dstatus ) { $key = 'accepted'; }
		elseif ( 'quotation' !== $r->dtype && in_array( $r->dstatus, array( 'sent', 'partial' ), true ) ) { $key = 'unpaid'; $val = (float) $r->balance; }
		elseif ( 'invoice' === $r->dtype && 'paid' === $r->dstatus ) { $key = 'paid'; }
		if ( '' === $key ) { continue; }
		$cards[ $key ]['n'] += (int) $r->n;
		$cards[ $key ]['sums'][ $r->cur ] = ( isset( $cards[ $key ]['sums'][ $r->cur ] ) ? $cards[ $key ]['sums'][ $r->cur ] : 0 ) + $val;
	}
	return $cards;
}

add_action( 'admin_notices', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit-' . PQ_CPT !== $screen->id || ! pq_can() ) { return; }
	echo '<div class="pq-app pq-stats">';
	foreach ( pq_stats() as $card ) {
		echo '<div class="pq-stat"><span class="pq-stat-label">' . esc_html( $card['label'] ) . '</span>';
		if ( $card['sums'] ) {
			foreach ( $card['sums'] as $cur => $v ) { echo '<strong>' . esc_html( pq_money( $v, $cur ) ) . '</strong>'; }
		} else {
			echo '<strong class="pq-zero">0</strong>';
		}
		echo '<span class="pq-stat-n">' . (int) $card['n'] . ' ' . ( 1 === (int) $card['n'] ? 'document' : 'documents' ) . '</span></div>';
	}
	echo '</div>';
} );
