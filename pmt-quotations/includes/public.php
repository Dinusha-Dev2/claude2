<?php
/**
 * Public view of a document, reached through a private key link.
 * Customers can read it and print or save it as a PDF from their browser.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['pq_doc'] ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification
	$id  = absint( $_GET['pq_doc'] ); // phpcs:ignore
	$key = isset( $_GET['k'] ) ? sanitize_text_field( wp_unslash( $_GET['k'] ) ) : ''; // phpcs:ignore
	$stored = $id ? (string) get_post_meta( $id, '_pq_key', true ) : '';

	if ( ! $id || PQ_CPT !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || '' === $stored || ! hash_equals( $stored, $key ) ) {
		status_header( 404 );
		nocache_headers();
		wp_die( 'This link is not valid, or the document is no longer available.', 'Not found', array( 'response' => 404 ) );
	}
	$doc = pq_get_doc( $id );
	if ( ! $doc ) {
		status_header( 404 );
		wp_die( 'This document is no longer available.', 'Not found', array( 'response' => 404 ) );
	}

	if ( ! pq_can() && ! get_post_meta( $id, '_pq_viewed', true ) ) {
		update_post_meta( $id, '_pq_viewed', time() );
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'Content-Type: text/html; charset=utf-8' );
	echo pq_document_html( $doc, null, 'public' ); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}, 1 );
