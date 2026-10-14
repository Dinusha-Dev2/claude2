<?php
/**
 * The document template. One renderer feeds the live preview, the public link,
 * print / save as PDF and email, so they always match.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pq_doc_label( $type, $s = null ) {
	$s = $s ? $s : pq_settings();
	$k = 'doc_' . $type;
	return isset( $s['labels'][ $k ] ) && '' !== $s['labels'][ $k ] ? $s['labels'][ $k ] : pq_types()[ $type ];
}

function pq_document_css( $s ) {
	$t     = $s['template'];
	$fonts = pq_fonts();
	$font  = isset( $fonts[ $t['font'] ] ) ? $fonts[ $t['font'] ][1] : $fonts['arial'][1];
	$sizes = array( 'compact' => 11, 'regular' => 12, 'large' => 13 );
	$size  = isset( $sizes[ $t['size'] ] ) ? $sizes[ $t['size'] ] : 12;

	$css  = ':root{--pq-accent:' . $t['accent'] . ';--pq-ink:#111;--pq-muted:#666;--pq-line:#dcdcdc;--pq-bar:#efefef;--pq-font:' . $font . ';--pq-size:' . $size . 'px;--pq-img:' . (int) $t['img_size'] . 'px}';
	$css .= '*{box-sizing:border-box}html,body{margin:0;padding:0}';
	$css .= 'body.pqd-body{background:#e6e6e6;color:var(--pq-ink);font-family:var(--pq-font);font-size:var(--pq-size);line-height:1.45;-webkit-print-color-adjust:exact;print-color-adjust:exact;font-variant-numeric:tabular-nums}';
	$css .= 'body.pqd-public{padding:72px 0 36px}body.pqd-preview{padding:12px 0}';

	// Without JavaScript the document simply flows as one sheet. With it, pages are built (see the script).
	$css .= '.pqd-flow{width:210mm;margin:0 auto;background:#fff;padding:14mm}';
	$css .= 'html.pqd-js .pqd-flow{display:none}';

	// A4 pages, always.
	$css .= '.pqd-pages{display:flex;flex-direction:column;align-items:center;gap:16px}';
	$css .= '.pqd-page{position:relative;width:210mm;height:296.5mm;padding:11mm 14mm 0;background:#fff;overflow:hidden;flex:none;box-shadow:0 1px 2px rgba(0,0,0,.08),0 6px 20px rgba(0,0,0,.08)}';
	$css .= 'body.pqd-preview .pqd-page{box-shadow:0 1px 3px rgba(0,0,0,.18)}';
	$css .= '.pqd-pbody{height:calc(296.5mm - 11mm - 15mm);overflow:hidden}';
	$css .= '.pqd-pfoot{position:absolute;left:14mm;right:14mm;bottom:7mm;display:flex;justify-content:space-between;gap:16px;padding-top:6px;border-top:1px solid var(--pq-line);color:var(--pq-muted);font-size:.88em}';
	$css .= '.pqd-pfoot span:last-child{color:var(--pq-ink);white-space:nowrap}';

	// First-page header: logo left, document title right, one thin rule.
	$css .= '.pqd-head{display:flex;justify-content:space-between;align-items:center;gap:24px;padding-bottom:12px;border-bottom:1px solid #9a9a9a}';
	$css .= '.pqd-logo{display:block}.pqd-brandname{font-size:1.7em;font-weight:700;letter-spacing:.01em}';
	$css .= '.pqd-doc{text-align:right}.pqd-doc h1{margin:0;font-size:1.45em;line-height:1.2;font-weight:700;color:var(--pq-accent)}';
	$css .= '.pqd-stamp{display:inline-block;margin-top:6px;padding:1px 8px;border:1px solid var(--pq-ink);font-weight:700;font-size:.9em}';

	// Continuation header (pages 2 onward).
	$css .= '.pqd-chead{display:flex;justify-content:space-between;align-items:center;gap:16px;padding-bottom:7px;margin-bottom:6px;border-bottom:1px solid #9a9a9a}';
	$css .= '.pqd-chead img{display:block}.pqd-chead-name{font-weight:700;font-size:1.1em}';
	$css .= '.pqd-chead-r{text-align:right;line-height:1.35}.pqd-chead-r strong{display:block}.pqd-chead-r span{color:var(--pq-muted)}';

	// Parties and summary: grey label bars, plain "Label: value" rows.
	$css .= '.pqd-parties{display:grid;grid-template-columns:1.35fr 1fr;gap:0 32px;margin-top:14px;align-items:start}';
	$css .= '.pqd-grp+.pqd-grp{margin-top:14px}';
	$css .= '.pqd-bar{margin:0;padding:2px 8px;background:var(--pq-bar);font-weight:700}.pqd-bar span{margin-left:6px;font-weight:400}';
	$css .= '.pqd-kv{margin:4px 0 0;padding:0 8px;display:grid;grid-template-columns:108px 1fr;gap:1px 8px}.pqd-kv dt{white-space:nowrap}';
	$css .= '.pqd-kv dt{color:var(--pq-muted)}.pqd-kv dd{margin:0}';
	$css .= '.pqd-sum .pqd-kv{grid-template-columns:1fr auto}.pqd-sum .pqd-kv dd{text-align:right}';
	$css .= '.pqd-sum .is-strong{font-weight:700;color:var(--pq-ink)}.pqd-sum .is-first{margin-top:5px;padding-top:5px;border-top:1px solid var(--pq-line)}';

	// Items.
	$css .= '.pqd-items{width:100%;border-collapse:collapse;margin-top:16px}';
	$css .= '.pqd-items thead th{padding:5px 8px;background:var(--pq-bar);color:var(--pq-ink);font-weight:700;text-align:left;white-space:nowrap}';
	$css .= '.pqd-items td{padding:8px;border-bottom:1px solid #e6e6e6;vertical-align:top}';
	$css .= '.pqd-items .c-no{width:30px}.pqd-items tbody .c-no{color:var(--pq-muted)}.pqd-items .c-img{width:calc(var(--pq-img) + 16px)}';
	$css .= '.pqd-items .c-img img,.pqd-items .c-img .pqd-noimg{display:block;width:var(--pq-img);height:var(--pq-img);object-fit:contain;border:1px solid var(--pq-line);background:#fff}';
	$css .= '.pqd-items .c-qty{width:72px;text-align:right;white-space:nowrap}.pqd-items .c-price{width:92px;text-align:right}.pqd-items .c-disc{width:54px;text-align:right}.pqd-items .c-amt{width:100px;text-align:right}';
	$css .= '.pqd-items thead th.c-qty,.pqd-items thead th.c-price,.pqd-items thead th.c-disc,.pqd-items thead th.c-amt{text-align:right}';
	$css .= '.pqd-item-name{font-weight:700}.pqd-item-desc{margin-top:1px;color:var(--pq-muted)}.pqd-item-sku{margin-top:1px;color:var(--pq-muted);font-size:.92em}';
	$css .= '.pqd-unit{display:block;color:var(--pq-muted);font-size:.92em}.pqd-empty{padding:26px 8px;text-align:center;color:var(--pq-muted)}';

	// Totals: right aligned, the final amount double underlined.
	$css .= '.pqd-sumrow{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;margin-top:12px}';
	$css .= '.pqd-payments{flex:1}.pqd-payments h3{margin:0 0 3px;font-size:1em;font-weight:700}';
	$css .= '.pqd-payments table{border-collapse:collapse;width:100%;max-width:320px}.pqd-payments td{padding:2px 0;border-bottom:1px solid var(--pq-line)}.pqd-payments td:last-child{text-align:right}';
	$css .= '.pqd-totals{margin-left:auto;border-collapse:collapse}';
	$css .= '.pqd-totals td{padding:2px 0 2px 24px;text-align:right}.pqd-totals td:last-child{min-width:104px;padding-right:8px}';
	$css .= '.pqd-totals .t-total td{padding-top:6px;font-weight:700}.pqd-totals .t-total td:last-child{border-bottom:3px double var(--pq-ink);padding-bottom:1px}';
	$css .= '.pqd-totals .t-due td{padding-top:6px;font-weight:700}';

	// Notes, bank details, signatures, footer.
	$css .= '.pqd-note{margin-top:10px}.pqd-note h3{margin:0 0 1px;font-size:1em;font-weight:700}.pqd-note p{margin:0}';
	$css .= '.pqd-note.is-hl{padding:6px 10px;background:var(--pq-bar);border-left:3px solid var(--pq-ink)}';
	$css .= '.pqd-banks{margin-top:14px}';
	$css .= '.pqd-bankgrid{display:grid;grid-template-columns:1fr 1fr;gap:8px 28px;padding:0 8px;margin-top:4px}';
	$css .= '.pqd-bank b{display:block;margin-bottom:1px}.pqd-bank dl{margin:0;display:grid;grid-template-columns:auto 1fr;gap:0 10px}.pqd-bank dt{color:var(--pq-muted)}.pqd-bank dd{margin:0}';
	$css .= '.pqd-sign{display:grid;grid-template-columns:1fr 1fr;gap:48px;margin-top:40px}.pqd-sign div{padding-top:6px;border-top:1px solid var(--pq-ink);color:var(--pq-muted)}';
	$css .= '.pqd-foot{margin-top:14px;text-align:center;color:var(--pq-muted);font-size:.9em}';

	// Public toolbar.
	$css .= '.pqd-toolbar{position:fixed;top:0;left:0;right:0;z-index:5;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 20px;background:#fff;border-bottom:1px solid var(--pq-line);font-family:var(--pq-font);font-size:14px}';
	$css .= '.pqd-toolbar strong{font-weight:700}.pqd-toolbar small{margin-left:10px;color:var(--pq-muted);font-size:12px}';
	$css .= '.pqd-toolbar button{padding:8px 16px;border:1px solid var(--pq-ink);border-radius:3px;background:var(--pq-ink);color:#fff;font:inherit;font-weight:600;cursor:pointer}.pqd-toolbar button:focus-visible{outline:2px solid var(--pq-ink);outline-offset:2px}';

	// Small screens: the A4 page is scaled to fit; keep the bar slim.
	$css .= '@media (max-width:560px){body.pqd-public{padding-top:56px}.pqd-toolbar{padding:8px 12px;font-size:13px}.pqd-toolbar small{display:none}.pqd-toolbar button{padding:7px 12px;font-size:13px}}';

	// Print: A4, no margins (each page carries its own), one sheet per page.
	$css .= '@page{size:A4;margin:14mm}';
	$css .= '@media print{body.pqd-body,body.pqd-public,body.pqd-preview{background:#fff;padding:0}.pqd-toolbar{display:none}.pqd-flow{width:auto;margin:0;padding:0}';
	$css .= '.pqd-pages{display:block;gap:0;zoom:1 !important}.pqd-page{box-shadow:none !important;break-after:page;page-break-after:always}.pqd-page:last-child{break-after:auto;page-break-after:auto}}';

	if ( ! empty( $t['custom_css'] ) ) { $css .= "\n" . $t['custom_css']; }
	return $css;
}

function pq_lines( $text ) {
	return nl2br( esc_html( $text ) );
}

/** Replace {days}, {valid_until}, {due_date}, {number} in footer text. */
function pq_footer_text( $doc, $s ) {
	$tpl  = isset( $s['footer'][ $doc['type'] ] ) ? $s['footer'][ $doc['type'] ] : '';
	$days = '';
	if ( $doc['issue_date'] && $doc['expiry_date'] ) {
		$days = (string) max( 0, (int) round( ( strtotime( $doc['expiry_date'] ) - strtotime( $doc['issue_date'] ) ) / DAY_IN_SECONDS ) );
	}
	$until = pq_fmt_date( $doc['expiry_date'], $s );
	return strtr( $tpl, array(
		'{days}'        => $days,
		'{valid_until}' => $until,
		'{due_date}'    => $until,
		'{number}'      => $doc['number'],
	) );
}

function pq_sample_placeholder() {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96"><rect width="96" height="96" fill="#f1f3f5"/><path d="M18 66l18-22 14 16 10-12 18 18z" fill="#cfd5dc"/><circle cx="64" cy="32" r="7" fill="#cfd5dc"/></svg>';
	return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

/** Logo URL with a display size worked out up front, so page layout never waits for the image to load. */
function pq_logo_data( $s, $max_h ) {
	$id = ! empty( $s['company']['logo_id'] ) ? (int) $s['company']['logo_id'] : 0;
	if ( ! $id ) { return null; }
	$src = wp_get_attachment_image_src( $id, 'large' );
	if ( ! $src || empty( $src[1] ) || empty( $src[2] ) ) { return null; }
	$h = min( (int) $src[2], (int) $max_h );
	$w = (int) round( $src[1] * $h / $src[2] );
	if ( $w > 420 ) { $h = (int) round( $h * 420 / $w ); $w = 420; }
	return array( 'url' => $src[0], 'w' => max( 1, $w ), 'h' => max( 1, $h ) );
}

/**
 * The document as flat blocks inside #pqd-root. Without JavaScript they flow as one sheet; the script in
 * pq_paginator_js() lays them onto A4 pages with repeated headings and "Page n / N".
 * $doc must come from pq_doc_prepare().
 */
function pq_document_body( $doc, $s, $ctx = 'public' ) {
	$t    = $s['template'];
	$L    = $s['labels'];
	$c    = $doc['calc'];
	$cur  = $doc['currency'];
	$co   = $s['company'];
	$cust = $doc['customer'];
	$is_quote = ( 'quotation' === $doc['type'] );
	$m    = function ( $v ) use ( $cur, $s ) { return esc_html( pq_money( $v, $cur, $s ) ); };

	$show_disc = false;
	if ( 'always' === $t['show_discount'] ) {
		$show_disc = true;
	} elseif ( 'auto' === $t['show_discount'] ) {
		foreach ( $doc['items'] as $it ) { if ( pq_num( $it['discount'] ) > 0 ) { $show_disc = true; break; } }
	}
	$show_img  = ! empty( $t['show_image'] );
	$title     = pq_doc_label( $doc['type'], $s ) . ' ' . $doc['number'];
	$logo      = pq_logo_data( $s, $t['logo_height'] );
	$logo_sm   = pq_logo_data( $s, 28 );
	$customer  = pq_customer_label( $cust );

	ob_start();
	echo '<div id="pqd-root" class="pqd-root" data-meta="' . esc_attr( wp_json_encode( array( 'title' => $title, 'company' => $co['name'], 'who' => $customer, 'page' => 'Page' ) ) ) . '">';
	echo '<div class="pqd-flow">';

	/* Header: logo (or name) on the left, document title on the right */
	echo '<header class="pqd-head"><div>';
	if ( $logo ) {
		echo '<img class="pqd-logo" src="' . esc_url( $logo['url'] ) . '" width="' . (int) $logo['w'] . '" height="' . (int) $logo['h'] . '" alt="' . esc_attr( $co['name'] ) . '">';
	} else {
		echo '<div class="pqd-brandname">' . esc_html( $co['name'] ) . '</div>';
	}
	echo '</div><div class="pqd-doc"><h1>' . esc_html( pq_doc_label( $doc['type'], $s ) ) . '</h1>';
	if ( 'paid' === $doc['status'] && ! $is_quote ) { echo '<div class="pqd-stamp">' . esc_html( $L['paid_stamp'] ) . '</div>'; }
	echo '</div></header>';

	/* Business and customer on the left, summary on the right */
	$kv = function ( $label, $html ) { return '<dt>' . esc_html( $label ) . ':</dt><dd>' . $html . '</dd>'; };

	echo '<section class="pqd-parties"><div class="pqd-left">';

	echo '<div class="pqd-grp"><p class="pqd-bar">' . esc_html( $L['from'] ) . ':<span>' . esc_html( $co['name'] ) . '</span></p><dl class="pqd-kv">';
	if ( '' !== $co['address'] ) { echo $kv( $L['address'], pq_lines( $co['address'] ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
	foreach ( $co['details'] as $row ) {
		if ( '' !== trim( $row['value'] ) ) { echo $kv( '' !== trim( $row['label'] ) ? rtrim( trim( $row['label'] ), ':' ) : '', esc_html( $row['value'] ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( '' !== $co['phone'] ) { echo $kv( $L['phone'], esc_html( $co['phone'] ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
	if ( '' !== $co['email'] ) { echo $kv( $L['email'], esc_html( $co['email'] ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
	if ( '' !== $co['website'] ) { echo $kv( $L['web'], esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $co['website'] ) ) ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</dl></div>';

	echo '<div class="pqd-grp"><p class="pqd-bar">' . esc_html( $L['bill_to'] ) . ':';
	if ( pq_customer_has_data( $cust ) ) {
		$main = '' !== $cust['company'] ? $cust['company'] : $cust['name'];
		echo '<span>' . esc_html( $main ) . '</span></p><dl class="pqd-kv">';
		if ( '' !== $cust['company'] && '' !== $cust['name'] ) { echo $kv( $L['attn'], esc_html( $cust['name'] ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
		$addr = array();
		if ( '' !== $cust['address'] ) { $addr[] = pq_lines( $cust['address'] ); }
		$place = array_filter( array( trim( $cust['city'] . ' ' . $cust['postcode'] ), $cust['country'] ) );
		if ( $place ) { $addr[] = esc_html( implode( ', ', $place ) ); }
		if ( $addr ) { echo $kv( $L['address'], implode( '<br>', $addr ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
		if ( '' !== $cust['phone'] ) { echo $kv( $L['phone'], esc_html( $cust['phone'] ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
		if ( '' !== $cust['email'] ) { echo $kv( $L['email'], esc_html( $cust['email'] ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
		if ( '' !== $cust['tax_no'] ) { echo $kv( $L['customer_tax'], esc_html( $cust['tax_no'] ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
		if ( '' !== $doc['delivery'] ) { echo $kv( $L['deliver_to'], pq_lines( $doc['delivery'] ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</dl>';
	} else {
		echo '</p><dl class="pqd-kv">';
		if ( '' !== $doc['delivery'] ) { echo $kv( $L['deliver_to'], pq_lines( $doc['delivery'] ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</dl>';
	}
	echo '</div></div>';

	echo '<div class="pqd-grp pqd-sum"><p class="pqd-bar">' . esc_html( strtr( $L['summary_title'], array( '{type}' => pq_doc_label( $doc['type'], $s ) ) ) ) . '</p><dl class="pqd-kv">';
	echo $kv( $L['number'], esc_html( $doc['number'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $kv( $L['date'], esc_html( pq_fmt_date( $doc['issue_date'], $s ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $doc['expiry_date'] ) { echo $kv( $is_quote ? $L['valid_until'] : $L['due_date'], esc_html( pq_fmt_date( $doc['expiry_date'], $s ) ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
	if ( '' !== $doc['reference'] ) { echo $kv( $L['reference'], esc_html( $doc['reference'] ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
	if ( ! empty( $doc['source_number'] ) ) { echo $kv( $L['source'], esc_html( $doc['source_number'] ) ); } // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<dt class="is-strong is-first">' . esc_html( $L['total'] ) . ':</dt><dd class="is-strong is-first">' . $m( $c['total'] ) . '</dd>';
	if ( ! $is_quote ) {
		echo '<dt>' . esc_html( $L['paid'] ) . ':</dt><dd>' . $m( $c['paid'] ) . '</dd>';
		echo '<dt class="is-strong">' . esc_html( $L['balance'] ) . ':</dt><dd class="is-strong">' . $m( $c['balance'] ) . '</dd>';
	}
	echo '</dl></div></section>';

	/* Items */
	echo '<table class="pqd-items"><thead><tr>';
	if ( ! empty( $t['show_numbers'] ) ) { echo '<th class="c-no">' . esc_html( $L['col_no'] ) . '</th>'; }
	if ( $show_img ) { echo '<th class="c-img"></th>'; }
	echo '<th>' . esc_html( $L['col_item'] ) . '</th><th class="c-qty">' . esc_html( $L['col_qty'] ) . '</th><th class="c-price">' . esc_html( $L['col_price'] ) . '</th>';
	if ( $show_disc ) { echo '<th class="c-disc">' . esc_html( $L['col_discount'] ) . '</th>'; }
	echo '<th class="c-amt">' . esc_html( $L['col_amount'] ) . '</th></tr></thead><tbody>';
	$cols = 3 + ( ! empty( $t['show_numbers'] ) ? 1 : 0 ) + ( $show_img ? 1 : 0 ) + ( $show_disc ? 1 : 0 );
	if ( ! $doc['items'] ) { echo '<tr><td class="pqd-empty" colspan="' . (int) $cols . '">No items yet</td></tr>'; }
	foreach ( $doc['items'] as $i => $it ) {
		echo '<tr>';
		if ( ! empty( $t['show_numbers'] ) ) { echo '<td class="c-no">' . (int) ( $i + 1 ) . '</td>'; }
		if ( $show_img ) {
			echo '<td class="c-img">';
			if ( ! empty( $it['img_url'] ) ) {
				$u = (string) $it['img_url'];
				echo '<img src="' . ( 0 === strpos( $u, 'data:image/svg+xml;base64,' ) ? esc_attr( $u ) : esc_url( $u ) ) . '" alt="">';
			}
			echo '</td>';
		}
		echo '<td><div class="pqd-item-name">' . esc_html( $it['name'] ) . '</div>';
		if ( '' !== $it['desc'] ) { echo '<div class="pqd-item-desc">' . pq_lines( $it['desc'] ) . '</div>'; }
		if ( ! empty( $t['show_sku'] ) && '' !== $it['sku'] ) { echo '<div class="pqd-item-sku">' . esc_html( $L['sku'] ) . ': ' . esc_html( $it['sku'] ) . '</div>'; }
		echo '</td><td class="c-qty">' . esc_html( pq_qty( $it['qty'] ) );
		if ( ! empty( $t['show_unit'] ) && '' !== $it['unit'] ) { echo '<span class="pqd-unit">' . esc_html( $it['unit'] ) . '</span>'; }
		echo '</td><td class="c-price">' . ( '' === $it['price'] ? '-' : $m( $it['price'] ) ) . '</td>';
		if ( $show_disc ) { echo '<td class="c-disc">' . ( pq_num( $it['discount'] ) > 0 ? esc_html( pq_qty( $it['discount'] ) ) . '%' : '' ) . '</td>'; }
		echo '<td class="c-amt">' . $m( $c['lines'][ $i ] ) . '</td></tr>';
	}
	echo '</tbody></table>';

	/* Totals and payments */
	echo '<div class="pqd-sumrow"><div class="pqd-payments">';
	if ( ! $is_quote && $doc['payments'] ) {
		echo '<h3>Payments received</h3><table>';
		foreach ( $doc['payments'] as $p ) {
			$what = trim( pq_fmt_date( $p['date'], $s ) . ( '' !== $p['method'] ? ' - ' . $p['method'] : '' ) );
			echo '<tr><td>' . esc_html( $what ) . '</td><td>' . $m( $p['amount'] ) . '</td></tr>';
		}
		echo '</table>';
	}
	echo '</div><table class="pqd-totals">';
	echo '<tr><td>' . esc_html( $L['subtotal'] ) . '</td><td>' . $m( $c['subtotal'] ) . '</td></tr>';
	if ( $c['discount'] > 0 ) {
		$dl = $L['discount'];
		if ( 'percent' === $doc['discount']['type'] ) { $dl .= ' (' . pq_qty( $doc['discount']['value'] ) . '%)'; }
		echo '<tr><td>' . esc_html( $dl ) . '</td><td>-' . $m( $c['discount'] ) . '</td></tr>';
	}
	if ( $c['shipping'] > 0 ) { echo '<tr><td>' . esc_html( $L['shipping'] ) . '</td><td>' . $m( $c['shipping'] ) . '</td></tr>'; }
	if ( 'none' !== $doc['tax']['mode'] && pq_num( $doc['tax']['rate'] ) > 0 ) {
		$tl = $doc['tax']['label'] . ' (' . pq_qty( $doc['tax']['rate'] ) . '%)';
		if ( 'inclusive' === $doc['tax']['mode'] ) { $tl .= ' included'; }
		echo '<tr><td>' . esc_html( $tl ) . '</td><td>' . $m( $c['tax'] ) . '</td></tr>';
	}
	echo '<tr class="t-total"><td>' . esc_html( $L['total'] ) . '</td><td>' . $m( $c['total'] ) . '</td></tr>';
	if ( ! $is_quote && $c['paid'] > 0 ) {
		echo '<tr><td>' . esc_html( $L['paid'] ) . '</td><td>-' . $m( $c['paid'] ) . '</td></tr>';
		echo '<tr class="t-due"><td>' . esc_html( $L['balance'] ) . '</td><td>' . $m( $c['balance'] ) . '</td></tr>';
	}
	echo '</table></div>';

	/* Terms and notes: one block each, so a page break never cuts through one */
	foreach ( $doc['blocks'] as $b ) {
		echo '<div class="pqd-note' . ( 'highlight' === $b['style'] ? ' is-hl' : '' ) . '">';
		if ( '' !== $b['title'] ) { echo '<h3>' . esc_html( $b['title'] ) . '</h3>'; }
		if ( '' !== $b['body'] ) { echo '<p>' . pq_lines( $b['body'] ) . '</p>'; }
		echo '</div>';
	}

	/* Bank details */
	$banks = array();
	foreach ( $s['banks'] as $b ) { if ( in_array( $b['id'], $doc['banks'], true ) ) { $banks[] = $b; } }
	if ( $banks ) {
		echo '<section class="pqd-banks"><p class="pqd-bar">' . esc_html( $L['bank'] ) . ':</p><div class="pqd-bankgrid">';
		foreach ( $banks as $b ) {
			echo '<div class="pqd-bank">';
			if ( '' !== $b['bank'] ) { echo '<b>' . esc_html( $b['bank'] ) . '</b>'; }
			echo '<dl>';
			if ( '' !== $b['account_name'] ) { echo '<dt>Account name:</dt><dd>' . esc_html( $b['account_name'] ) . '</dd>'; }
			if ( '' !== $b['account_no'] ) { echo '<dt>Account no.:</dt><dd>' . esc_html( $b['account_no'] ) . '</dd>'; }
			if ( '' !== $b['branch'] ) { echo '<dt>Branch:</dt><dd>' . esc_html( $b['branch'] ) . '</dd>'; }
			if ( '' !== $b['swift'] ) { echo '<dt>Swift code:</dt><dd>' . esc_html( $b['swift'] ) . '</dd>'; }
			echo '</dl></div>';
		}
		echo '</div></section>';
	}

	if ( ! empty( $t['show_signature'] ) ) {
		echo '<section class="pqd-sign"><div>' . esc_html( $L['signature_company'] ) . '</div><div>' . esc_html( $L['signature_customer'] ) . '</div></section>';
	}
	$foot = trim( pq_footer_text( $doc, $s ) );
	if ( '' !== $foot ) { echo '<footer class="pqd-foot">' . pq_lines( $foot ) . '</footer>'; }
	echo '</div>'; // .pqd-flow

	/* Compact header repeated at the top of every page after the first */
	echo '<div class="pqd-chead-src" hidden><div class="pqd-chead"><div>';
	if ( $logo_sm ) {
		echo '<img src="' . esc_url( $logo_sm['url'] ) . '" width="' . (int) $logo_sm['w'] . '" height="' . (int) $logo_sm['h'] . '" alt="' . esc_attr( $co['name'] ) . '">';
	} else {
		echo '<span class="pqd-chead-name">' . esc_html( $co['name'] ) . '</span>';
	}
	echo '</div><div class="pqd-chead-r"><strong>' . esc_html( $title ) . '</strong>';
	if ( '' !== $customer ) { echo '<span>' . esc_html( $customer ) . '</span>'; }
	echo '</div></div></div>';
	echo '</div>'; // #pqd-root
	return ob_get_clean();
}

/**
 * Lays the flat blocks onto A4 pages. Rules: the first page carries the full header, later pages a compact one;
 * the item table's heading repeats on every page; the totals never sit alone, the last item travels with them;
 * each note, bank block and signature stays whole; every page is numbered "Page n / N".
 * Plain ES5, no dependencies, so it works the same in every browser and in print.
 */
function pq_paginator_js() {
	return <<<'JS'
(function(){
 var root=document.getElementById('pqd-root'); if(!root){return;}
 var flow=root.querySelector('.pqd-flow'), src=root.querySelector('.pqd-chead-src'), meta={};
 try{meta=JSON.parse(root.getAttribute('data-meta')||'{}');}catch(e){}
 function mk(c){var d=document.createElement('div');d.className=c;return d;}
 function paginate(){
  var old=root.querySelector('.pqd-pages'); if(old){old.parentNode.removeChild(old);}
  var wrap=mk('pqd-pages'); root.appendChild(wrap);
  var pages=[], body=null, kids=[].slice.call(flow.children);
  function newPage(){
   var p=mk('pqd-page'), b=mk('pqd-pbody'), f=mk('pqd-pfoot');
   p.appendChild(b); p.appendChild(f); wrap.appendChild(p); pages.push(p); body=b;
   if(pages.length>1&&src&&src.firstElementChild){b.appendChild(src.firstElementChild.cloneNode(true));}
  }
  function fits(){return body.scrollHeight<=body.clientHeight+1;}
  function fresh(){return body.children.length<=(pages.length>1?1:0);}
  function put(n){body.appendChild(n); if(fits()){return true;} body.removeChild(n); return false;}
  function block(n){ if(!put(n)){ if(!fresh()){newPage();} body.appendChild(n); } }
  newPage();
  for(var k=0;k<kids.length;k++){
   var el=kids[k];
   if(el.tagName==='TABLE'&&/pqd-items/.test(el.className)){
    var sum=(kids[k+1]&&/pqd-sumrow/.test(kids[k+1].className))?kids[++k]:null;
    var head=el.tHead, rows=[].slice.call(el.tBodies[0].rows);
    var shell=function(){var t=el.cloneNode(false); t.appendChild(head.cloneNode(true)); var tb=document.createElement('tbody'); t.appendChild(tb); return {t:t,tb:tb,n:0};};
    var sh=shell(); body.appendChild(sh.t);
    for(var i=0;i<rows.length;i++){
     var r=rows[i].cloneNode(true); sh.tb.appendChild(r);
     if(!fits()){
      sh.tb.removeChild(r);
      if(sh.n===0){ body.removeChild(sh.t); if(!fresh()){newPage();} sh=shell(); body.appendChild(sh.t); }
      else { newPage(); sh=shell(); body.appendChild(sh.t); }
      sh.tb.appendChild(r); sh.n=1;
     } else { sh.n++; }
    }
    if(sum){
     var s=sum.cloneNode(true);
     if(!put(s)){
      if(sh.n>1){
       var last=sh.tb.lastElementChild; sh.tb.removeChild(last); sh.n--;
       newPage(); var s2=shell(); body.appendChild(s2.t); s2.tb.appendChild(last); s2.n=1; body.appendChild(s);
      } else { newPage(); body.appendChild(s); }
     }
    }
    continue;
   }
   block(el.cloneNode(true));
  }
  var n=pages.length;
  for(var j=0;j<n;j++){
   var f=pages[j].querySelector('.pqd-pfoot'), a=document.createElement('span'), b2=document.createElement('span');
   a.textContent=(meta.company?meta.company+'  |  ':'')+(meta.title||''); b2.textContent=(meta.page||'Page')+' '+(j+1)+' / '+n;
   f.appendChild(a); f.appendChild(b2);
  }
  var cnt=document.querySelectorAll('[data-pqd-pages]'); for(var q=0;q<cnt.length;q++){cnt[q].textContent=n+(n===1?' page':' pages')+', A4';}
  document.documentElement.className=document.documentElement.className.replace(/\bpqd-js\b/,'')+' pqd-js';
  var st=document.getElementById('pqd-page-style');
  if(!st){st=document.createElement('style'); st.id='pqd-page-style'; st.textContent='@page{size:A4;margin:0}'; document.head.appendChild(st);}
  fitWidth(wrap);
 }
 function fitWidth(wrap){
  var avail=document.documentElement.clientWidth-16, sc=Math.min(1,avail/794);
  wrap.style.zoom=sc<1?sc:'';
 }
 paginate();
 window.addEventListener('load',paginate);
 window.addEventListener('resize',function(){var w=root.querySelector('.pqd-pages'); if(w){fitWidth(w);}});
})();
JS;
}

/** Complete HTML page. $ctx: public | preview. */
function pq_document_html( $doc, $s = null, $ctx = 'public' ) {
	$s   = $s ? $s : pq_settings();
	$doc = pq_doc_prepare( $doc, $s );
	$title = pq_doc_label( $doc['type'], $s ) . ' ' . $doc['number'];

	ob_start();
	echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
	echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
	echo '<meta name="robots" content="noindex,nofollow">';
	echo '<title>' . esc_html( $title ) . '</title><style>' . pq_document_css( $s ) . '</style></head>';
	echo '<body class="pqd-body pqd-' . esc_attr( $ctx ) . '">';
	if ( 'public' === $ctx ) {
		echo '<div class="pqd-toolbar"><span><strong>' . esc_html( $title ) . '</strong><small data-pqd-pages></small></span><button type="button" onclick="window.print()">Print or save as PDF</button></div>';
	}
	echo pq_document_body( $doc, $s, $ctx );
	echo '<script>' . pq_paginator_js() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</body></html>';
	return ob_get_clean();
}

/** A realistic sample document for the template preview in settings. */
function pq_sample_doc( $s ) {
	$d = pq_blank_doc( 'quotation', $s, false );
	$d['number']   = $s['numbering']['quotation']['prefix'] . ( $s['numbering']['quotation']['pad'] ? str_pad( (string) $s['numbering']['quotation']['next'], (int) $s['numbering']['quotation']['pad'], '0', STR_PAD_LEFT ) : (string) $s['numbering']['quotation']['next'] );
	$d['customer'] = array_merge( pq_blank_customer(), array(
		'name'    => 'Mr. Nuwan',
		'company' => 'Fushifaru Resort',
		'email'   => 'nuwan@example.com',
		'phone'   => '+960 700 0000',
		'address' => 'M. Eyecare Building, Orchid Magu',
		'city'    => 'Male',
		'country' => 'Maldives',
	) );
	$ph = pq_sample_placeholder();
	$d['items'] = array(
		array_merge( pq_blank_item(), array( 'name' => 'Electric Flat Grill 730x500x230mm', 'desc' => '220V, 50Hz, 4.4kW', 'sku' => 'EFG-730', 'qty' => 1, 'unit' => 'Pcs', 'price' => 770, 'img_url' => $ph ) ),
		array_merge( pq_blank_item(), array( 'name' => 'Electric Flat Grill with Groove 550x400x230mm', 'desc' => '220V, 50Hz, 3kW', 'sku' => 'EFG-550G', 'qty' => 1, 'unit' => 'Pcs', 'price' => 400, 'img_url' => $ph ) ),
	);
	$d['reference'] = 'PO-2026-118';
	return $d;
}
