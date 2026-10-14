<?php
/**
 * Helpers: capability, settings and defaults, formatting, numbering and totals.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ------------------------------------------------------------------ */
/* Access                                                              */
/* ------------------------------------------------------------------ */

/** Capability needed to manage quotations. Shop managers and admins by default. */
function pq_cap() {
	$cap = class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
	return apply_filters( 'pq_capability', $cap );
}
function pq_can() {
	return current_user_can( pq_cap() );
}

/* ------------------------------------------------------------------ */
/* Types and statuses                                                  */
/* ------------------------------------------------------------------ */

function pq_types() {
	return array(
		'quotation' => 'Quotation',
		'proforma'  => 'Proforma invoice',
		'invoice'   => 'Tax invoice',
		'delivery'  => 'Delivery note',
	);
}

/** Types that take payments. */
function pq_type_has_payments( $type ) {
	return in_array( $type, array( 'proforma', 'invoice' ), true );
}

/** What the second date means for a type: valid (until), due, or nothing. */
function pq_type_expiry( $type ) {
	if ( 'invoice' === $type ) { return 'due'; }
	if ( 'delivery' === $type ) { return ''; }
	return 'valid';
}

/** Days from the issue date to the second date for a new document. */
function pq_type_days( $type, $s = null ) {
	$s = $s ? $s : pq_settings();
	$k = pq_type_expiry( $type );
	if ( 'due' === $k ) { return (int) $s['defaults']['due_days']; }
	if ( 'valid' === $k ) { return (int) $s['defaults']['valid_days']; }
	return 0;
}

/** Which documents each type can be turned into. */
function pq_convert_targets( $type ) {
	$map = array(
		'quotation' => array( 'proforma', 'invoice', 'delivery' ),
		'proforma'  => array( 'invoice', 'delivery' ),
		'invoice'   => array( 'delivery' ),
		'delivery'  => array( 'invoice' ),
	);
	return isset( $map[ $type ] ) ? $map[ $type ] : array();
}

function pq_statuses( $type = 'quotation' ) {
	if ( 'quotation' === $type ) {
		return array(
			'draft'     => 'Draft',
			'sent'      => 'Sent',
			'accepted'  => 'Accepted',
			'declined'  => 'Declined',
			'converted' => 'Converted',
		);
	}
	if ( 'delivery' === $type ) {
		return array(
			'draft'     => 'Draft',
			'sent'      => 'Dispatched',
			'delivered' => 'Delivered',
			'cancelled' => 'Cancelled',
		);
	}
	$list = array(
		'draft'     => 'Draft',
		'sent'      => 'Sent',
		'partial'   => 'Part paid',
		'paid'      => 'Paid',
		'cancelled' => 'Cancelled',
	);
	if ( 'proforma' === $type ) { $list['converted'] = 'Invoiced'; }
	return $list;
}

/** Every status key once. The first label wins, so "sent" reads "Sent" in mixed lists. */
function pq_all_statuses() {
	return pq_statuses( 'quotation' ) + pq_statuses( 'invoice' ) + pq_statuses( 'delivery' );
}

/* ------------------------------------------------------------------ */
/* Show / hide                                                         */
/* ------------------------------------------------------------------ */

/** Parts of the document that can be shown or hidden. key => array( label, group, types or null for all ). */
function pq_display_fields() {
	return array(
		'logo'             => array( 'Logo', 'header', null ),
		'company_address'  => array( 'Our address', 'header', null ),
		'company_details'  => array( 'Our registration details (TIN, reg. no.)', 'header', null ),
		'company_contact'  => array( 'Our phone, email and website', 'header', null ),
		'customer_address' => array( 'Customer address', 'customer', null ),
		'customer_contact' => array( 'Customer phone and email', 'customer', null ),
		'customer_tax'     => array( 'Customer tax number', 'customer', null ),
		'deliver_to'       => array( 'Delivery address', 'customer', null ),
		'reference'        => array( 'Customer reference / PO no.', 'details', null ),
		'source_ref'       => array( 'Linked document number', 'details', null ),
		'expiry'           => array( 'Valid until / due date', 'details', array( 'quotation', 'proforma', 'invoice' ) ),
		'delivery_info'    => array( 'Delivery method, vehicle and driver', 'details', array( 'delivery' ) ),
		'extra'            => array( 'Extra details', 'details', null ),
		'row_numbers'      => array( 'Row numbers', 'items', null ),
		'images'           => array( 'Item images', 'items', null ),
		'item_desc'        => array( 'Item descriptions', 'items', null ),
		'sku'              => array( 'SKU', 'items', null ),
		'unit'             => array( 'Unit beside quantity', 'items', null ),
		'prices'           => array( 'Prices, amounts and totals', 'items', null ),
		'line_discount'    => array( 'Line discount column (when used)', 'items', null ),
		'amount_words'     => array( 'Amount in words', 'totals', null ),
		'payments'         => array( 'Payments received', 'totals', array( 'proforma', 'invoice' ) ),
		'terms'            => array( 'Terms and notes', 'end', null ),
		'banks'            => array( 'Bank details', 'end', null ),
		'signature'        => array( 'Signature lines', 'end', null ),
		'footer'           => array( 'Footer text', 'end', null ),
	);
}

function pq_display_groups() {
	return array(
		'header'   => 'Your business',
		'customer' => 'Customer',
		'details'  => 'Document details',
		'items'    => 'Item table',
		'totals'   => 'Totals',
		'end'      => 'End of document',
	);
}

function pq_display_applies( $key, $type ) {
	$f = pq_display_fields();
	return isset( $f[ $key ] ) && ( null === $f[ $key ][2] || in_array( $type, $f[ $key ][2], true ) );
}

/** Built-in starting point for each type. */
function pq_display_factory() {
	$out = array();
	foreach ( array_keys( pq_types() ) as $t ) {
		foreach ( array_keys( pq_display_fields() ) as $k ) { $out[ $t ][ $k ] = 1; }
	}
	$out['quotation']['amount_words'] = 0;
	$out['quotation']['signature']    = 0;
	$out['proforma']['signature']     = 0;
	foreach ( array( 'prices', 'line_discount', 'amount_words', 'banks' ) as $k ) { $out['delivery'][ $k ] = 0; }
	return $out;
}

/** What a document shows: settings defaults for its type, overridden by its own choices. */
function pq_doc_show( $doc, $s = null ) {
	$s    = $s ? $s : pq_settings();
	$type = isset( $doc['type'] ) ? $doc['type'] : 'quotation';
	$def  = isset( $s['display'][ $type ] ) ? $s['display'][ $type ] : array();
	$own  = isset( $doc['show'] ) && is_array( $doc['show'] ) ? $doc['show'] : array();
	$out  = array();
	foreach ( array_keys( pq_display_fields() ) as $k ) {
		$v = array_key_exists( $k, $own ) ? $own[ $k ] : ( isset( $def[ $k ] ) ? $def[ $k ] : 1 );
		$out[ $k ] = pq_display_applies( $k, $type ) && ! empty( $v );
	}
	if ( ! $out['prices'] ) { $out['line_discount'] = false; $out['amount_words'] = false; }
	return $out;
}

/** Default show / hide choices for a new document of a type. */
function pq_display_for_type( $type, $s = null ) {
	$s   = $s ? $s : pq_settings();
	$out = array();
	foreach ( array_keys( pq_display_fields() ) as $k ) {
		$out[ $k ] = empty( $s['display'][ $type ][ $k ] ) ? 0 : 1;
	}
	return $out;
}

/* ------------------------------------------------------------------ */
/* Settings                                                            */
/* ------------------------------------------------------------------ */

function pq_default_labels() {
	return array(
		'doc_quotation'      => 'Quotation',
		'doc_proforma'       => 'Proforma invoice',
		'doc_invoice'        => 'Tax invoice',
		'doc_delivery'       => 'Delivery note',
		'number'             => 'No.',
		'date'               => 'Date',
		'valid_until'        => 'Valid until',
		'due_date'           => 'Due date',
		'reference'          => 'Your reference',
		'source'             => '{type} ref.',
		'delivery_method'    => 'Delivery method',
		'vehicle'            => 'Vehicle no.',
		'driver'             => 'Delivered by',
		'from'               => 'From',
		'bill_to'            => 'Billed to',
		'deliver_to'         => 'Deliver to',
		'customer_tax'       => 'Tax no.',
		'attn'               => 'Attn.',
		'address'            => 'Address',
		'phone'              => 'Ph.',
		'email'              => 'Email',
		'web'                => 'Web',
		'summary_title'      => '{type} summary',
		'col_no'             => '#',
		'col_item'           => 'Description',
		'col_qty'            => 'Qty',
		'col_price'          => 'Unit price',
		'col_discount'       => 'Disc.',
		'col_amount'         => 'Amount',
		'sku'                => 'SKU',
		'subtotal'           => 'Subtotal',
		'discount'           => 'Discount',
		'shipping'           => 'Delivery',
		'total'              => 'Total',
		'paid'               => 'Paid',
		'balance'            => 'Balance due',
		'amount_words'       => 'Amount in words',
		'payments'           => 'Payments received',
		'bank'               => 'Bank details',
		'signature_company'  => 'Authorised signature',
		'signature_customer' => 'Customer acceptance',
		'signature_buyer'    => 'Customer signature',
		'signature_delivered' => 'Delivered by',
		'signature_received' => 'Received in good condition by',
		'signature_hint'     => 'Name, signature and date',
		'paid_stamp'         => 'Paid in full',
	);
}

function pq_default_settings() {
	return array(
		'company'    => array(
			'name'    => 'PMT ASIA PVT. LTD.',
			'logo_id' => 0,
			'address' => 'Blue Heaven, Male, Maldives.',
			'phone'   => '+9607686927 | +94704149823 | +94372096118',
			'email'   => 'sales1@pmtasia.net',
			'website' => '',
			'details' => array(
				array( 'label' => 'Postcode', 'value' => '20002' ),
				array( 'label' => 'TIN No.', 'value' => '1158960GST501' ),
				array( 'label' => 'Registration No.', 'value' => 'C11872023' ),
			),
		),
		'numbering'  => array(
			'quotation' => array( 'prefix' => 'QT-', 'next' => 1, 'pad' => 4 ),
			'proforma'  => array( 'prefix' => 'PI-', 'next' => 1, 'pad' => 4 ),
			'invoice'   => array( 'prefix' => 'TXI-', 'next' => 1, 'pad' => 4 ),
			'delivery'  => array( 'prefix' => 'DN-', 'next' => 1, 'pad' => 4 ),
		),
		'defaults'   => array(
			'valid_days' => 14,
			'due_days'   => 7,
			'unit'       => 'Pcs',
			'units'      => "Pcs\nSets\nMetres\nKg\nLitres\nBoxes\nRolls\nLot",
		),
		'format'     => array(
			'decimals' => 2,
			'dec_sep'  => '.',
			'thou_sep' => ',',
			'date'     => 'j M Y',
		),
		'currency'   => 'USD',
		'currencies' => array(
			pq_currency_row( 'USD', '$', 0 ),
			pq_currency_row( 'MVR', 'MVR', 1 ),
			pq_currency_row( 'LKR', 'Rs.', 1 ),
			pq_currency_row( 'EUR', '€', 0 ),
			pq_currency_row( 'GBP', '£', 0 ),
		),
		'tax'        => array(
			'label'        => 'GST',
			'rate'         => 8,
			'mode'         => 'exclusive',
			'tax_shipping' => 0,
		),
		'blocks'     => array(
			array(
				'title' => 'Payment terms',
				'body'  => "50% advance payment upon confirmation and receipt of Purchase Order (PO). Balance 50% payable within 7 days after delivery.\nPayment mode: TT / Bank transfer.",
				'style' => '',
			),
			array(
				'title' => 'Delivery terms',
				'body'  => "Delivery to customer's doorstep within 7-14 working days.",
				'style' => '',
			),
			array(
				'title' => 'Partial orders policy',
				'body'  => 'Partial or split deliveries are not accepted. Orders will be processed as a complete lot.',
				'style' => 'highlight',
			),
			pq_delivery_block(),
		),
		'banks'      => array(
			array(
				'id'           => 'bank_boc',
				'account_name' => 'PMT ASIA (PVT) LTD',
				'bank'         => 'Bank of Ceylon',
				'account_no'   => '92435591',
				'branch'       => 'Paragahadeniya',
				'swift'        => 'BCEYLKLX',
				'show'         => 1,
			),
			array(
				'id'           => 'bank_bml',
				'account_name' => 'PMT ASIA (PVT) LTD',
				'bank'         => 'Bank of Maldives',
				'account_no'   => '7730000662957',
				'branch'       => 'Male Maldives',
				'swift'        => 'MALBMVMVXXX',
				'show'         => 1,
			),
		),
		'labels'     => pq_default_labels(),
		'footer'     => array(
			'quotation' => 'This quotation is valid for {days} days only.',
			'proforma'  => 'This proforma invoice is valid until {valid_until}.',
			'invoice'   => 'Thank you for your business.',
			'delivery'  => 'Goods remain the property of {company} until paid in full.',
		),
		'display'    => pq_display_factory(),
		'template'   => array(
			'accent'         => '#1a1a1a',
			'font'           => 'arial',
			'size'           => 'regular',
			'logo_height'    => 90,
			'img_size'       => 48,
			'custom_css'     => '',
		),
		'email'      => array(
			'subject' => '{type} {number} from {company}',
			'body'    => "Hi {customer},\n\nPlease find your {type_lower} {number} for {total}.\n\nYou can view it, print it or save it as a PDF here:\n{link}\n\nIf you have any questions, just reply to this email.\n\n{company}",
		),
		'general'    => array(
			'delete_on_uninstall' => 0,
		),
	);
}

/** Known currencies: symbol, tax and the words used for "amount in words". */
function pq_currency_row( $code, $symbol, $space ) {
	$known = array(
		'USD' => array( 'GST', '8', 'US Dollars', 'Cents' ),
		'LKR' => array( 'VAT', '18', 'Sri Lankan Rupees', 'Cents' ),
		'MVR' => array( '', '', 'Maldivian Rufiyaa', 'Laari' ),
		'EUR' => array( '', '', 'Euros', 'Cents' ),
		'GBP' => array( '', '', 'Pounds Sterling', 'Pence' ),
	);
	$k = isset( $known[ $code ] ) ? $known[ $code ] : array( '', '', '', '' );
	return array(
		'code' => $code, 'symbol' => $symbol, 'position' => 'before', 'space' => $space, 'decimals' => '',
		'tax_label' => $k[0], 'tax_rate' => $k[1], 'word' => $k[2], 'subword' => $k[3],
	);
}

function pq_delivery_block() {
	return array(
		'title' => 'Goods received',
		'body'  => 'Please check all goods on delivery. Any shortage or damage must be reported within 24 hours.',
		'style' => '',
		'on'    => 'delivery',
	);
}

function pq_is_assoc( $a ) {
	return is_array( $a ) && $a && array_keys( $a ) !== range( 0, count( $a ) - 1 );
}

/** Merge saved settings over defaults. Associative groups merge key by key; lists are replaced whole. */
function pq_merge( $def, $saved ) {
	foreach ( $def as $k => $v ) {
		if ( ! array_key_exists( $k, $saved ) ) { continue; }
		if ( pq_is_assoc( $v ) && is_array( $saved[ $k ] ) ) {
			$def[ $k ] = pq_merge( $v, $saved[ $k ] );
		} else {
			$def[ $k ] = $saved[ $k ];
		}
	}
	return $def;
}

function pq_settings( $reset = false ) {
	static $cache = null;
	if ( null === $cache || $reset ) {
		$saved = get_option( 'pq_settings', array() );
		$cache = pq_merge( pq_default_settings(), is_array( $saved ) ? $saved : array() );
		$cache['currencies'] = pq_fix_currencies( $cache['currencies'] );
	}
	return $cache;
}

/** Older saved lists may lack decimals or carry an unused rate. Tidy them without touching what the user set. */
function pq_fix_currencies( $list ) {
	foreach ( $list as $i => $c ) {
		foreach ( array( 'decimals', 'tax_label', 'tax_rate', 'word', 'subword' ) as $k ) {
			if ( ! isset( $c[ $k ] ) ) { $list[ $i ][ $k ] = ''; }
		}
		unset( $list[ $i ]['rate'] );
	}
	return $list;
}
add_action( 'update_option_pq_settings', function () { pq_settings( true ); } );
add_action( 'add_option_pq_settings', function () { pq_settings( true ); } );

/* ------------------------------------------------------------------ */
/* Small sanitisers                                                    */
/* ------------------------------------------------------------------ */

function pq_s_line( $v, $max = 200 ) {
	$v = sanitize_text_field( is_scalar( $v ) ? (string) $v : '' );
	return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max );
}
function pq_s_text( $v, $max = 3000 ) {
	$v = sanitize_textarea_field( is_scalar( $v ) ? (string) $v : '' );
	return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max );
}
function pq_s_date( $v, $fallback = '' ) {
	$v = is_scalar( $v ) ? trim( (string) $v ) : '';
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
		$p = explode( '-', $v );
		if ( checkdate( (int) $p[1], (int) $p[2], (int) $p[0] ) ) { return $v; }
	}
	return $fallback;
}
function pq_s_enum( $v, $allowed, $fallback ) {
	return ( is_scalar( $v ) && in_array( (string) $v, $allowed, true ) ) ? (string) $v : $fallback;
}
function pq_s_bool( $v ) {
	return ( ! empty( $v ) && 'false' !== $v && '0' !== $v ) ? 1 : 0;
}
function pq_num( $v ) {
	if ( is_bool( $v ) || ! is_scalar( $v ) ) { return 0.0; }
	$v = str_replace( array( ',', ' ' ), '', (string) $v );
	return is_numeric( $v ) ? (float) $v : 0.0;
}
function pq_s_color( $v, $fallback ) {
	$c = sanitize_hex_color( is_scalar( $v ) ? (string) $v : '' );
	return $c ? $c : $fallback;
}

/** Settings sanitiser (register_setting callback). */
function pq_sanitize_settings( $in ) {
	$d   = pq_default_settings();
	$in  = is_array( $in ) ? $in : array();
	$out = array();

	// A full form post carries a marker, so a missing list (for example no banks) means "none".
	// Any other update is partial: sections that are not sent keep their defaults.
	if ( empty( $in['_form'] ) ) {
		foreach ( $d as $k => $v ) {
			if ( ! array_key_exists( $k, $in ) ) { $in[ $k ] = $v; }
		}
	}

	// Company.
	$c   = isset( $in['company'] ) && is_array( $in['company'] ) ? $in['company'] : array();
	$det = array();
	if ( isset( $c['details'] ) && is_array( $c['details'] ) ) {
		foreach ( array_slice( array_values( $c['details'] ), 0, 12 ) as $r ) {
			$label = pq_s_line( isset( $r['label'] ) ? $r['label'] : '', 60 );
			$value = pq_s_line( isset( $r['value'] ) ? $r['value'] : '', 120 );
			if ( '' !== $label || '' !== $value ) { $det[] = array( 'label' => $label, 'value' => $value ); }
		}
	}
	$out['company'] = array(
		'name'    => pq_s_line( isset( $c['name'] ) ? $c['name'] : '', 120 ),
		'logo_id' => isset( $c['logo_id'] ) ? absint( $c['logo_id'] ) : 0,
		'address' => pq_s_text( isset( $c['address'] ) ? $c['address'] : '', 500 ),
		'phone'   => pq_s_line( isset( $c['phone'] ) ? $c['phone'] : '', 160 ),
		'email'   => sanitize_email( isset( $c['email'] ) ? $c['email'] : '' ),
		'website' => esc_url_raw( isset( $c['website'] ) ? $c['website'] : '' ),
		'details' => $det,
	);

	// Numbering.
	$out['numbering'] = array();
	foreach ( array_keys( pq_types() ) as $t ) {
		$n = isset( $in['numbering'][ $t ] ) && is_array( $in['numbering'][ $t ] ) ? $in['numbering'][ $t ] : array();
		$out['numbering'][ $t ] = array(
			'prefix' => pq_s_line( isset( $n['prefix'] ) ? $n['prefix'] : $d['numbering'][ $t ]['prefix'], 20 ),
			'next'   => max( 1, isset( $n['next'] ) ? absint( $n['next'] ) : (int) $d['numbering'][ $t ]['next'] ),
			'pad'    => min( 10, isset( $n['pad'] ) ? absint( $n['pad'] ) : 0 ),
		);
	}

	// Defaults.
	$df = isset( $in['defaults'] ) && is_array( $in['defaults'] ) ? $in['defaults'] : array();
	$units = array();
	foreach ( preg_split( '/[\r\n,]+/', isset( $df['units'] ) ? (string) $df['units'] : '' ) as $u ) {
		$u = pq_s_line( $u, 24 );
		if ( '' !== $u && ! in_array( $u, $units, true ) ) { $units[] = $u; }
	}
	if ( ! $units ) { $units = preg_split( '/\r?\n/', $d['defaults']['units'] ); }
	$unit = pq_s_line( isset( $df['unit'] ) ? $df['unit'] : '', 24 );
	$out['defaults'] = array(
		'valid_days' => min( 3650, max( 0, isset( $df['valid_days'] ) ? absint( $df['valid_days'] ) : 14 ) ),
		'due_days'   => min( 3650, max( 0, isset( $df['due_days'] ) ? absint( $df['due_days'] ) : 7 ) ),
		'unit'       => in_array( $unit, $units, true ) ? $unit : $units[0],
		'units'      => implode( "\n", array_slice( $units, 0, 40 ) ),
	);

	// Number and date format.
	$f   = isset( $in['format'] ) && is_array( $in['format'] ) ? $in['format'] : array();
	$sep = array( '.', ',', ' ', "'", '' );
	$out['format'] = array(
		'decimals' => min( 4, max( 0, isset( $f['decimals'] ) ? absint( $f['decimals'] ) : 2 ) ),
		'dec_sep'  => pq_s_enum( isset( $f['dec_sep'] ) ? $f['dec_sep'] : '.', array( '.', ',' ), '.' ),
		'thou_sep' => pq_s_enum( isset( $f['thou_sep'] ) ? $f['thou_sep'] : ',', $sep, ',' ),
		'date'     => pq_s_enum( isset( $f['date'] ) ? $f['date'] : 'j M Y', array( 'j M Y', 'd/m/Y', 'm/d/Y', 'Y/m/d', 'Y-m-d', 'F j, Y' ), 'j M Y' ),
	);

	// Currencies.
	$cur   = array();
	$codes = array();
	if ( isset( $in['currencies'] ) && is_array( $in['currencies'] ) ) {
		foreach ( array_slice( array_values( $in['currencies'] ), 0, 20 ) as $r ) {
			$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', isset( $r['code'] ) ? (string) $r['code'] : '' ) );
			$code = substr( $code, 0, 5 );
			if ( '' === $code || in_array( $code, $codes, true ) ) { continue; }
			$codes[] = $code;
			$cur[]   = array(
				'code'     => $code,
				'symbol'   => pq_s_line( isset( $r['symbol'] ) ? $r['symbol'] : $code, 8 ),
				'position' => pq_s_enum( isset( $r['position'] ) ? $r['position'] : 'before', array( 'before', 'after' ), 'before' ),
				'space'    => pq_s_bool( isset( $r['space'] ) ? $r['space'] : 0 ),
				'decimals' => ( isset( $r['decimals'] ) && '' !== (string) $r['decimals'] ) ? min( 4, absint( $r['decimals'] ) ) : '',
				'tax_label' => pq_s_line( isset( $r['tax_label'] ) ? $r['tax_label'] : '', 30 ),
				'tax_rate'  => ( isset( $r['tax_rate'] ) && '' !== trim( (string) $r['tax_rate'] ) ) ? (string) min( 100, max( 0, pq_num( $r['tax_rate'] ) ) ) : '',
				'word'      => pq_s_line( isset( $r['word'] ) ? $r['word'] : '', 40 ),
				'subword'   => pq_s_line( isset( $r['subword'] ) ? $r['subword'] : '', 30 ),
			);
		}
	}
	if ( ! $cur ) { $cur = $d['currencies']; $codes = wp_list_pluck( $cur, 'code' ); }
	$out['currencies'] = $cur;
	$out['currency']   = pq_s_enum( isset( $in['currency'] ) ? strtoupper( (string) $in['currency'] ) : '', $codes, $codes[0] );

	// Tax.
	$t = isset( $in['tax'] ) && is_array( $in['tax'] ) ? $in['tax'] : array();
	$out['tax'] = array(
		'label'        => pq_s_line( isset( $t['label'] ) ? $t['label'] : 'Tax', 30 ),
		'rate'         => min( 100, max( 0, pq_num( isset( $t['rate'] ) ? $t['rate'] : 0 ) ) ),
		'mode'         => pq_s_enum( isset( $t['mode'] ) ? $t['mode'] : 'exclusive', array( 'exclusive', 'inclusive', 'none' ), 'exclusive' ),
		'tax_shipping' => pq_s_bool( isset( $t['tax_shipping'] ) ? $t['tax_shipping'] : 0 ),
	);

	// Default terms blocks, each with the document types it starts on.
	$out['blocks'] = pq_sanitize_blocks( isset( $in['blocks'] ) ? $in['blocks'] : array(), true );

	// Show / hide defaults per type. Unticked boxes are not posted, so a missing key means hidden.
	$out['display'] = array();
	$disp = isset( $in['display'] ) && is_array( $in['display'] ) ? $in['display'] : array();
	foreach ( array_keys( pq_types() ) as $ty ) {
		foreach ( array_keys( pq_display_fields() ) as $k ) {
			$out['display'][ $ty ][ $k ] = pq_s_bool( isset( $disp[ $ty ][ $k ] ) ? $disp[ $ty ][ $k ] : 0 );
		}
	}

	// Bank accounts.
	$banks = array();
	if ( isset( $in['banks'] ) && is_array( $in['banks'] ) ) {
		foreach ( array_slice( array_values( $in['banks'] ), 0, 10 ) as $r ) {
			$row = array(
				'id'           => preg_replace( '/[^a-z0-9_]/', '', strtolower( isset( $r['id'] ) ? (string) $r['id'] : '' ) ),
				'account_name' => pq_s_line( isset( $r['account_name'] ) ? $r['account_name'] : '', 120 ),
				'bank'         => pq_s_line( isset( $r['bank'] ) ? $r['bank'] : '', 120 ),
				'account_no'   => pq_s_line( isset( $r['account_no'] ) ? $r['account_no'] : '', 80 ),
				'branch'       => pq_s_line( isset( $r['branch'] ) ? $r['branch'] : '', 120 ),
				'swift'        => pq_s_line( isset( $r['swift'] ) ? $r['swift'] : '', 40 ),
				'show'         => pq_s_bool( isset( $r['show'] ) ? $r['show'] : 0 ),
			);
			if ( '' === $row['bank'] && '' === $row['account_no'] && '' === $row['account_name'] ) { continue; }
			if ( '' === $row['id'] ) { $row['id'] = 'bank_' . strtolower( wp_generate_password( 8, false, false ) ); }
			$banks[] = $row;
		}
	}
	$out['banks'] = $banks;

	// Labels.
	$out['labels'] = array();
	$in_labels     = isset( $in['labels'] ) && is_array( $in['labels'] ) ? $in['labels'] : array();
	foreach ( pq_default_labels() as $k => $def ) {
		$v = isset( $in_labels[ $k ] ) ? pq_s_line( $in_labels[ $k ], 80 ) : $def;
		$out['labels'][ $k ] = ( '' === $v && 'col_no' !== $k ) ? $def : $v;
	}

	// Footer text per type.
	$out['footer'] = array();
	foreach ( array_keys( pq_types() ) as $ty ) {
		$out['footer'][ $ty ] = pq_s_text( isset( $in['footer'][ $ty ] ) ? $in['footer'][ $ty ] : '', 400 );
	}

	// Template.
	$tp = isset( $in['template'] ) && is_array( $in['template'] ) ? $in['template'] : array();
	$out['template'] = array(
		'accent'         => pq_s_color( isset( $tp['accent'] ) ? $tp['accent'] : '', $d['template']['accent'] ),
		'font'           => pq_s_enum( isset( $tp['font'] ) ? $tp['font'] : 'arial', array_keys( pq_fonts() ), 'arial' ),
		'size'           => pq_s_enum( isset( $tp['size'] ) ? $tp['size'] : 'regular', array( 'compact', 'regular', 'large' ), 'regular' ),
		'logo_height'    => min( 160, max( 20, isset( $tp['logo_height'] ) ? absint( $tp['logo_height'] ) : 90 ) ),
		'img_size'       => min( 120, max( 24, isset( $tp['img_size'] ) ? absint( $tp['img_size'] ) : 48 ) ),
		'custom_css'     => pq_clean_css( isset( $tp['custom_css'] ) ? $tp['custom_css'] : '' ),
	);

	// Email.
	$em = isset( $in['email'] ) && is_array( $in['email'] ) ? $in['email'] : array();
	$out['email'] = array(
		'subject' => pq_s_line( isset( $em['subject'] ) ? $em['subject'] : $d['email']['subject'], 200 ),
		'body'    => pq_s_text( isset( $em['body'] ) ? $em['body'] : $d['email']['body'], 4000 ),
	);

	$g = isset( $in['general'] ) && is_array( $in['general'] ) ? $in['general'] : array();
	$out['general'] = array( 'delete_on_uninstall' => pq_s_bool( isset( $g['delete_on_uninstall'] ) ? $g['delete_on_uninstall'] : 0 ) );

	return $out;
}

function pq_clean_css( $css ) {
	$css = is_scalar( $css ) ? (string) $css : '';
	$css = wp_strip_all_tags( $css );
	$css = preg_replace( '/@import[^;]*;?/i', '', $css );
	$css = preg_replace( '/expression\s*\(|javascript:|behavior\s*:|-moz-binding/i', '', $css );
	return trim( substr( $css, 0, 8000 ) );
}

/** Terms blocks. With $with_on, keep which document types each block starts on (settings only). */
function pq_sanitize_blocks( $blocks, $with_on = false ) {
	$out = array();
	if ( is_array( $blocks ) ) {
		foreach ( array_slice( array_values( $blocks ), 0, 12 ) as $b ) {
			if ( ! is_array( $b ) ) { continue; }
			$title = pq_s_line( isset( $b['title'] ) ? $b['title'] : '', 120 );
			$body  = pq_s_text( isset( $b['body'] ) ? $b['body'] : '', 3000 );
			if ( '' === $title && '' === $body ) { continue; }
			$row = array(
				'title' => $title,
				'body'  => $body,
				'style' => pq_s_enum( isset( $b['style'] ) ? $b['style'] : '', array( '', 'highlight' ), '' ),
			);
			if ( $with_on ) {
				$row['on'] = pq_s_enum( isset( $b['on'] ) ? $b['on'] : '', array_merge( array( '', 'all' ), array_keys( pq_types() ) ), '' );
			}
			$out[] = $row;
		}
	}
	return $out;
}

/** Where a terms block can start. '' means every type except delivery notes. */
function pq_block_on_options() {
	$o = array( '' => 'Quotations and invoices', 'all' => 'Every document' );
	foreach ( pq_types() as $k => $v ) { $o[ $k ] = $v . ' only'; }
	return $o;
}

/** The default terms a new document of this type starts with. */
function pq_blocks_for_type( $type, $s = null ) {
	$s   = $s ? $s : pq_settings();
	$out = array();
	foreach ( $s['blocks'] as $b ) {
		$on = isset( $b['on'] ) ? $b['on'] : '';
		if ( 'all' === $on || $type === $on || ( '' === $on && 'delivery' !== $type ) ) {
			$out[] = array( 'title' => $b['title'], 'body' => $b['body'], 'style' => $b['style'] );
		}
	}
	return $out;
}

function pq_fonts() {
	return array(
		'arial'    => array( 'Arial (classic)', 'Arial, "Helvetica Neue", Helvetica, sans-serif' ),
		'sans'     => array( 'Modern sans', '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif' ),
		'humanist' => array( 'Humanist sans', '"Gill Sans", "Gill Sans MT", Calibri, "Trebuchet MS", sans-serif' ),
		'serif'    => array( 'Serif', 'Georgia, Cambria, "Times New Roman", serif' ),
		'classic'  => array( 'Classic serif', '"Palatino Linotype", Palatino, "Book Antiqua", Georgia, serif' ),
	);
}

/* ------------------------------------------------------------------ */
/* Currency, dates, quantities                                         */
/* ------------------------------------------------------------------ */

function pq_currency( $code, $s = null ) {
	$s = $s ? $s : pq_settings();
	foreach ( $s['currencies'] as $c ) {
		if ( strtoupper( $code ) === $c['code'] ) { return $c; }
	}
	return array( 'code' => strtoupper( $code ), 'symbol' => strtoupper( $code ), 'position' => 'before', 'space' => 1 );
}

/** Decimal places for a currency: its own setting, else the general one. */
function pq_decimals( $code, $s = null ) {
	$s = $s ? $s : pq_settings();
	foreach ( $s['currencies'] as $c ) {
		if ( strtoupper( (string) $code ) === $c['code'] && isset( $c['decimals'] ) && '' !== (string) $c['decimals'] ) { return (int) $c['decimals']; }
	}
	return (int) $s['format']['decimals'];
}

/** Tax for a currency: its own name and rate if set, else the general tax. */
function pq_tax_for_currency( $code, $s = null ) {
	$s   = $s ? $s : pq_settings();
	$tax = array( 'label' => $s['tax']['label'], 'rate' => (float) $s['tax']['rate'], 'mode' => $s['tax']['mode'] );
	foreach ( $s['currencies'] as $c ) {
		if ( strtoupper( (string) $code ) !== $c['code'] ) { continue; }
		if ( isset( $c['tax_rate'] ) && '' !== (string) $c['tax_rate'] ) { $tax['rate'] = (float) $c['tax_rate']; }
		if ( isset( $c['tax_label'] ) && '' !== (string) $c['tax_label'] ) { $tax['label'] = $c['tax_label']; }
	}
	return $tax;
}

/** 0 to 999 999 999 999 in English words. */
function pq_words( $n ) {
	$n     = (int) $n;
	$ones  = array( '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen' );
	$tens  = array( '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety' );
	if ( 0 === $n ) { return 'Zero'; }
	$chunk = function ( $x ) use ( $ones, $tens ) {
		$w = array();
		if ( $x >= 100 ) { $w[] = $ones[ intdiv( $x, 100 ) ] . ' Hundred'; $x %= 100; }
		if ( $x >= 20 ) { $w[] = $tens[ intdiv( $x, 10 ) ] . ( $x % 10 ? '-' . $ones[ $x % 10 ] : '' ); }
		elseif ( $x > 0 ) { $w[] = $ones[ $x ]; }
		return implode( ' ', $w );
	};
	$out = array();
	foreach ( array( 1000000000 => 'Billion', 1000000 => 'Million', 1000 => 'Thousand' ) as $size => $name ) {
		if ( $n >= $size ) { $out[] = $chunk( intdiv( $n, $size ) ) . ' ' . $name; $n %= $size; }
	}
	if ( $n > 0 ) { $out[] = $chunk( $n ); }
	return implode( ' ', $out );
}

/** "US Dollars One Thousand Two Hundred and Cents Fifty Only". */
function pq_amount_in_words( $amount, $code, $s = null ) {
	$s     = $s ? $s : pq_settings();
	$dec   = pq_decimals( $code, $s );
	$cur   = pq_currency( $code, $s );
	$amt   = round( abs( (float) $amount ), $dec );
	$whole = (int) floor( $amt );
	$frac  = $dec > 0 ? (int) round( ( $amt - $whole ) * pow( 10, $dec ) ) : 0;
	$word  = ! empty( $cur['word'] ) ? $cur['word'] : $cur['code'];
	$out   = $word . ' ' . pq_words( $whole );
	if ( $frac > 0 ) {
		$out .= ' and ' . ( ! empty( $cur['subword'] ) ? $cur['subword'] . ' ' : '' ) . pq_words( $frac );
	}
	return $out . ' Only';
}

function pq_number_format( $n, $s = null, $dec = null ) {
	$s   = $s ? $s : pq_settings();
	$dec = null === $dec ? (int) $s['format']['decimals'] : (int) $dec;
	return number_format( (float) $n, $dec, $s['format']['dec_sep'], $s['format']['thou_sep'] );
}

function pq_money( $amount, $code = '', $s = null ) {
	$s    = $s ? $s : pq_settings();
	$code = $code ? $code : $s['currency'];
	$cur  = pq_currency( $code, $s );
	$neg  = ( (float) $amount ) < -0.0000001;
	$txt  = pq_number_format( abs( (float) $amount ), $s, pq_decimals( $code, $s ) );
	$sp   = ! empty( $cur['space'] ) ? ' ' : '';
	$out  = ( 'after' === $cur['position'] ) ? $txt . $sp . $cur['symbol'] : $cur['symbol'] . $sp . $txt;
	return ( $neg ? '-' : '' ) . $out;
}

function pq_fmt_date( $ymd, $s = null ) {
	$s = $s ? $s : pq_settings();
	if ( ! pq_s_date( $ymd ) ) { return ''; }
	$dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $ymd . ' 12:00:00', wp_timezone() );
	return $dt ? wp_date( $s['format']['date'], $dt->getTimestamp(), wp_timezone() ) : $ymd;
}

function pq_today() {
	return wp_date( 'Y-m-d' );
}

function pq_date_plus( $days, $from = '' ) {
	$dt = $from ? DateTime::createFromFormat( 'Y-m-d', $from, wp_timezone() ) : new DateTime( 'now', wp_timezone() );
	if ( ! $dt ) { $dt = new DateTime( 'now', wp_timezone() ); }
	$dt->modify( '+' . (int) $days . ' days' );
	return $dt->format( 'Y-m-d' );
}

function pq_qty( $q ) {
	$q = (float) $q;
	$t = rtrim( rtrim( number_format( $q, 3, '.', '' ), '0' ), '.' );
	return '' === $t ? '0' : $t;
}

function pq_units_list( $s = null ) {
	$s = $s ? $s : pq_settings();
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', $s['defaults']['units'] ) ) ) );
}

/* ------------------------------------------------------------------ */
/* Numbering                                                           */
/* ------------------------------------------------------------------ */

function pq_number_exists( $number, $exclude = 0 ) {
	global $wpdb;
	$id = $wpdb->get_var( $wpdb->prepare(
		"SELECT p.ID FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
		 WHERE m.meta_key = '_pq_number' AND m.meta_value = %s AND p.post_type = %s
		 AND p.post_status NOT IN ('trash','auto-draft') AND p.ID <> %d LIMIT 1",
		$number, PQ_CPT, (int) $exclude
	) );
	return (bool) $id;
}

/** Next free number for a type. Returns array( 'PMT-14617', 14617 ). */
function pq_next_number( $type, $s = null ) {
	$s   = $s ? $s : pq_settings();
	$n   = $s['numbering'][ $type ];
	$num = max( 1, (int) $n['next'] );
	$guard = 0;
	do {
		$number = $n['prefix'] . ( $n['pad'] ? str_pad( (string) $num, (int) $n['pad'], '0', STR_PAD_LEFT ) : (string) $num );
		$taken  = pq_number_exists( $number );
		if ( $taken ) { $num++; }
		$guard++;
	} while ( $taken && $guard < 500 );
	return array( $number, $num );
}

/** Move the counter past a number that was just used. */
function pq_bump_number( $type, $used ) {
	$raw = get_option( 'pq_settings', array() );
	$raw = is_array( $raw ) ? $raw : array();
	$cur = pq_settings();
	$next = max( (int) $cur['numbering'][ $type ]['next'], (int) $used + 1 );
	if ( ! isset( $raw['numbering'] ) || ! is_array( $raw['numbering'] ) ) { $raw['numbering'] = array(); }
	$raw['numbering'][ $type ] = array(
		'prefix' => $cur['numbering'][ $type ]['prefix'],
		'next'   => $next,
		'pad'    => $cur['numbering'][ $type ]['pad'],
	);
	update_option( 'pq_settings', $raw, false );
	pq_settings( true );
}

/* ------------------------------------------------------------------ */
/* Totals                                                              */
/* ------------------------------------------------------------------ */

/**
 * Work out line totals, discount, tax, grand total, paid and balance.
 * Every step is rounded to the configured decimals so printed figures always add up.
 */
function pq_calculate( $doc, $s = null ) {
	$s   = $s ? $s : pq_settings();
	$dec = pq_decimals( isset( $doc['currency'] ) ? $doc['currency'] : $s['currency'], $s );

	$sub   = 0.0;
	$lines = array();
	foreach ( $doc['items'] as $i => $it ) {
		$qty   = max( 0, pq_num( $it['qty'] ) );
		$price = max( 0, pq_num( $it['price'] ) );
		$gross = $qty * $price;
		if ( isset( $it['disc_type'] ) && 'fixed' === $it['disc_type'] ) {
			$line = round( $gross - min( $gross, max( 0, pq_num( $it['discount'] ) ) ), $dec );
		} else {
			$line = round( $gross * ( 1 - min( 100, max( 0, pq_num( $it['discount'] ) ) ) / 100 ), $dec );
		}
		$lines[ $i ] = $line;
		$sub += $line;
	}
	$sub = round( $sub, $dec );

	$dtype = isset( $doc['discount']['type'] ) && 'fixed' === $doc['discount']['type'] ? 'fixed' : 'percent';
	$dval  = max( 0, pq_num( isset( $doc['discount']['value'] ) ? $doc['discount']['value'] : 0 ) );
	if ( 'percent' === $dtype ) {
		$disc_amt = round( $sub * min( 100, $dval ) / 100, $dec );
	} else {
		$disc_amt = min( $sub, round( $dval, $dec ) );
	}

	$ship = round( max( 0, pq_num( isset( $doc['shipping']['amount'] ) ? $doc['shipping']['amount'] : 0 ) ), $dec );
	$net  = round( $sub - $disc_amt, $dec );

	$rate = max( 0, pq_num( isset( $doc['tax']['rate'] ) ? $doc['tax']['rate'] : 0 ) );
	$mode = isset( $doc['tax']['mode'] ) ? $doc['tax']['mode'] : 'exclusive';
	$base = $net + ( ! empty( $s['tax']['tax_shipping'] ) ? $ship : 0 );

	if ( 'none' === $mode || $rate <= 0 ) {
		$tax   = 0.0;
		$total = round( $net + $ship, $dec );
	} elseif ( 'inclusive' === $mode ) {
		$tax   = round( $base - $base / ( 1 + $rate / 100 ), $dec );
		$total = round( $net + $ship, $dec );
	} else {
		$tax   = round( $base * $rate / 100, $dec );
		$total = round( $net + $ship + $tax, $dec );
	}

	$paid = 0.0;
	if ( ! empty( $doc['payments'] ) && is_array( $doc['payments'] ) ) {
		foreach ( $doc['payments'] as $p ) { $paid += max( 0, pq_num( $p['amount'] ) ); }
	}
	$paid    = round( $paid, $dec );
	$balance = round( $total - $paid, $dec );

	return array(
		'lines'    => $lines,
		'subtotal' => $sub,
		'discount' => $disc_amt,
		'shipping' => $ship,
		'net'      => $net,
		'tax'      => $tax,
		'total'    => $total,
		'paid'     => $paid,
		'balance'  => $balance,
	);
}
