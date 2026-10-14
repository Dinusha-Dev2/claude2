<?php
/**
 * Storage: private post types for documents and customers, plus their admin lists.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'init', function () {
	$cap  = pq_cap();
	// Only primitive capabilities are mapped. The meta capabilities (edit_post, read_post,
	// delete_post) must keep their own names or WordPress would hijack $cap site-wide.
	$caps = array(
		'edit_posts'             => $cap,
		'edit_others_posts'      => $cap,
		'delete_posts'           => $cap,
		'publish_posts'          => $cap,
		'read_private_posts'     => $cap,
		'delete_private_posts'   => $cap,
		'delete_published_posts' => $cap,
		'delete_others_posts'    => $cap,
		'edit_private_posts'     => $cap,
		'edit_published_posts'   => $cap,
	);

	register_post_type( PQ_CPT, array(
		'labels'              => array(
			'name' => 'All documents', 'singular_name' => 'Document', 'menu_name' => 'Quotes & Invoices',
			'all_items' => 'All documents', 'search_items' => 'Search documents',
			'not_found' => 'No documents yet', 'not_found_in_trash' => 'Nothing in the trash',
		),
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => false,
		'menu_position'       => 27,
		'menu_icon'           => 'dashicons-media-spreadsheet',
		'supports'            => array( 'title' ),
		'capability_type'     => array( 'pq_doc', 'pq_docs' ),
		'capabilities'        => array_merge( $caps, array( 'create_posts' => 'do_not_allow' ) ),
		'map_meta_cap'        => true,
		'exclude_from_search' => true,
		'has_archive'         => false,
		'rewrite'             => false,
	) );

	register_post_type( PQ_CUSTOMER, array(
		'labels'              => array(
			'name' => 'Customers', 'singular_name' => 'Customer', 'menu_name' => 'Customers',
			'all_items' => 'Customers', 'add_new' => 'Add customer', 'add_new_item' => 'Add customer',
			'edit_item' => 'Customer details', 'search_items' => 'Search customers',
			'not_found' => 'No customers saved yet', 'not_found_in_trash' => 'Nothing in the trash',
		),
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => 'edit.php?post_type=' . PQ_CPT,
		'show_in_rest'        => false,
		'supports'            => array( 'title' ),
		'capability_type'     => array( 'pq_cust', 'pq_custs' ),
		'capabilities'        => $caps,
		'map_meta_cap'        => true,
		'exclude_from_search' => true,
		'has_archive'         => false,
		'rewrite'             => false,
	) );
} );

/* Edit links open the quotation editor, never the default post screen. */
add_filter( 'get_edit_post_link', function ( $link, $id ) {
	if ( PQ_CPT === get_post_type( $id ) ) { return pq_editor_url( $id ); }
	return $link;
}, 10, 2 );

add_action( 'load-post.php', function () {
	$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	if ( $id && PQ_CPT === get_post_type( $id ) && ( ! isset( $_GET['action'] ) || 'edit' === $_GET['action'] ) ) { // phpcs:ignore
		wp_safe_redirect( pq_editor_url( $id ) );
		exit;
	}
} );

/* ------------------------------------------------------------------ */
/* Documents list                                                      */
/* ------------------------------------------------------------------ */

add_filter( 'manage_' . PQ_CPT . '_posts_columns', function () {
	$lt   = function_exists( 'pq_list_type' ) ? pq_list_type() : '';
	$cols = array(
		'cb'          => '<input type="checkbox">',
		'title'       => $lt ? pq_doc_label( $lt ) . ' no.' : 'Document',
		'pq_type'     => 'Type',
		'pq_customer' => 'Customer',
		'pq_date'     => 'Date',
		'pq_total'    => 'delivery' === $lt ? 'Items' : 'Total',
		'pq_status'   => 'Status',
	);
	if ( $lt ) { unset( $cols['pq_type'] ); }
	return $cols;
} );

add_filter( 'manage_edit-' . PQ_CPT . '_sortable_columns', function () {
	return array( 'title' => 'title', 'pq_date' => 'pq_date', 'pq_total' => 'pq_total' );
} );

add_action( 'manage_' . PQ_CPT . '_posts_custom_column', function ( $col, $id ) {
	$doc = pq_get_doc( $id );
	if ( ! $doc ) { return; }
	$doc = pq_doc_prepare( $doc );
	if ( 'pq_type' === $col ) {
		echo esc_html( pq_doc_label( $doc['type'] ) );
	} elseif ( 'pq_customer' === $col ) {
		$c = $doc['customer'];
		echo esc_html( pq_customer_label( $c ) );
		if ( '' !== $c['company'] && '' !== $c['name'] ) { echo '<br><span class="description">' . esc_html( $c['name'] ) . '</span>'; }
	} elseif ( 'pq_date' === $col ) {
		echo esc_html( pq_fmt_date( $doc['issue_date'] ) );
	} elseif ( 'pq_total' === $col ) {
		if ( 'delivery' === $doc['type'] && ! pq_doc_show( $doc )['prices'] ) {
			$n = count( $doc['items'] );
			echo esc_html( $n . ( 1 === $n ? ' item' : ' items' ) );
			return;
		}
		echo '<strong>' . esc_html( pq_money( $doc['calc']['total'], $doc['currency'] ) ) . '</strong>';
		if ( pq_type_has_payments( $doc['type'] ) && 'converted' !== $doc['status'] && $doc['calc']['balance'] > 0 && $doc['calc']['paid'] > 0 ) {
			echo '<br><span class="description">Due ' . esc_html( pq_money( $doc['calc']['balance'], $doc['currency'] ) ) . '</span>';
		}
	} elseif ( 'pq_status' === $col ) {
		list( $key, $label ) = pq_display_status( $doc );
		echo '<span class="pq-badge pq-st-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</span>';
		$viewed = get_post_meta( $id, '_pq_viewed', true );
		if ( $viewed && 'draft' !== $doc['status'] ) { echo '<br><span class="description">Opened by customer</span>'; }
	}
}, 10, 2 );

add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( PQ_CPT === $post->post_type ) {
		$new = array();
		$new['edit'] = '<a href="' . esc_url( pq_editor_url( $post->ID ) ) . '">Edit</a>';
		$url = pq_public_url( $post->ID );
		if ( $url ) { $new['pq_view'] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">View / print</a>'; }
		$new['pq_dup'] = '<a href="' . esc_url( pq_action_url( 'pq_duplicate_doc', $post->ID ) ) . '">Duplicate</a>';
		$ptype = (string) get_post_meta( $post->ID, '_pq_type', true );
		$next  = array( 'quotation' => 'invoice', 'proforma' => 'invoice', 'invoice' => 'delivery' );
		if ( isset( $next[ $ptype ] ) ) {
			$new['pq_inv'] = '<a href="' . esc_url( pq_action_url( 'pq_convert_doc', $post->ID, array( 'to' => $next[ $ptype ] ) ) ) . '">Create ' . esc_html( strtolower( pq_doc_label( $next[ $ptype ] ) ) ) . '</a>';
		}
		if ( isset( $actions['trash'] ) ) { $new['trash'] = $actions['trash']; }
		return $new;
	}
	if ( PQ_CUSTOMER === $post->post_type ) {
		unset( $actions['inline hide-if-no-js'] );
		$new = array( 'pq_new' => '<a href="' . esc_url( pq_editor_url( 0, array( 'customer' => $post->ID ) ) ) . '">New quotation</a>' );
		$new['pq_docs'] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . PQ_CPT . '&pq_customer=' . (int) $post->ID ) ) . '">Documents</a>';
		return array_merge( $actions, $new );
	}
	return $actions;
}, 10, 2 );

add_filter( 'bulk_actions-edit-' . PQ_CPT, function ( $a ) { unset( $a['edit'] ); return $a; } );
add_filter( 'bulk_actions-edit-' . PQ_CUSTOMER, function ( $a ) { unset( $a['edit'] ); return $a; } );

add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( PQ_CPT !== $post_type ) { return; }
	$type   = pq_list_type();
	$status = isset( $_GET['pq_status'] ) ? sanitize_key( wp_unslash( $_GET['pq_status'] ) ) : ''; // phpcs:ignore
	if ( $type ) {
		// On a type's own page the type is fixed; only its statuses are offered.
		echo '<input type="hidden" name="pq_type" value="' . esc_attr( $type ) . '">';
		$statuses = pq_statuses( $type );
	} else {
		echo '<select name="pq_type"><option value="">All types</option>';
		foreach ( array_keys( pq_types() ) as $k ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $type, $k, false ) . '>' . esc_html( pq_doc_label( $k ) ) . '</option>'; }
		echo '</select>';
		$statuses = pq_all_statuses();
	}
	echo '<select name="pq_status"><option value="">All statuses</option>';
	foreach ( $statuses as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $status, $k, false ) . '>' . esc_html( $v ) . '</option>'; }
	echo '</select>';
} );

add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() ) { return; }
	$pt = $q->get( 'post_type' );
	if ( PQ_CPT !== $pt ) { return; }
	$meta = (array) $q->get( 'meta_query' );
	$g    = function ( $k ) { return isset( $_GET[ $k ] ) ? sanitize_key( wp_unslash( $_GET[ $k ] ) ) : ''; }; // phpcs:ignore
	if ( $g( 'pq_type' ) )   { $meta[] = array( 'key' => '_pq_type', 'value' => $g( 'pq_type' ) ); }
	if ( $g( 'pq_status' ) ) { $meta[] = array( 'key' => '_pq_status', 'value' => $g( 'pq_status' ) ); }
	if ( $g( 'pq_customer' ) ) { $meta[] = array( 'key' => '_pq_customer', 'value' => absint( $g( 'pq_customer' ) ) ); }
	if ( $meta ) { $q->set( 'meta_query', $meta ); }
	$ob = $q->get( 'orderby' );
	if ( 'pq_date' === $ob ) { $q->set( 'meta_key', '_pq_issue' ); $q->set( 'orderby', 'meta_value' ); }
	if ( 'pq_total' === $ob ) { $q->set( 'meta_key', '_pq_total' ); $q->set( 'orderby', 'meta_value_num' ); }
} );

/* Search also by customer name. */
add_filter( 'posts_search', function ( $search, $q ) {
	global $wpdb;
	if ( ! is_admin() || ! $q->is_main_query() || PQ_CPT !== $q->get( 'post_type' ) || '' === $q->get( 's' ) ) { return $search; }
	$like = '%' . $wpdb->esc_like( $q->get( 's' ) ) . '%';
	return $wpdb->prepare( " AND ( {$wpdb->posts}.post_title LIKE %s OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key IN ('_pq_number','_pq_customer_name') AND pm.meta_value LIKE %s ) ) ", $like, $like );
}, 10, 2 );

/* ------------------------------------------------------------------ */
/* Duplicate and convert (links from the list and the editor)          */
/* ------------------------------------------------------------------ */

function pq_admin_post_guard( $action ) {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	check_admin_referer( $action . '_' . $id );
	if ( ! pq_can() ) { wp_die( 'You are not allowed to do this.', '', array( 'response' => 403 ) ); }
	return $id;
}

add_action( 'admin_post_pq_duplicate_doc', function () {
	$id  = pq_admin_post_guard( 'pq_duplicate_doc' );
	$res = pq_duplicate_document( $id );
	if ( is_wp_error( $res ) ) { wp_die( esc_html( $res->get_error_message() ) ); }
	wp_safe_redirect( pq_editor_url( $res['id'], array( 'notice' => 'duplicated' ) ) );
	exit;
} );

add_action( 'admin_post_pq_convert_doc', function () {
	$id = pq_admin_post_guard( 'pq_convert_doc' );
	$to = isset( $_GET['to'] ) ? sanitize_key( wp_unslash( $_GET['to'] ) ) : 'invoice';
	$res = pq_convert_document( $id, $to );
	if ( is_wp_error( $res ) ) { wp_die( esc_html( $res->get_error_message() ) ); }
	wp_safe_redirect( pq_editor_url( $res['id'], array( 'notice' => 'converted' ) ) );
	exit;
} );

/* ------------------------------------------------------------------ */
/* Customers                                                           */
/* ------------------------------------------------------------------ */

add_filter( 'manage_' . PQ_CUSTOMER . '_posts_columns', function () {
	return array(
		'cb'         => '<input type="checkbox">',
		'title'      => 'Customer',
		'pq_contact' => 'Contact',
		'pq_where'   => 'Location',
		'pq_docs'    => 'Documents',
		'pq_billed'  => 'Total quoted',
	);
} );

add_action( 'manage_' . PQ_CUSTOMER . '_posts_custom_column', function ( $col, $id ) {
	$c = pq_get_customer( $id );
	if ( ! $c ) { return; }
	if ( 'pq_contact' === $col ) {
		echo esc_html( $c['name'] );
		if ( '' !== $c['email'] ) { echo '<br><a href="mailto:' . esc_attr( $c['email'] ) . '">' . esc_html( $c['email'] ) . '</a>'; }
		if ( '' !== $c['phone'] ) { echo '<br>' . esc_html( $c['phone'] ); }
	} elseif ( 'pq_where' === $col ) {
		echo esc_html( trim( $c['city'] . ( '' !== $c['city'] && '' !== $c['country'] ? ', ' : '' ) . $c['country'] ) );
	} else {
		$ids = get_posts( array( 'post_type' => PQ_CPT, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_pq_customer', 'value' => $id ) ) ) );
		if ( 'pq_docs' === $col ) {
			echo $ids ? '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . PQ_CPT . '&pq_customer=' . (int) $id ) ) . '">' . (int) count( $ids ) . '</a>' : '0';
		} else {
			$sums = array();
			foreach ( $ids as $did ) {
				if ( 'quotation' !== get_post_meta( $did, '_pq_type', true ) ) { continue; }
				$cur = get_post_meta( $did, '_pq_currency', true );
				$sums[ $cur ] = ( isset( $sums[ $cur ] ) ? $sums[ $cur ] : 0 ) + (float) get_post_meta( $did, '_pq_total', true );
			}
			$out = array();
			foreach ( $sums as $cur => $v ) { $out[] = esc_html( pq_money( $v, $cur ) ); }
			echo $out ? implode( '<br>', $out ) : '-'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}, 10, 2 );

add_action( 'add_meta_boxes_' . PQ_CUSTOMER, function () {
	add_meta_box( 'pq_customer_details', 'Customer details', 'pq_customer_metabox', PQ_CUSTOMER, 'normal', 'high' );
	add_meta_box( 'pq_customer_docs', 'Documents', 'pq_customer_docs_metabox', PQ_CUSTOMER, 'normal', 'default' );
} );

function pq_customer_metabox( $post ) {
	$c = pq_get_customer( $post->ID );
	if ( ! $c ) { $c = pq_blank_customer(); }
	wp_nonce_field( 'pq_customer_save', 'pq_customer_nonce' );
	$rows = array(
		'name' => 'Contact name', 'company' => 'Company', 'email' => 'Email', 'phone' => 'Phone',
		'address' => 'Address', 'city' => 'City', 'postcode' => 'Postcode', 'country' => 'Country', 'tax_no' => 'Tax / TIN no.',
		'currency' => 'Usually quoted in',
	);
	echo '<table class="form-table pq-form-table"><tbody>';
	foreach ( $rows as $k => $label ) {
		echo '<tr><th scope="row"><label for="pq_c_' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( 'address' === $k ) {
			echo '<textarea class="large-text" rows="3" id="pq_c_address" name="pq_c[address]">' . esc_textarea( $c['address'] ) . '</textarea>';
		} elseif ( 'currency' === $k ) {
			echo '<select id="pq_c_currency" name="pq_c[currency]"><option value="">No preference</option>';
			foreach ( pq_settings()['currencies'] as $cur ) { echo '<option value="' . esc_attr( $cur['code'] ) . '"' . selected( $c['currency'], $cur['code'], false ) . '>' . esc_html( $cur['code'] . ' (' . $cur['symbol'] . ')' ) . '</option>'; }
			echo '</select>';
		} else {
			echo '<input type="' . ( 'email' === $k ? 'email' : 'text' ) . '" class="regular-text" id="pq_c_' . esc_attr( $k ) . '" name="pq_c[' . esc_attr( $k ) . ']" value="' . esc_attr( $c[ $k ] ) . '">';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	if ( $post->ID && 'auto-draft' !== $post->post_status ) {
		echo '<p><a class="button button-primary" href="' . esc_url( pq_editor_url( 0, array( 'customer' => $post->ID ) ) ) . '">New quotation for this customer</a></p>';
	}
}

function pq_customer_docs_metabox( $post ) {
	$ids = get_posts( array( 'post_type' => PQ_CPT, 'post_status' => 'publish', 'posts_per_page' => 50, 'meta_query' => array( array( 'key' => '_pq_customer', 'value' => (int) $post->ID ) ), 'fields' => 'ids' ) );
	if ( ! $ids ) { echo '<p>No quotations or invoices for this customer yet.</p>'; return; }
	echo '<table class="widefat striped"><thead><tr><th>Document</th><th>Date</th><th>Total</th><th>Status</th></tr></thead><tbody>';
	foreach ( $ids as $did ) {
		$doc = pq_get_doc( $did );
		if ( ! $doc ) { continue; }
		$doc = pq_doc_prepare( $doc );
		list( $key, $label ) = pq_display_status( $doc );
		echo '<tr><td><a href="' . esc_url( pq_editor_url( $did ) ) . '">' . esc_html( $doc['number'] ) . '</a> <span class="description">' . esc_html( pq_doc_label( $doc['type'] ) ) . '</span></td>';
		echo '<td>' . esc_html( pq_fmt_date( $doc['issue_date'] ) ) . '</td><td>' . esc_html( pq_money( $doc['calc']['total'], $doc['currency'] ) ) . '</td>';
		echo '<td><span class="pq-badge pq-st-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</span></td></tr>';
	}
	echo '</tbody></table>';
}

add_action( 'save_post_' . PQ_CUSTOMER, function ( $id ) {
	if ( ! isset( $_POST['pq_customer_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pq_customer_nonce'] ) ), 'pq_customer_save' ) ) { return; }
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( ! pq_can() ) { return; }
	$c = pq_clean_customer( isset( $_POST['pq_c'] ) ? wp_unslash( $_POST['pq_c'] ) : array() ); // phpcs:ignore
	foreach ( pq_customer_fields() as $f ) { update_post_meta( $id, '_pq_c_' . $f, wp_slash( $c[ $f ] ) ); }
	$title = pq_customer_label( $c );
	$post  = get_post( $id );
	if ( '' !== $title && ( '' === trim( $post->post_title ) || 'Auto Draft' === $post->post_title ) ) {
		static $busy = false;
		if ( $busy ) { return; }
		$busy = true;
		wp_update_post( array( 'ID' => $id, 'post_title' => wp_slash( $title ) ) );
		$busy = false;
	}
} );

/* Search customers by contact details in the list. */
add_filter( 'posts_search', function ( $search, $q ) {
	global $wpdb;
	if ( ! is_admin() || ! $q->is_main_query() || PQ_CUSTOMER !== $q->get( 'post_type' ) || '' === $q->get( 's' ) ) { return $search; }
	$like = '%' . $wpdb->esc_like( $q->get( 's' ) ) . '%';
	return $wpdb->prepare( " AND ( {$wpdb->posts}.post_title LIKE %s OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key IN ('_pq_c_name','_pq_c_company','_pq_c_email','_pq_c_phone') AND pm.meta_value LIKE %s ) ) ", $like, $like );
}, 10, 2 );
