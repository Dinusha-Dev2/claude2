<?php
/**
 * Documents and customers: load, normalise, save, convert, duplicate.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ------------------------------------------------------------------ */
/* Customers                                                           */
/* ------------------------------------------------------------------ */

function pq_customer_fields() {
	return array( 'name', 'company', 'email', 'phone', 'address', 'city', 'postcode', 'country', 'tax_no', 'currency' );
}

function pq_blank_customer() {
	return array_merge( array( 'id' => 0 ), array_fill_keys( pq_customer_fields(), '' ) );
}

function pq_clean_customer( $raw ) {
	$raw = is_array( $raw ) ? $raw : array();
	$c   = pq_blank_customer();
	$c['id']       = isset( $raw['id'] ) ? absint( $raw['id'] ) : 0;
	$c['name']     = pq_s_line( isset( $raw['name'] ) ? $raw['name'] : '', 120 );
	$c['company']  = pq_s_line( isset( $raw['company'] ) ? $raw['company'] : '', 160 );
	$c['email']    = sanitize_email( isset( $raw['email'] ) ? $raw['email'] : '' );
	$c['phone']    = pq_s_line( isset( $raw['phone'] ) ? $raw['phone'] : '', 60 );
	$c['address']  = pq_s_text( isset( $raw['address'] ) ? $raw['address'] : '', 400 );
	$c['city']     = pq_s_line( isset( $raw['city'] ) ? $raw['city'] : '', 80 );
	$c['postcode'] = pq_s_line( isset( $raw['postcode'] ) ? $raw['postcode'] : '', 20 );
	$c['country']  = pq_s_line( isset( $raw['country'] ) ? $raw['country'] : '', 80 );
	$c['tax_no']   = pq_s_line( isset( $raw['tax_no'] ) ? $raw['tax_no'] : '', 60 );
	$cur           = strtoupper( preg_replace( '/[^A-Za-z]/', '', isset( $raw['currency'] ) ? (string) $raw['currency'] : '' ) );
	$c['currency'] = in_array( $cur, wp_list_pluck( pq_settings()['currencies'], 'code' ), true ) ? $cur : '';
	if ( $c['id'] && PQ_CUSTOMER !== get_post_type( $c['id'] ) ) { $c['id'] = 0; }
	return $c;
}

function pq_customer_has_data( $c ) {
	return '' !== trim( $c['name'] . $c['company'] . $c['email'] . $c['phone'] );
}

/** Load a saved customer by post ID. */
function pq_get_customer( $id ) {
	$id = absint( $id );
	if ( ! $id || PQ_CUSTOMER !== get_post_type( $id ) ) { return null; }
	$c = pq_blank_customer();
	$c['id'] = $id;
	foreach ( pq_customer_fields() as $f ) {
		$c[ $f ] = (string) get_post_meta( $id, '_pq_c_' . $f, true );
	}
	return $c;
}

function pq_find_customer_by_email( $email ) {
	if ( '' === $email ) { return 0; }
	$q = get_posts( array(
		'post_type'      => PQ_CUSTOMER,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => '_pq_c_email', 'value' => $email, 'compare' => '=' ) ),
	) );
	return $q ? (int) $q[0] : 0;
}

function pq_customer_label( $c ) {
	$label = '' !== $c['company'] ? $c['company'] : $c['name'];
	return '' !== $label ? $label : $c['email'];
}

/** Create or update a customer record. Matches by ID, then by email. */
function pq_upsert_customer( $c ) {
	$id = (int) $c['id'];
	if ( ! $id ) { $id = pq_find_customer_by_email( $c['email'] ); }
	$title = pq_customer_label( $c );
	if ( $id && PQ_CUSTOMER === get_post_type( $id ) ) {
		wp_update_post( wp_slash( array( 'ID' => $id, 'post_title' => $title ) ) );
	} else {
		$id = wp_insert_post( wp_slash( array(
			'post_type'   => PQ_CUSTOMER,
			'post_status' => 'publish',
			'post_title'  => $title,
		) ) );
		if ( is_wp_error( $id ) || ! $id ) { return 0; }
	}
	foreach ( pq_customer_fields() as $f ) {
		update_post_meta( $id, '_pq_c_' . $f, wp_slash( $c[ $f ] ) );
	}
	return (int) $id;
}

/* ------------------------------------------------------------------ */
/* Document structure                                                  */
/* ------------------------------------------------------------------ */

function pq_default_bank_ids( $s = null ) {
	$s   = $s ? $s : pq_settings();
	$ids = array();
	foreach ( $s['banks'] as $b ) {
		if ( ! empty( $b['show'] ) ) { $ids[] = $b['id']; }
	}
	return $ids;
}

/** A new, unsaved document filled from settings. */
function pq_blank_doc( $type = 'quotation', $s = null, $numbered = true ) {
	$s    = $s ? $s : pq_settings();
	$type = isset( pq_types()[ $type ] ) ? $type : 'quotation';
	$number = '';
	if ( $numbered ) { list( $number ) = pq_next_number( $type, $s ); }
	$days = 'quotation' === $type ? (int) $s['defaults']['valid_days'] : (int) $s['defaults']['due_days'];
	$today = pq_today();
	return array(
		'id'            => 0,
		'type'          => $type,
		'number'        => $number,
		'number_auto'   => true,
		'status'        => 'draft',
		'issue_date'    => $today,
		'expiry_date'   => pq_date_plus( $days, $today ),
		'reference'     => '',
		'currency'      => $s['currency'],
		'customer'      => pq_blank_customer(),
		'save_customer' => true,
		'delivery'      => '',
		'items'         => array(),
		'discount'      => array( 'type' => 'percent', 'value' => 0 ),
		'shipping'      => array( 'amount' => 0 ),
		'tax'           => array( 'label' => $s['tax']['label'], 'rate' => $s['tax']['rate'], 'mode' => $s['tax']['mode'] ),
		'blocks'        => $s['blocks'],
		'banks'         => pq_default_bank_ids( $s ),
		'payments'      => array(),
		'internal'      => '',
		'rfq_id'        => 0,
		'source_id'     => 0,
		'source_number' => '',
	);
}

function pq_blank_item() {
	return array(
		'pid'      => 0,
		'name'     => '',
		'desc'     => '',
		'sku'      => '',
		'img_id'   => 0,
		'qty'      => 1,
		'unit'     => '',
		'price'    => '',
		'discount' => 0,
	);
}

/** Clean a document coming from the editor. Never trusts the browser. */
function pq_normalize_doc( $raw, $s = null ) {
	$s   = $s ? $s : pq_settings();
	$raw = is_array( $raw ) ? $raw : array();
	$d   = array();

	$d['id']          = isset( $raw['id'] ) ? absint( $raw['id'] ) : 0;
	$d['type']        = pq_s_enum( isset( $raw['type'] ) ? $raw['type'] : '', array_keys( pq_types() ), 'quotation' );
	$d['status']      = pq_s_enum( isset( $raw['status'] ) ? $raw['status'] : '', array_keys( pq_statuses( $d['type'] ) ), 'draft' );
	$d['number']      = pq_s_line( isset( $raw['number'] ) ? $raw['number'] : '', 40 );
	$d['number_auto'] = ! empty( $raw['number_auto'] );
	$d['issue_date']  = pq_s_date( isset( $raw['issue_date'] ) ? $raw['issue_date'] : '', pq_today() );
	$d['expiry_date'] = pq_s_date( isset( $raw['expiry_date'] ) ? $raw['expiry_date'] : '', '' );
	$d['reference']   = pq_s_line( isset( $raw['reference'] ) ? $raw['reference'] : '', 120 );

	$codes = wp_list_pluck( $s['currencies'], 'code' );
	$cur   = strtoupper( preg_replace( '/[^A-Za-z]/', '', isset( $raw['currency'] ) ? (string) $raw['currency'] : '' ) );
	$d['currency'] = in_array( $cur, $codes, true ) ? $cur : $s['currency'];

	$d['customer']      = pq_clean_customer( isset( $raw['customer'] ) ? $raw['customer'] : array() );
	$d['save_customer'] = ! empty( $raw['save_customer'] );
	$d['delivery']      = pq_s_text( isset( $raw['delivery'] ) ? $raw['delivery'] : '', 500 );

	$items = array();
	if ( isset( $raw['items'] ) && is_array( $raw['items'] ) ) {
		foreach ( array_slice( array_values( $raw['items'] ), 0, 300 ) as $r ) {
			if ( ! is_array( $r ) ) { continue; }
			$it = pq_blank_item();
			$it['pid']  = isset( $r['pid'] ) ? absint( $r['pid'] ) : 0;
			if ( $it['pid'] && 'product' !== get_post_type( $it['pid'] ) ) { $it['pid'] = 0; }
			$it['name'] = pq_s_line( isset( $r['name'] ) ? $r['name'] : '', 300 );
			$it['desc'] = pq_s_text( isset( $r['desc'] ) ? $r['desc'] : '', 1500 );
			$it['sku']  = pq_s_line( isset( $r['sku'] ) ? $r['sku'] : '', 60 );
			$img        = isset( $r['img_id'] ) ? absint( $r['img_id'] ) : 0;
			$it['img_id'] = ( $img && wp_attachment_is_image( $img ) ) ? $img : 0;
			$qty = isset( $r['qty'] ) && '' !== trim( (string) $r['qty'] ) ? pq_num( $r['qty'] ) : 1;
			$it['qty']      = max( 0, $qty );
			$it['unit']     = pq_s_line( isset( $r['unit'] ) ? $r['unit'] : '', 24 );
			$it['price']    = isset( $r['price'] ) && '' !== trim( (string) $r['price'] ) ? max( 0, pq_num( $r['price'] ) ) : '';
			$it['discount'] = (float) min( 100, max( 0, pq_num( isset( $r['discount'] ) ? $r['discount'] : 0 ) ) );
			if ( '' === $it['name'] && '' === $it['desc'] && ! $it['pid'] && '' === $it['price'] && ! $it['img_id'] ) { continue; }
			$items[] = $it;
		}
	}
	$d['items'] = $items;

	$dd = isset( $raw['discount'] ) && is_array( $raw['discount'] ) ? $raw['discount'] : array();
	$d['discount'] = array(
		'type'  => pq_s_enum( isset( $dd['type'] ) ? $dd['type'] : 'percent', array( 'percent', 'fixed' ), 'percent' ),
		'value' => max( 0, pq_num( isset( $dd['value'] ) ? $dd['value'] : 0 ) ),
	);
	$d['shipping'] = array( 'amount' => max( 0, pq_num( isset( $raw['shipping']['amount'] ) ? $raw['shipping']['amount'] : 0 ) ) );

	$tx = isset( $raw['tax'] ) && is_array( $raw['tax'] ) ? $raw['tax'] : array();
	$d['tax'] = array(
		'label' => pq_s_line( isset( $tx['label'] ) ? $tx['label'] : $s['tax']['label'], 30 ),
		'rate'  => min( 100, max( 0, pq_num( isset( $tx['rate'] ) ? $tx['rate'] : 0 ) ) ),
		'mode'  => pq_s_enum( isset( $tx['mode'] ) ? $tx['mode'] : 'exclusive', array( 'exclusive', 'inclusive', 'none' ), 'exclusive' ),
	);

	$d['blocks'] = pq_sanitize_blocks( isset( $raw['blocks'] ) ? $raw['blocks'] : array() );

	$valid_banks = wp_list_pluck( $s['banks'], 'id' );
	$banks       = array();
	if ( isset( $raw['banks'] ) && is_array( $raw['banks'] ) ) {
		foreach ( $raw['banks'] as $b ) {
			$b = is_scalar( $b ) ? (string) $b : '';
			if ( in_array( $b, $valid_banks, true ) && ! in_array( $b, $banks, true ) ) { $banks[] = $b; }
		}
	}
	$d['banks'] = $banks;

	$pays = array();
	if ( isset( $raw['payments'] ) && is_array( $raw['payments'] ) ) {
		foreach ( array_slice( array_values( $raw['payments'] ), 0, 60 ) as $p ) {
			if ( ! is_array( $p ) ) { continue; }
			$amt = max( 0, pq_num( isset( $p['amount'] ) ? $p['amount'] : 0 ) );
			if ( $amt <= 0 ) { continue; }
			$pays[] = array(
				'date'   => pq_s_date( isset( $p['date'] ) ? $p['date'] : '', pq_today() ),
				'amount' => $amt,
				'method' => pq_s_line( isset( $p['method'] ) ? $p['method'] : '', 60 ),
				'note'   => pq_s_line( isset( $p['note'] ) ? $p['note'] : '', 160 ),
			);
		}
	}
	$d['payments'] = ( 'quotation' === $d['type'] ) ? array() : $pays;

	$d['internal']      = pq_s_text( isset( $raw['internal'] ) ? $raw['internal'] : '', 3000 );
	$d['rfq_id']        = isset( $raw['rfq_id'] ) ? absint( $raw['rfq_id'] ) : 0;
	$d['source_id']     = isset( $raw['source_id'] ) ? absint( $raw['source_id'] ) : 0;
	$d['source_number'] = pq_s_line( isset( $raw['source_number'] ) ? $raw['source_number'] : '', 40 );

	return $d;
}

/** Add computed totals and image URLs, ready for rendering. */
function pq_doc_prepare( $doc, $s = null ) {
	$s = $s ? $s : pq_settings();
	$doc['calc'] = pq_calculate( $doc, $s );
	foreach ( $doc['items'] as $i => $it ) {
		if ( isset( $it['img_url'] ) && '' !== $it['img_url'] ) { continue; }
		$doc['items'][ $i ]['img_url'] = ! empty( $it['img_id'] ) ? (string) wp_get_attachment_image_url( (int) $it['img_id'], 'medium' ) : '';
	}
	return $doc;
}

/** Load a saved document. Returns null if missing. */
function pq_get_doc( $id ) {
	$id = absint( $id );
	if ( ! $id || PQ_CPT !== get_post_type( $id ) ) { return null; }
	$stored = get_post_meta( $id, '_pq_data', true );
	if ( ! is_array( $stored ) ) { return null; }
	$base = pq_blank_doc( isset( $stored['type'] ) ? $stored['type'] : 'quotation', null, false );
	$doc  = array_merge( $base, $stored );
	$doc['id']          = $id;
	$doc['number_auto'] = false;
	$doc['number']      = (string) get_post_meta( $id, '_pq_number', true );
	return $doc;
}

/** Document as the editor needs it, with image URLs for thumbnails. */
function pq_doc_for_editor( $doc ) {
	foreach ( $doc['items'] as $i => $it ) {
		$doc['items'][ $i ]['img'] = ! empty( $it['img_id'] ) ? (string) wp_get_attachment_image_url( (int) $it['img_id'], 'thumbnail' ) : '';
	}
	return $doc;
}

/** Status shown to people: adds computed Expired and Overdue. */
function pq_display_status( $doc, $s = null ) {
	$s    = $s ? $s : pq_settings();
	$all  = pq_all_statuses();
	$st   = isset( $doc['status'] ) ? $doc['status'] : 'draft';
	$key  = $st;
	$today = pq_today();
	if ( 'quotation' === $doc['type'] && 'sent' === $st && $doc['expiry_date'] && $doc['expiry_date'] < $today ) {
		return array( 'expired', 'Expired' );
	}
	if ( 'quotation' !== $doc['type'] && in_array( $st, array( 'sent', 'partial' ), true ) && $doc['expiry_date'] && $doc['expiry_date'] < $today ) {
		$calc = isset( $doc['calc'] ) ? $doc['calc'] : pq_calculate( $doc, $s );
		if ( $calc['balance'] > 0 ) { return array( 'overdue', 'Overdue' ); }
	}
	return array( $key, isset( $all[ $st ] ) ? $all[ $st ] : ucfirst( $st ) );
}

/* ------------------------------------------------------------------ */
/* Save                                                                */
/* ------------------------------------------------------------------ */

/** Save a document from editor data. Returns array( id, doc ) or WP_Error. */
function pq_save_document( $raw, $id = 0 ) {
	$s   = pq_settings();
	$d   = pq_normalize_doc( $raw, $s );
	$id  = absint( $id );
	$bump = 0;

	if ( $id ) {
		$existing = get_post( $id );
		if ( ! $existing || PQ_CPT !== $existing->post_type ) {
			return new WP_Error( 'pq_missing', 'This document no longer exists.' );
		}
		if ( '' === $d['number'] ) { $d['number'] = (string) get_post_meta( $id, '_pq_number', true ); }
		if ( pq_number_exists( $d['number'], $id ) ) {
			return new WP_Error( 'pq_number', 'That document number is already used. Choose a different number.' );
		}
		$old_type = get_post_meta( $id, '_pq_type', true );
		if ( $old_type && $old_type !== $d['type'] && get_post_meta( $id, '_pq_number', true ) === $d['number'] ) {
			// Type changed: give it a number from the new series unless the user typed one.
			list( $d['number'], $bump_num ) = pq_next_number( $d['type'], $s );
			$bump = $bump_num;
		}
	} else {
		if ( '' === $d['number'] || $d['number_auto'] ) {
			list( $d['number'], $bump ) = pq_next_number( $d['type'], $s );
		} elseif ( pq_number_exists( $d['number'] ) ) {
			return new WP_Error( 'pq_number', 'That document number is already used. Choose a different number.' );
		}
	}

	// Customer record.
	$c   = $d['customer'];
	$cid = 0;
	if ( pq_customer_has_data( $c ) ) {
		if ( $d['save_customer'] ) {
			$cid = pq_upsert_customer( $c );
		} elseif ( $c['id'] ) {
			$cid = (int) $c['id'];
		}
	}
	$d['customer']['id'] = $cid;

	// Payment status follows the payments recorded.
	$calc = pq_calculate( $d, $s );
	if ( 'quotation' !== $d['type'] && 'cancelled' !== $d['status'] ) {
		if ( $calc['total'] > 0 && $calc['paid'] >= $calc['total'] ) {
			$d['status'] = 'paid';
		} elseif ( $calc['paid'] > 0 ) {
			$d['status'] = 'partial';
		} elseif ( in_array( $d['status'], array( 'paid', 'partial' ), true ) ) {
			$d['status'] = 'sent';
		}
	}

	$label = pq_customer_label( $c );
	$title = trim( $d['number'] . ( '' !== $label ? ' - ' . $label : '' ) );
	$arr   = array( 'post_type' => PQ_CPT, 'post_status' => 'publish', 'post_title' => $title );
	if ( $id ) {
		$arr['ID'] = $id;
		$res = wp_update_post( wp_slash( $arr ), true );
	} else {
		$res = wp_insert_post( wp_slash( $arr ), true );
	}
	if ( is_wp_error( $res ) || ! $res ) {
		return is_wp_error( $res ) ? $res : new WP_Error( 'pq_save', 'The document could not be saved.' );
	}
	$id = (int) $res;

	$store = $d;
	unset( $store['id'], $store['number_auto'] );
	update_post_meta( $id, '_pq_data', wp_slash( $store ) );
	update_post_meta( $id, '_pq_number', wp_slash( $d['number'] ) );
	update_post_meta( $id, '_pq_type', $d['type'] );
	update_post_meta( $id, '_pq_status', $d['status'] );
	update_post_meta( $id, '_pq_currency', $d['currency'] );
	update_post_meta( $id, '_pq_issue', $d['issue_date'] );
	update_post_meta( $id, '_pq_total', $calc['total'] );
	update_post_meta( $id, '_pq_balance', $calc['balance'] );
	update_post_meta( $id, '_pq_customer', $cid );
	update_post_meta( $id, '_pq_customer_name', wp_slash( $label ) );
	update_post_meta( $id, '_pq_rfq', (int) $d['rfq_id'] );
	update_post_meta( $id, '_pq_source', (int) $d['source_id'] );
	if ( ! get_post_meta( $id, '_pq_key', true ) ) {
		update_post_meta( $id, '_pq_key', wp_generate_password( 32, false, false ) );
	}
	if ( $bump ) { pq_bump_number( $d['type'], $bump ); }

	$saved = pq_get_doc( $id );
	return array( 'id' => $id, 'doc' => $saved );
}

/* ------------------------------------------------------------------ */
/* Convert and duplicate                                               */
/* ------------------------------------------------------------------ */

function pq_convert_document( $id, $target ) {
	$src = pq_get_doc( $id );
	if ( ! $src ) { return new WP_Error( 'pq_missing', 'Document not found.' ); }
	if ( ! in_array( $target, array( 'proforma', 'invoice' ), true ) ) {
		return new WP_Error( 'pq_type', 'Choose proforma invoice or invoice.' );
	}
	$s   = pq_settings();
	$new = $src;
	$new['id']            = 0;
	$new['type']          = $target;
	$new['status']        = 'draft';
	$new['number']        = '';
	$new['number_auto']   = true;
	$new['issue_date']    = pq_today();
	$new['expiry_date']   = pq_date_plus( (int) $s['defaults']['due_days'] );
	$new['payments']      = array();
	$new['source_id']     = $src['id'];
	$new['source_number'] = $src['number'];
	$new['save_customer'] = false;
	$res = pq_save_document( $new, 0 );
	if ( is_wp_error( $res ) ) { return $res; }
	if ( 'quotation' === $src['type'] ) {
		$src['status'] = 'converted';
		update_post_meta( $id, '_pq_status', 'converted' );
		$stored = get_post_meta( $id, '_pq_data', true );
		if ( is_array( $stored ) ) {
			$stored['status'] = 'converted';
			update_post_meta( $id, '_pq_data', wp_slash( $stored ) );
		}
	}
	update_post_meta( $id, '_pq_converted_to', (int) $res['id'] );
	return $res;
}

function pq_duplicate_document( $id ) {
	$src = pq_get_doc( $id );
	if ( ! $src ) { return new WP_Error( 'pq_missing', 'Document not found.' ); }
	$s   = pq_settings();
	$new = $src;
	$days = 'quotation' === $src['type'] ? (int) $s['defaults']['valid_days'] : (int) $s['defaults']['due_days'];
	$new['id']            = 0;
	$new['status']        = 'draft';
	$new['number']        = '';
	$new['number_auto']   = true;
	$new['issue_date']    = pq_today();
	$new['expiry_date']   = pq_date_plus( $days );
	$new['payments']      = array();
	$new['source_id']     = 0;
	$new['source_number'] = '';
	$new['rfq_id']        = 0;
	$new['save_customer'] = false;
	return pq_save_document( $new, 0 );
}

/* ------------------------------------------------------------------ */
/* Links                                                               */
/* ------------------------------------------------------------------ */

function pq_editor_url( $id = 0, $args = array() ) {
	$q = array( 'page' => 'pq-editor' );
	if ( $id ) { $q['doc'] = (int) $id; }
	return add_query_arg( array_merge( $q, $args ), admin_url( 'admin.php' ) );
}

function pq_public_url( $id ) {
	$key = get_post_meta( $id, '_pq_key', true );
	if ( ! $key ) { return ''; }
	return add_query_arg( array( 'pq_doc' => (int) $id, 'k' => $key ), home_url( '/' ) );
}

function pq_action_url( $action, $id, $args = array() ) {
	$url = add_query_arg( array_merge( array( 'action' => $action, 'id' => (int) $id ), $args ), admin_url( 'admin-post.php' ) );
	return wp_nonce_url( $url, $action . '_' . (int) $id );
}
