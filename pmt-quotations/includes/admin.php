<?php
/**
 * Admin: menus, assets and the overview strip on the documents list.
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
		'deliver'  => array( 'label' => 'Out for delivery', 'n' => 0, 'sums' => array(), 'count_only' => true ),
	);
	foreach ( (array) $rows as $r ) {
		$key = '';
		$val = (float) $r->total;
		if ( 'quotation' === $r->dtype && 'sent' === $r->dstatus ) { $key = 'open'; }
		elseif ( 'quotation' === $r->dtype && 'accepted' === $r->dstatus ) { $key = 'accepted'; }
		elseif ( pq_type_has_payments( $r->dtype ) && in_array( $r->dstatus, array( 'sent', 'partial' ), true ) ) { $key = 'unpaid'; $val = (float) $r->balance; }
		elseif ( 'invoice' === $r->dtype && 'paid' === $r->dstatus ) { $key = 'paid'; }
		elseif ( 'delivery' === $r->dtype && 'sent' === $r->dstatus ) { $key = 'deliver'; }
		if ( '' === $key ) { continue; }
		$cards[ $key ]['n'] += (int) $r->n;
		$cards[ $key ]['sums'][ $r->cur ] = ( isset( $cards[ $key ]['sums'][ $r->cur ] ) ? $cards[ $key ]['sums'][ $r->cur ] : 0 ) + $val;
	}
	return $cards;
}

/** Cards for one type's list: count and value per status. */
function pq_type_stats( $type ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT s.meta_value AS dstatus, c.meta_value AS cur, COUNT(*) AS n,
		        SUM(CAST(tot.meta_value AS DECIMAL(20,4))) AS total, SUM(CAST(b.meta_value AS DECIMAL(20,4))) AS balance
		 FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} t   ON t.post_id = p.ID   AND t.meta_key = '_pq_type' AND t.meta_value = %s
		 INNER JOIN {$wpdb->postmeta} s   ON s.post_id = p.ID   AND s.meta_key = '_pq_status'
		 INNER JOIN {$wpdb->postmeta} c   ON c.post_id = p.ID   AND c.meta_key = '_pq_currency'
		 INNER JOIN {$wpdb->postmeta} tot ON tot.post_id = p.ID AND tot.meta_key = '_pq_total'
		 INNER JOIN {$wpdb->postmeta} b   ON b.post_id = p.ID   AND b.meta_key = '_pq_balance'
		 WHERE p.post_type = %s AND p.post_status = 'publish'
		 GROUP BY s.meta_value, c.meta_value",
		$type, PQ_CPT
	) );
	$cards = array();
	foreach ( pq_statuses( $type ) as $k => $label ) {
		$cards[ $k ] = array( 'label' => $label, 'n' => 0, 'sums' => array(), 'count_only' => 'delivery' === $type );
	}
	foreach ( (array) $rows as $r ) {
		if ( ! isset( $cards[ $r->dstatus ] ) ) { continue; }
		// What is still owed matters for unpaid invoices; the full value for everything else.
		$val = ( pq_type_has_payments( $type ) && in_array( $r->dstatus, array( 'sent', 'partial' ), true ) ) ? (float) $r->balance : (float) $r->total;
		$cards[ $r->dstatus ]['n'] += (int) $r->n;
		$cards[ $r->dstatus ]['sums'][ $r->cur ] = ( isset( $cards[ $r->dstatus ]['sums'][ $r->cur ] ) ? $cards[ $r->dstatus ]['sums'][ $r->cur ] : 0 ) + $val;
	}
	if ( pq_type_has_payments( $type ) ) {
		foreach ( array( 'sent', 'partial' ) as $k ) { $cards[ $k ]['label'] .= ' (due)'; }
	}
	return $cards;
}

add_action( 'admin_notices', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit-' . PQ_CPT !== $screen->id || ! pq_can() ) { return; }
	$lt = pq_list_type();
	echo '<div class="pq-app pq-stats' . ( $lt ? ' pq-stats-type' : '' ) . '">';
	foreach ( $lt ? pq_type_stats( $lt ) : pq_stats() as $card ) {
		echo '<div class="pq-stat"><span class="pq-stat-label">' . esc_html( $card['label'] ) . '</span>';
		if ( ! empty( $card['count_only'] ) ) {
			echo '<strong' . ( $card['n'] ? '' : ' class="pq-zero"' ) . '>' . (int) $card['n'] . '</strong>';
		} elseif ( $card['sums'] ) {
			foreach ( $card['sums'] as $cur => $v ) { echo '<strong>' . esc_html( pq_money( $v, $cur ) ) . '</strong>'; }
		} else {
			echo '<strong class="pq-zero">0</strong>';
		}
		if ( empty( $card['count_only'] ) ) { echo '<span class="pq-stat-n">' . (int) $card['n'] . ' ' . ( 1 === (int) $card['n'] ? 'document' : 'documents' ) . '</span>'; }
		elseif ( ! $lt ) { echo '<span class="pq-stat-n">delivery notes dispatched</span>'; }
		echo '</div>';
	}
	echo '</div>';
} );

/* A type's list page is titled after it ("Tax invoices") and shows only its own counts. */
add_action( 'load-edit.php', function () {
	global $typenow, $wp_post_types;
	if ( PQ_CPT !== $typenow || ! pq_list_type() || ! isset( $wp_post_types[ PQ_CPT ] ) ) { return; }
	$wp_post_types[ PQ_CPT ]->labels->name = pq_type_plural( pq_list_type() );
	add_filter( 'views_edit-' . PQ_CPT, '__return_empty_array' );
} );
