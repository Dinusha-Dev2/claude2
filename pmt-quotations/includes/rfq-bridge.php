<?php
/**
 * Bridge to the site's Bulk Order / RFQ requests (post type pa_rfq).
 * Adds "Create quotation" to each request and fills the editor from it.
 * Does nothing if the RFQ feature is not present.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Fill a new quotation from an RFQ: customer, delivery, items with store images. */
function pq_prefill_from_rfq( $doc, $rfq_id ) {
	if ( 'pa_rfq' !== get_post_type( $rfq_id ) ) { return $doc; }
	$d = get_post_meta( $rfq_id, '_pa_rfq', true );
	if ( ! is_array( $d ) ) { return $doc; }
	$s = pq_settings();

	$doc['rfq_id']    = (int) $rfq_id;
	$doc['reference'] = isset( $d['ref'] ) ? pq_s_line( $d['ref'], 120 ) : '';

	$email = sanitize_email( isset( $d['email'] ) ? $d['email'] : '' );
	$saved = pq_find_customer_by_email( $email );
	if ( $saved ) {
		$doc['customer'] = pq_get_customer( $saved );
	} else {
		$c = pq_blank_customer();
		$c['name']    = pq_s_line( isset( $d['name'] ) ? $d['name'] : '', 120 );
		$c['company'] = pq_s_line( isset( $d['company'] ) ? $d['company'] : '', 160 );
		$c['email']   = $email;
		$c['phone']   = pq_s_line( isset( $d['phone'] ) ? $d['phone'] : '', 60 );
		$doc['customer'] = $c;
	}

	$locs = function_exists( 'pa_rfq_locations' ) ? pa_rfq_locations() : array();
	$loc  = isset( $d['location'] ) && isset( $locs[ $d['location'] ] ) ? $locs[ $d['location'] ] : ( isset( $d['location'] ) ? ucfirst( (string) $d['location'] ) : '' );
	if ( ! empty( $d['site'] ) ) { $loc .= ( '' !== $loc ? ' - ' : '' ) . $d['site']; }
	$doc['delivery'] = pq_s_text( $loc, 500 );

	$ships = function_exists( 'pa_rfq_shipping' ) ? pa_rfq_shipping() : array();
	$note  = array( 'Created from ' . $doc['reference'] );
	if ( ! empty( $d['date'] ) ) { $note[] = 'Customer needs it by: ' . $d['date']; }
	if ( ! empty( $d['shipping'] ) ) { $note[] = 'Shipping preference: ' . ( isset( $ships[ $d['shipping'] ] ) ? $ships[ $d['shipping'] ] : $d['shipping'] ); }
	if ( ! empty( $d['notes'] ) ) { $note[] = 'Customer notes: ' . $d['notes']; }
	$doc['internal'] = pq_s_text( implode( "\n", $note ), 3000 );

	$units  = function_exists( 'pa_rfq_units' ) ? pa_rfq_units() : array();
	$store  = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
	$items  = array();
	foreach ( ( isset( $d['items'] ) && is_array( $d['items'] ) ? $d['items'] : array() ) as $it ) {
		$row = pq_blank_item();
		$pid = ! empty( $it['pid'] ) ? absint( $it['pid'] ) : 0;
		$row['name'] = pq_s_line( isset( $it['desc'] ) ? $it['desc'] : '', 300 );
		if ( ! empty( $it['model'] ) ) { $row['desc'] = pq_s_text( 'Model / part no.: ' . $it['model'], 500 ); }
		$row['qty']  = isset( $it['qty'] ) && is_numeric( $it['qty'] ) ? (float) $it['qty'] : 1;
		$ukey        = isset( $it['unit'] ) ? $it['unit'] : '';
		$row['unit'] = isset( $units[ $ukey ] ) ? $units[ $ukey ] : $s['defaults']['unit'];
		if ( $pid && 'product' === get_post_type( $pid ) ) {
			$p = pq_product_row( $pid );
			$row['pid']    = $pid;
			$row['sku']    = $p['sku'];
			$row['img_id'] = $p['img_id'];
			if ( '' === $row['name'] ) { $row['name'] = $p['name']; }
			if ( '' === $row['desc'] && '' !== $p['desc'] ) { $row['desc'] = $p['desc']; }
			if ( '' !== $p['price'] && $store && strtoupper( $store ) === $doc['currency'] ) { $row['price'] = $p['price']; }
		}
		$items[] = $row;
	}
	$doc['items'] = $items;
	return $doc;
}

/* Row action on the RFQ list. */
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( 'pa_rfq' === $post->post_type && pq_can() ) {
		$actions['pq_make'] = '<a href="' . esc_url( pq_editor_url( 0, array( 'from_rfq' => $post->ID ) ) ) . '">Create quotation</a>';
	}
	return $actions;
}, 20, 2 );

/* Box on the RFQ detail screen. */
add_action( 'add_meta_boxes_pa_rfq', function () {
	if ( ! pq_can() ) { return; }
	add_meta_box( 'pq_rfq_quote', 'Quotation', function ( $post ) {
		$ids = get_posts( array(
			'post_type' => PQ_CPT, 'post_status' => 'publish', 'posts_per_page' => 10, 'fields' => 'ids',
			'meta_query' => array( array( 'key' => '_pq_rfq', 'value' => (int) $post->ID ) ),
		) );
		if ( $ids ) {
			echo '<p>Quotations made from this request:</p><ul>';
			foreach ( $ids as $did ) {
				echo '<li><a href="' . esc_url( pq_editor_url( $did ) ) . '">' . esc_html( get_post_meta( $did, '_pq_number', true ) ) . '</a></li>';
			}
			echo '</ul>';
		} else {
			echo '<p>No quotation yet.</p>';
		}
		echo '<p><a class="button button-primary" href="' . esc_url( pq_editor_url( 0, array( 'from_rfq' => $post->ID ) ) ) . '">Create quotation from this request</a></p>';
	}, 'pa_rfq', 'side', 'high' );
} );
