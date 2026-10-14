<?php
/**
 * One-time upgrades of saved settings when a new version first runs.
 * Only fills in what is new; never overwrites something the owner changed.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pq_maybe_upgrade() {
	$from = (string) get_option( 'pq_version', '' );
	if ( '' !== $from && version_compare( $from, '1.5.0', '<' ) ) {
		pq_upgrade_to_150();
	}
	if ( '' !== $from && version_compare( $from, '1.6.0', '<' ) ) {
		pq_upgrade_to_160();
	}
	if ( PQ_VERSION !== $from ) {
		update_option( 'pq_version', PQ_VERSION, false );
	}
}
add_action( 'init', 'pq_maybe_upgrade', 1 );

/** 1.5.0: four document types, show / hide per type, tax per currency, terms per type. */
function pq_upgrade_to_150() {
	$raw = get_option( 'pq_settings', array() );
	if ( ! is_array( $raw ) || ! $raw ) { return; }

	// Old wording that 1.5.0 changes, only where the owner kept the old default.
	if ( isset( $raw['labels']['doc_invoice'] ) && 'Invoice' === $raw['labels']['doc_invoice'] ) {
		$raw['labels']['doc_invoice'] = 'Tax invoice';
	}
	if ( isset( $raw['labels']['source'] ) && 'Quotation ref.' === $raw['labels']['source'] ) {
		$raw['labels']['source'] = '{type} ref.';
	}

	// The old global item-table switches become the show / hide defaults of every type.
	if ( empty( $raw['display'] ) ) {
		$display = pq_display_factory();
		$tp      = isset( $raw['template'] ) && is_array( $raw['template'] ) ? $raw['template'] : array();
		$map     = array( 'show_numbers' => 'row_numbers', 'show_image' => 'images', 'show_sku' => 'sku', 'show_unit' => 'unit' );
		foreach ( $display as $type => $row ) {
			foreach ( $map as $old => $new ) {
				if ( isset( $tp[ $old ] ) ) { $display[ $type ][ $new ] = empty( $tp[ $old ] ) ? 0 : 1; }
			}
			if ( isset( $tp['show_discount'] ) && 'never' === $tp['show_discount'] ) { $display[ $type ]['line_discount'] = 0; }
			if ( 'delivery' !== $type && isset( $tp['show_signature'] ) && in_array( $type, array( 'quotation', 'proforma' ), true ) ) {
				$display[ $type ]['signature'] = empty( $tp['show_signature'] ) ? 0 : 1;
			}
		}
		$raw['display'] = $display;
	}
	if ( isset( $raw['template'] ) && is_array( $raw['template'] ) ) {
		foreach ( array( 'show_numbers', 'show_image', 'show_sku', 'show_unit', 'show_discount', 'show_signature', 'paper' ) as $k ) {
			unset( $raw['template'][ $k ] );
		}
	}

	// Tax and amount-in-words names for the currencies we know, where not set yet.
	if ( isset( $raw['currencies'] ) && is_array( $raw['currencies'] ) ) {
		foreach ( $raw['currencies'] as $i => $c ) {
			$known = pq_currency_row( isset( $c['code'] ) ? $c['code'] : '', '', 0 );
			foreach ( array( 'tax_label', 'tax_rate', 'word', 'subword' ) as $k ) {
				if ( ! isset( $c[ $k ] ) ) { $raw['currencies'][ $i ][ $k ] = $known[ $k ]; }
			}
		}
	}

	// Existing terms keep starting on quotations and invoices; delivery notes get their own block.
	if ( isset( $raw['blocks'] ) && is_array( $raw['blocks'] ) ) {
		$has_delivery = false;
		foreach ( $raw['blocks'] as $i => $b ) {
			if ( ! isset( $b['on'] ) ) { $raw['blocks'][ $i ]['on'] = ''; }
			if ( isset( $b['on'] ) && in_array( $b['on'], array( 'delivery', 'all' ), true ) ) { $has_delivery = true; }
		}
		if ( ! $has_delivery ) { $raw['blocks'][] = pq_delivery_block(); }
	}

	update_option( 'pq_settings', $raw, false );
	pq_settings( true );
}

/** 1.6.0: one numbering style, QT-0001, PI-0001, TXI-0001, DN-0001, each series starting at 0001. */
function pq_upgrade_to_160() {
	$raw = get_option( 'pq_settings', array() );
	if ( ! is_array( $raw ) || ! $raw ) { return; }
	$new = array( 'quotation' => 'QT-', 'proforma' => 'PI-', 'invoice' => 'TXI-', 'delivery' => 'DN-' );
	foreach ( $new as $type => $prefix ) {
		// Numbers already used are skipped when the next one is worked out, so restarting at 1 never clashes.
		$raw['numbering'][ $type ] = array( 'prefix' => $prefix, 'next' => 1, 'pad' => 4 );
	}
	update_option( 'pq_settings', $raw, false );
	pq_settings( true );
}
