<?php
/**
 * Plugin Name:       PMT Quotations & Invoices
 * Description:       Quotations, proforma invoices, tax invoices and delivery notes with saved customers, a store product picker, custom items with images, payment tracking and a fully customisable document template.
 * Version:           1.6.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            eSupport Technology
 * License:           GPL-2.0-or-later
 * Text Domain:       pmt-quotations
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'PQ_VERSION', '1.6.2' );
define( 'PQ_FILE', __FILE__ );
define( 'PQ_DIR', plugin_dir_path( __FILE__ ) );
define( 'PQ_URL', plugin_dir_url( __FILE__ ) );
define( 'PQ_CPT', 'pq_quote' );
define( 'PQ_CUSTOMER', 'pq_customer' );

require_once PQ_DIR . 'includes/helpers.php';
require_once PQ_DIR . 'includes/upgrade.php';
require_once PQ_DIR . 'includes/documents.php';
require_once PQ_DIR . 'includes/render.php';
require_once PQ_DIR . 'includes/post-types.php';
require_once PQ_DIR . 'includes/admin.php';
require_once PQ_DIR . 'includes/editor-page.php';
require_once PQ_DIR . 'includes/settings-page.php';
require_once PQ_DIR . 'includes/ajax.php';
require_once PQ_DIR . 'includes/public.php';
require_once PQ_DIR . 'includes/rfq-bridge.php';

register_activation_hook( PQ_FILE, function () {
	if ( false === get_option( 'pq_settings', false ) ) {
		add_option( 'pq_settings', array(), '', false );
	}
	update_option( 'pq_version', PQ_VERSION, false );
} );
