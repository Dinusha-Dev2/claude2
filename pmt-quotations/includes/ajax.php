<?php
/**
 * AJAX endpoints used by the editor and settings pages.
 * Every endpoint checks a nonce and the quotation capability.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pq_ajax_guard() {
	check_ajax_referer( 'pq_admin', 'nonce' );
	if ( ! pq_can() ) {
		wp_send_json_error( array( 'message' => 'You do not have permission to do this.' ), 403 );
	}
}

function pq_post_json( $key ) {
	$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security
	$arr = json_decode( is_string( $raw ) ? $raw : '', true );
	return is_array( $arr ) ? $arr : array();
}

/* ---------------- Save ---------------- */

add_action( 'wp_ajax_pq_save', function () {
	pq_ajax_guard();
	$raw = pq_post_json( 'doc' );
	$id  = isset( $raw['id'] ) ? absint( $raw['id'] ) : 0;
	$res = pq_save_document( $raw, $id );
	if ( is_wp_error( $res ) ) {
		wp_send_json_error( array( 'message' => $res->get_error_message() ) );
	}
	$doc = pq_doc_prepare( $res['doc'] );
	list( $key, $label ) = pq_display_status( $doc );
	wp_send_json_success( array(
		'id'     => $res['id'],
		'doc'    => pq_doc_for_editor( $res['doc'] ),
		'links'  => pq_doc_links( $res['id'], $res['doc'] ),
		'status' => array( $key, $label ),
		'edit'   => pq_editor_url( $res['id'] ),
	) );
} );

/* ---------------- Live preview ---------------- */

add_action( 'wp_ajax_pq_preview', function () {
	pq_ajax_guard();
	$s = pq_settings();
	if ( ! empty( $_POST['form'] ) ) {
		$arr = array();
		wp_parse_str( wp_unslash( $_POST['form'] ), $arr ); // phpcs:ignore WordPress.Security
		$s = pq_sanitize_settings( isset( $arr['pq_settings'] ) ? $arr['pq_settings'] : array() );
	}
	if ( ! empty( $_POST['sample'] ) ) {
		$doc = pq_sample_doc( $s, isset( $_POST['sample_type'] ) ? sanitize_key( wp_unslash( $_POST['sample_type'] ) ) : 'quotation' ); // phpcs:ignore
	} else {
		$doc = pq_normalize_doc( pq_post_json( 'doc' ), $s );
		if ( '' === $doc['number'] ) { $doc['number'] = '(number assigned on save)'; }
	}
	wp_send_json_success( array( 'html' => pq_document_html( $doc, $s, 'preview' ) ) );
} );

/* ---------------- Store products ---------------- */

function pq_product_row( $id ) {
	$name   = html_entity_decode( wp_strip_all_tags( get_the_title( $id ) ), ENT_QUOTES, 'UTF-8' );
	$img_id = (int) get_post_thumbnail_id( $id );
	$sku    = '';
	$price  = '';
	$short  = '';
	$p      = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
	if ( $p ) {
		$sku = (string) $p->get_sku();
		$pr  = $p->get_price();
		if ( '' !== $pr && is_numeric( $pr ) && (float) $pr > 0 ) { $price = (float) $pr; }
		$short = wp_strip_all_tags( $p->get_short_description() );
	} else {
		$sku = (string) get_post_meta( $id, '_sku', true );
		$pr  = get_post_meta( $id, '_price', true );
		if ( '' !== $pr && is_numeric( $pr ) && (float) $pr > 0 ) { $price = (float) $pr; }
		$short = wp_strip_all_tags( get_the_excerpt( $id ) );
	}
	$short = trim( preg_replace( '/[ \t]+/', ' ', html_entity_decode( $short, ENT_QUOTES, 'UTF-8' ) ) );
	$short = wp_html_excerpt( $short, 300, '...' );
	$terms = get_the_terms( $id, 'product_cat' );
	return array(
		'id'     => (int) $id,
		'name'   => $name,
		'sku'    => $sku,
		'img_id' => $img_id,
		'thumb'  => $img_id ? (string) wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '',
		'price'  => $price,
		'desc'   => $short,
		'cat'    => ( $terms && ! is_wp_error( $terms ) ) ? html_entity_decode( $terms[0]->name, ENT_QUOTES, 'UTF-8' ) : '',
	);
}

add_action( 'wp_ajax_pq_products', function () {
	pq_ajax_guard();
	if ( ! post_type_exists( 'product' ) ) {
		wp_send_json_success( array( 'items' => array(), 'total' => 0, 'pages' => 0, 'page' => 1, 'cats' => array() ) );
	}
	global $wpdb;
	$q    = isset( $_POST['q'] ) ? pq_s_line( wp_unslash( $_POST['q'] ), 80 ) : ''; // phpcs:ignore
	$cat  = isset( $_POST['cat'] ) ? absint( $_POST['cat'] ) : 0;
	$page = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
	$args = array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => 20,
		'paged'               => $page,
		'orderby'             => 'title',
		'order'               => 'ASC',
		'ignore_sticky_posts' => true,
	);
	if ( '' !== $q ) { $args['s'] = $q; $args['orderby'] = 'relevance'; }
	if ( $cat ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cat, 'include_children' => true ) );
	}
	$wpq = new WP_Query( $args );
	$ids = array_map( 'intval', wp_list_pluck( $wpq->posts, 'ID' ) );

	if ( '' !== $q && 1 === $page ) {
		$sku_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s LIMIT 12",
			'%' . $wpdb->esc_like( $q ) . '%'
		) );
		foreach ( array_reverse( (array) $sku_ids ) as $sid ) {
			$sid = (int) $sid;
			if ( 'product' === get_post_type( $sid ) && 'publish' === get_post_status( $sid ) && ! in_array( $sid, $ids, true ) ) {
				array_unshift( $ids, $sid );
			}
		}
	}

	$items = array();
	foreach ( $ids as $pid ) { $items[] = pq_product_row( $pid ); }

	$cats = array();
	if ( ! empty( $_POST['with_cats'] ) ) { // phpcs:ignore
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'orderby' => 'name', 'number' => 150 ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( 'uncategorized' === $t->slug ) { continue; }
				$cats[] = array( 'id' => (int) $t->term_id, 'name' => html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ) );
			}
		}
	}
	wp_send_json_success( array(
		'items' => $items,
		'total' => (int) $wpq->found_posts,
		'pages' => (int) $wpq->max_num_pages,
		'page'  => $page,
		'cats'  => $cats,
	) );
} );

/* ---------------- Saved customers ---------------- */

add_action( 'wp_ajax_pq_customers', function () {
	pq_ajax_guard();
	$q    = isset( $_POST['q'] ) ? pq_s_line( wp_unslash( $_POST['q'] ), 80 ) : ''; // phpcs:ignore
	$args = array(
		'post_type'      => PQ_CUSTOMER,
		'post_status'    => 'publish',
		'posts_per_page' => 8,
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'fields'         => 'ids',
	);
	if ( '' !== $q ) {
		$args['meta_query'] = array( 'relation' => 'OR' );
		foreach ( array( 'name', 'company', 'email', 'phone', 'mobile' ) as $f ) {
			$args['meta_query'][] = array( 'key' => '_pq_c_' . $f, 'value' => $q, 'compare' => 'LIKE' );
		}
	}
	$out = array();
	foreach ( get_posts( $args ) as $id ) {
		$c = pq_get_customer( $id );
		if ( ! $c ) { continue; }
		$c['label'] = pq_customer_label( $c );
		$c['sub']   = trim( ( '' !== $c['company'] ? $c['name'] : '' ) . ( '' !== $c['email'] ? ' ' . $c['email'] : '' ) . ( '' !== $c['phone'] ? ' ' . $c['phone'] : '' ) );
		$out[] = $c;
	}
	wp_send_json_success( array( 'items' => $out ) );
} );

/* ---------------- Email ---------------- */

function pq_email_vars( $doc, $s ) {
	$doc = pq_doc_prepare( $doc, $s );
	$c   = $doc['customer'];
	$who = '' !== $c['name'] ? $c['name'] : ( '' !== $c['company'] ? $c['company'] : 'there' );
	$type = pq_doc_label( $doc['type'], $s );
	return array(
		'{customer}'    => $who,
		'{company}'     => $s['company']['name'],
		'{number}'      => $doc['number'],
		'{type}'        => $type,
		'{type_lower}'  => strtolower( $type ),
		'{total}'       => pq_money( $doc['calc']['total'], $doc['currency'], $s ),
		'{link}'        => pq_public_url( $doc['id'] ),
		'{valid_until}' => pq_fmt_date( $doc['expiry_date'], $s ),
	);
}

add_action( 'wp_ajax_pq_email_defaults', function () {
	pq_ajax_guard();
	$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore
	$doc = pq_get_doc( $id );
	if ( ! $doc ) { wp_send_json_error( array( 'message' => 'Save the document first.' ) ); }
	$s    = pq_settings();
	$vars = pq_email_vars( $doc, $s );
	wp_send_json_success( array(
		'to'      => $doc['customer']['email'],
		'subject' => strtr( $s['email']['subject'], $vars ),
		'body'    => strtr( $s['email']['body'], $vars ),
	) );
} );

add_action( 'wp_ajax_pq_send', function () {
	pq_ajax_guard();
	$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore
	$doc = pq_get_doc( $id );
	if ( ! $doc ) { wp_send_json_error( array( 'message' => 'Save the document first.' ) ); }
	$to = sanitize_email( isset( $_POST['to'] ) ? wp_unslash( $_POST['to'] ) : '' ); // phpcs:ignore
	if ( ! is_email( $to ) ) { wp_send_json_error( array( 'message' => 'Enter a valid email address.' ) ); }
	$subject = pq_s_line( isset( $_POST['subject'] ) ? wp_unslash( $_POST['subject'] ) : '', 200 ); // phpcs:ignore
	$body    = pq_s_text( isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : '', 6000 ); // phpcs:ignore
	if ( '' === $subject ) { wp_send_json_error( array( 'message' => 'Enter a subject.' ) ); }

	$s      = pq_settings();
	$link   = pq_public_url( $id );
	$type   = pq_doc_label( $doc['type'], $s );
	$accent = $s['template']['accent'];
	$html   = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1d2430;max-width:560px">'
		. wpautop( make_clickable( esc_html( $body ) ) )
		. '<p style="margin:22px 0"><a href="' . esc_url( $link ) . '" style="display:inline-block;padding:11px 20px;background:' . esc_attr( $accent ) . ';color:#ffffff;text-decoration:none;border-radius:4px;font-weight:600">View ' . esc_html( strtolower( $type ) ) . '</a></p></div>';

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	if ( is_email( $s['company']['email'] ) ) {
		$headers[] = 'Reply-To: ' . str_replace( array( '<', '>', ',', ';', '"' ), '', $s['company']['name'] ) . ' <' . $s['company']['email'] . '>';
	}
	if ( ! empty( $_POST['copy'] ) ) { // phpcs:ignore
		$me = wp_get_current_user();
		if ( $me && is_email( $me->user_email ) ) { $headers[] = 'Bcc: ' . $me->user_email; }
	}
	$sent = wp_mail( $to, $subject, $html, $headers );
	if ( ! $sent ) {
		wp_send_json_error( array( 'message' => 'The email could not be sent. Check the site\'s email setup (SMTP) and try again.' ) );
	}

	$log   = get_post_meta( $id, '_pq_sent_log', true );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array( 'time' => time(), 'to' => $to );
	update_post_meta( $id, '_pq_sent_log', array_slice( $log, -20 ) );

	if ( 'draft' === $doc['status'] ) {
		update_post_meta( $id, '_pq_status', 'sent' );
		$stored = get_post_meta( $id, '_pq_data', true );
		if ( is_array( $stored ) ) { $stored['status'] = 'sent'; update_post_meta( $id, '_pq_data', wp_slash( $stored ) ); }
	}
	$fresh = pq_get_doc( $id );
	wp_send_json_success( array( 'doc' => pq_doc_for_editor( $fresh ), 'links' => pq_doc_links( $id, $fresh ) ) );
} );
