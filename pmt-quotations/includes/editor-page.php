<?php
/**
 * The document editor page: boot data for the script and the page markup.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Links and relations the editor shows for a saved document. */
function pq_doc_links( $id, $doc ) {
	$links = array(
		'list'     => admin_url( 'edit.php?post_type=' . PQ_CPT ),
		'settings' => admin_url( 'admin.php?page=pq-settings' ),
		'new'      => pq_editor_url(),
	);
	if ( ! $id ) { return $links; }
	$links['public']    = pq_public_url( $id );
	$links['duplicate'] = pq_action_url( 'pq_duplicate_doc', $id );
	$links['convert']   = array();
	foreach ( pq_convert_targets( $doc['type'] ) as $t ) { $links['convert'][ $t ] = pq_action_url( 'pq_convert_doc', $id, array( 'to' => $t ) ); }
	$trash              = get_delete_post_link( $id );
	$links['trash']     = $trash ? $trash : '';
	// Documents made from this one, newest first.
	$links['children'] = array();
	$kids = get_posts( array(
		'post_type' => PQ_CPT, 'post_status' => 'publish', 'posts_per_page' => 10, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC',
		'meta_query' => array( array( 'key' => '_pq_source', 'value' => (int) $id ) ),
	) );
	foreach ( $kids as $kid ) {
		$links['children'][] = array( 'url' => pq_editor_url( $kid ), 'number' => (string) get_post_meta( $kid, '_pq_number', true ), 'type' => pq_doc_label( (string) get_post_meta( $kid, '_pq_type', true ) ) );
	}
	$src                = (int) $doc['source_id'];
	$links['source']    = ( $src && PQ_CPT === get_post_type( $src ) ) ? pq_editor_url( $src ) : '';
	$rfq                = (int) $doc['rfq_id'];
	$links['rfq']       = ( $rfq && 'pa_rfq' === get_post_type( $rfq ) ) ? get_edit_post_link( $rfq, 'raw' ) : '';
	$log                = get_post_meta( $id, '_pq_sent_log', true );
	$links['sent_log']  = array();
	if ( is_array( $log ) ) {
		foreach ( array_slice( array_reverse( $log ), 0, 5 ) as $e ) {
			if ( isset( $e['time'], $e['to'] ) ) { $links['sent_log'][] = wp_date( 'j M Y, H:i', (int) $e['time'] ) . ' to ' . $e['to']; }
		}
	}
	$viewed             = (int) get_post_meta( $id, '_pq_viewed', true );
	$links['viewed']    = $viewed ? wp_date( 'j M Y, H:i', $viewed ) : '';
	return $links;
}

/** Everything editor.js needs. */
function pq_editor_boot() {
	$s  = pq_settings();
	$id = isset( $_GET['doc'] ) ? absint( $_GET['doc'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$doc = $id ? pq_get_doc( $id ) : null;

	if ( ! $doc ) {
		$id   = 0;
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'quotation'; // phpcs:ignore
		$doc  = pq_blank_doc( $type, $s );
		if ( ! empty( $_GET['customer'] ) ) { // phpcs:ignore
			$c = pq_get_customer( absint( $_GET['customer'] ) ); // phpcs:ignore
			if ( $c ) { $doc['customer'] = $c; }
		}
		if ( ! empty( $_GET['from_rfq'] ) ) { // phpcs:ignore
			$doc = pq_prefill_from_rfq( $doc, absint( $_GET['from_rfq'] ) ); // phpcs:ignore
		}
	}

	$banks = array();
	foreach ( $s['banks'] as $b ) {
		$banks[] = array( 'id' => $b['id'], 'label' => trim( $b['bank'] . ( '' !== $b['account_no'] ? ' - ' . $b['account_no'] : '' ) ) );
	}
	$next_numbers = array();
	foreach ( array_keys( pq_types() ) as $t ) { list( $next_numbers[ $t ] ) = pq_next_number( $t, $s ); }
	$statuses = array();
	$expiry   = array();
	$days     = array();
	$display  = array();
	$blocks   = array();
	$targets  = array();
	foreach ( array_keys( pq_types() ) as $t ) {
		$statuses[ $t ] = pq_statuses( $t );
		$expiry[ $t ]   = pq_type_expiry( $t );
		$days[ $t ]     = pq_type_days( $t, $s );
		$display[ $t ]  = pq_display_for_type( $t, $s );
		$blocks[ $t ]   = pq_blocks_for_type( $t, $s );
		$targets[ $t ]  = pq_convert_targets( $t );
	}
	$fields = array();
	foreach ( pq_display_fields() as $k => $f ) { $fields[] = array( 'key' => $k, 'label' => $f[0], 'group' => $f[1], 'types' => $f[2] ); }
	$taxes = array();
	foreach ( $s['currencies'] as $cur ) { $taxes[ $cur['code'] ] = pq_tax_for_currency( $cur['code'], $s ); }

	$links = pq_doc_links( $id, $doc );

	return array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'pq_admin' ),
		'doc'   => pq_doc_for_editor( $doc ),
		'cfg'   => array(
			'currencies'     => $s['currencies'],
			'decimals'       => (int) $s['format']['decimals'],
			'decSep'         => $s['format']['dec_sep'],
			'thouSep'        => $s['format']['thou_sep'],
			'units'          => pq_units_list( $s ),
			'defaultUnit'    => $s['defaults']['unit'],
			'banks'          => $banks,
			'defaultBlocks'  => $blocks,
			'expiry'         => $expiry,
			'typeDays'       => $days,
			'display'        => $display,
			'displayFields'  => $fields,
			'displayGroups'  => pq_display_groups(),
			'taxes'          => $taxes,
			'targets'        => $targets,
			'taxShipping'    => ! empty( $s['tax']['tax_shipping'] ),
			'storeCurrency'  => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'hasStore'       => post_type_exists( 'product' ),
			'types'          => pq_types(),
			'statuses'       => $statuses,
			'labels'         => array_combine( array_keys( pq_types() ), array_map( function ( $t ) use ( $s ) { return pq_doc_label( $t, $s ); }, array_keys( pq_types() ) ) ),
			'today'          => pq_today(),
			'validDays'      => (int) $s['defaults']['valid_days'],
			'dueDays'        => (int) $s['defaults']['due_days'],
			'placeholder'    => pq_sample_placeholder(),
			'nextNumbers'    => $next_numbers,
		),
		'links'  => $links,
		'notice' => isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '', // phpcs:ignore
	);
}

function pq_render_editor_page() {
	if ( ! pq_can() ) { wp_die( 'You are not allowed to open this page.' ); }
	$currency_options = '';
	foreach ( pq_settings()['currencies'] as $c ) { $currency_options .= '<option value="' . esc_attr( $c['code'] ) . '">' . esc_html( $c['code'] . ' (' . $c['symbol'] . ')' ) . '</option>'; }
	?>
<div class="wrap pq-app pq-editor-page">
	<h1 class="screen-reader-text">Quotation editor</h1>
	<div id="pq-editor" class="pq-editor">
		<header class="pq-bar">
			<div class="pq-bar-title">
				<?php
				$back_id   = isset( $_GET['doc'] ) ? absint( $_GET['doc'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
				$back_type = $back_id ? (string) get_post_meta( $back_id, '_pq_type', true ) : ( isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'quotation' ); // phpcs:ignore
				$back_type = isset( pq_types()[ $back_type ] ) ? $back_type : 'quotation';
				?>
				<a class="pq-back" href="<?php echo esc_url( admin_url( pq_list_url( $back_type ) ) ); ?>"><?php echo esc_html( pq_type_plural( $back_type ) ); ?></a>
				<div class="pq-bar-line"><strong id="pq-title">New quotation</strong><span id="pq-badge" class="pq-badge"></span><span id="pq-dirty" class="pq-dirty" hidden>Unsaved changes</span></div>
			</div>
			<div class="pq-bar-actions">
				<button type="button" class="button" data-act="print" data-needs-saved>Print or save as PDF</button>
				<button type="button" class="button" data-act="email" data-needs-saved>Email</button>
				<button type="button" class="button" data-act="copy" data-needs-saved>Copy link</button>
				<details class="pq-more" data-needs-saved>
					<summary class="button">More</summary>
					<div class="pq-more-menu">
						<span id="pq-convert-items"></span>
						<a href="#" data-act="duplicate">Duplicate</a>
						<a href="#" data-act="trash" class="pq-danger">Move to trash</a>
					</div>
				</details>
				<button type="button" class="button button-primary" data-act="save">Save</button>
			</div>
		</header>

		<div id="pq-links" class="pq-links" hidden></div>
		<div id="pq-toast" class="pq-toast" role="status" aria-live="polite" hidden></div>

		<div class="pq-cols">
			<div class="pq-form" id="pq-form">

				<section class="pq-card">
					<h2>Document</h2>
					<div class="pq-grid pq-g3">
						<label>Type
							<select data-bind="type">
								<?php foreach ( array_keys( pq_types() ) as $k ) { echo '<option value="' . esc_attr( $k ) . '">' . esc_html( pq_doc_label( $k ) ) . '</option>'; } ?>
							</select>
						</label>
						<label>Number
							<input type="text" data-bind="number" autocomplete="off">
						</label>
						<label>Status
							<select data-bind="status"></select>
						</label>
						<label>Currency
							<select data-bind="currency">
								<?php foreach ( pq_settings()['currencies'] as $c ) { echo '<option value="' . esc_attr( $c['code'] ) . '">' . esc_html( $c['code'] . ' (' . $c['symbol'] . ')' ) . '</option>'; } ?>
							</select>
							<span class="description">Changes the symbol only. Prices are not converted.</span>
						</label>
						<label>Date
							<input type="date" data-bind="issue_date">
						</label>
						<label data-expiry-wrap><span data-expiry-label>Valid until</span>
							<input type="date" data-bind="expiry_date">
						</label>
					</div>
					<div class="pq-grid pq-g1">
						<label>Customer reference or PO number
							<input type="text" data-bind="reference" placeholder="Optional, shown on the document">
						</label>
					</div>
				</section>

				<section class="pq-card" id="pq-dn-card" hidden>
					<h2>Delivery details</h2>
					<div class="pq-grid pq-g3">
						<label>Delivery method <input type="text" data-bind="delivery_info.method" list="pq-dn-methods" placeholder="Own vehicle, courier, sea freight"></label>
						<label>Vehicle / boat no. <input type="text" data-bind="delivery_info.vehicle"></label>
						<label>Delivered by <input type="text" data-bind="delivery_info.driver" placeholder="Driver or staff name"></label>
					</div>
					<datalist id="pq-dn-methods"><option value="Own vehicle"><option value="Courier"><option value="Sea freight"><option value="Air freight"><option value="Customer collection"></datalist>
				</section>

				<section class="pq-card">
					<h2>Customer</h2>
					<div class="pq-search">
						<label>Find a saved customer
							<input type="search" id="pq-cust-search" placeholder="Search by name, company, email or phone" autocomplete="off">
						</label>
						<div id="pq-cust-results" class="pq-results" hidden></div>
					</div>
					<div id="pq-cust-linked" class="pq-linked" hidden></div>
					<div class="pq-grid pq-g2">
						<label>Contact name <input type="text" data-bind="customer.name"></label>
						<label>Company <input type="text" data-bind="customer.company"></label>
						<label>Email <input type="email" data-bind="customer.email"></label>
						<label>Phone <input type="text" data-bind="customer.phone"></label>
					</div>
					<div class="pq-grid pq-g1">
						<label>Address <textarea rows="2" data-bind="customer.address"></textarea></label>
					</div>
					<div class="pq-grid pq-g4">
						<label>City <input type="text" data-bind="customer.city"></label>
						<label>Postcode <input type="text" data-bind="customer.postcode"></label>
						<label>Country <input type="text" data-bind="customer.country"></label>
						<label>Tax / TIN no. <input type="text" data-bind="customer.tax_no"></label>
					</div>
					<div class="pq-grid pq-g2">
						<label>Usually quoted in
							<select data-bind="customer.currency"><option value="">No preference</option><?php echo $currency_options; // phpcs:ignore WordPress.Security.EscapeOutput ?></select>
							<span class="description">New documents for this customer start in this currency.</span>
						</label>
					</div>
					<div class="pq-grid pq-g1">
						<label>Deliver to (optional) <textarea rows="2" data-bind="delivery" placeholder="Delivery address, resort or island, if different"></textarea></label>
					</div>
					<label class="pq-check"><input type="checkbox" data-bind="save_customer"> Save these details so I can find this customer again</label>
				</section>

				<section class="pq-card">
					<div class="pq-card-head">
						<h2>Items</h2>
						<div class="pq-card-actions">
							<button type="button" class="button" data-act="undo-prices" hidden>Undo price change</button>
							<button type="button" class="button" data-act="price-tools">Price tools</button>
							<button type="button" class="button" data-act="add-store">Add from store</button>
							<button type="button" class="button" data-act="add-custom">Add custom item</button>
						</div>
					</div>
					<div id="pq-items" class="pq-items"></div>
					<p id="pq-items-empty" class="pq-empty" hidden>No items yet. Add products from the store, or add a custom item with your own name, image and price.</p>
				</section>

				<section class="pq-card">
					<h2>Discount</h2>
					<div class="pq-disc">
						<div class="pq-seg" role="radiogroup" aria-label="Discount type">
							<label><input type="radio" name="pq-disc-type" value="percent" data-bind-radio="discount.type"><span>Percentage (%)</span></label>
							<label><input type="radio" name="pq-disc-type" value="fixed" data-bind-radio="discount.type"><span>Amount (<b data-cur-symbol></b>)</span></label>
						</div>
						<div class="pq-grid pq-g3">
							<label><span data-disc-value-label>Discount (%)</span> <input type="number" min="0" step="any" data-bind="discount.value" placeholder="0"></label>
							<label>Shown on the document as <input type="text" data-bind="discount.label" placeholder="Discount"></label>
							<div class="pq-disc-out"><span>Discount amount</span><strong id="pq-disc-amt">-</strong></div>
						</div>
						<p class="description">Applied to the subtotal, before tax. Each item can also have its own discount, as a percentage or an amount.</p>
					</div>
				</section>

				<section class="pq-card">
					<h2>Tax and totals</h2>
					<div class="pq-grid pq-g4">
						<label>Tax name <input type="text" data-bind="tax.label" placeholder="GST"></label>
						<label>Tax rate (%) <input type="number" min="0" max="100" step="any" data-bind="tax.rate"></label>
						<label>Tax is
							<select data-bind="tax.mode">
								<option value="exclusive">Added on top of prices</option>
								<option value="inclusive">Already included in prices</option>
								<option value="none">Not charged</option>
							</select>
						</label>
						<label>Delivery fee <span class="pq-inputgroup"><span class="pq-sym" data-cur-symbol></span><input type="number" min="0" step="any" data-bind="shipping.amount"></span></label>
					</div>
					<p class="description" id="pq-tax-hint"></p>
					<div id="pq-summary" class="pq-summary"></div>
				</section>

				<section class="pq-card" id="pq-pay-card" hidden>
					<div class="pq-card-head">
						<h2>Payments</h2>
						<div class="pq-card-actions"><button type="button" class="button" data-act="add-payment">Record payment</button></div>
					</div>
					<div id="pq-payments"></div>
					<p id="pq-pay-empty" class="pq-empty">No payments recorded.</p>
				</section>

				<section class="pq-card">
					<div class="pq-card-head">
						<h2>Terms and notes</h2>
						<div class="pq-card-actions">
							<button type="button" class="button" data-act="reset-blocks">Use default terms</button>
							<button type="button" class="button" data-act="add-block">Add a block</button>
						</div>
					</div>
					<div id="pq-blocks"></div>
					<p id="pq-blocks-empty" class="pq-empty" hidden>No terms on this document.</p>
				</section>

				<section class="pq-card">
					<h2>Bank details on the document</h2>
					<div id="pq-banks" class="pq-banks"></div>
					<p class="description">Add or edit bank accounts in <a href="<?php echo esc_url( admin_url( 'admin.php?page=pq-settings' ) ); ?>">Settings</a>.</p>
				</section>

				<section class="pq-card">
					<div class="pq-card-head">
						<h2>Extra details</h2>
						<div class="pq-card-actions"><button type="button" class="button" data-act="add-extra">Add a detail</button></div>
					</div>
					<p class="description">Anything else to print with the document details, for example Project, Sales person or Payment terms.</p>
					<div id="pq-extra"></div>
				</section>

				<section class="pq-card">
					<div class="pq-card-head">
						<h2>Show on this document</h2>
						<div class="pq-card-actions"><button type="button" class="button" data-act="reset-show">Use defaults for this type</button></div>
					</div>
					<p class="description">Untick anything you do not want printed. Defaults for each type are in <a href="<?php echo esc_url( admin_url( 'admin.php?page=pq-settings#display' ) ); ?>">Settings</a>.</p>
					<div id="pq-show" class="pq-show"></div>
				</section>

				<section class="pq-card">
					<h2>Internal note</h2>
					<label class="screen-reader-text" for="pq-internal">Internal note</label>
					<textarea id="pq-internal" rows="3" data-bind="internal" placeholder="Only visible to your team. Never printed or emailed."></textarea>
				</section>
			</div>

			<aside class="pq-preview" aria-label="Document preview">
				<div class="pq-preview-bar"><span>Live preview</span><button type="button" class="button-link" data-act="refresh">Refresh</button></div>
				<div class="pq-preview-wrap" id="pq-preview-wrap"><iframe id="pq-frame" title="Document preview" tabindex="-1"></iframe></div>
			</aside>
		</div>
	</div>

	<!-- Store product picker -->
	<div class="pq-modal" id="pq-m-products" hidden>
		<div class="pq-modal-box" role="dialog" aria-modal="true" aria-labelledby="pq-mp-title">
			<div class="pq-modal-head">
				<h2 id="pq-mp-title">Add from store</h2>
				<button type="button" class="pq-x" data-act="close-modal" aria-label="Close">&times;</button>
			</div>
			<div class="pq-modal-tools">
				<input type="search" id="pq-prod-q" placeholder="Search products by name or SKU" autocomplete="off">
				<select id="pq-prod-cat"><option value="0">All categories</option></select>
			</div>
			<div class="pq-modal-body"><div id="pq-prod-list" class="pq-prodgrid"></div><p id="pq-prod-msg" class="pq-empty"></p><p class="pq-more-wrap"><button type="button" class="button" id="pq-prod-more" hidden>Load more</button></p></div>
			<div class="pq-modal-foot"><span id="pq-prod-added" class="description"></span><button type="button" class="button button-primary" data-act="close-modal">Done</button></div>
		</div>
	</div>

	<!-- Price tools -->
	<div class="pq-modal" id="pq-m-prices" hidden>
		<div class="pq-modal-box pq-narrow" role="dialog" aria-modal="true" aria-labelledby="pq-pt-title">
			<div class="pq-modal-head">
				<h2 id="pq-pt-title">Price tools</h2>
				<button type="button" class="pq-x" data-act="close-modal" aria-label="Close">&times;</button>
			</div>
			<div class="pq-modal-body pq-form-body">
				<label>What do you want to do?
					<select id="pq-pt-action">
						<option value="raise">Raise every price by a percentage</option>
						<option value="lower">Lower every price by a percentage</option>
						<option value="discount">Give every line the same discount</option>
						<option value="round">Round every price</option>
						<option value="clear-discount">Remove all line discounts</option>
					</select>
				</label>
				<label id="pq-pt-value-wrap">Percentage <input type="number" id="pq-pt-value" min="0" step="any" value="10"></label>
				<label id="pq-pt-round-wrap" hidden>Round to
					<select id="pq-pt-round">
						<option value="1">The nearest whole number</option>
						<option value="up1">Up to the next whole number</option>
						<option value="0.5">The nearest 0.50</option>
						<option value="5">The nearest 5</option>
						<option value="10">The nearest 10</option>
						<option value="100">The nearest 100</option>
					</select>
				</label>
				<p id="pq-pt-info" class="description"></p>
			</div>
			<div class="pq-modal-foot"><span id="pq-pt-msg" class="description"></span><span><button type="button" class="button" data-act="close-modal">Cancel</button> <button type="button" class="button button-primary" id="pq-pt-apply">Apply</button></span></div>
		</div>
	</div>

	<!-- Email -->
	<div class="pq-modal" id="pq-m-email" hidden>
		<div class="pq-modal-box pq-narrow" role="dialog" aria-modal="true" aria-labelledby="pq-me-title">
			<div class="pq-modal-head">
				<h2 id="pq-me-title">Email to customer</h2>
				<button type="button" class="pq-x" data-act="close-modal" aria-label="Close">&times;</button>
			</div>
			<div class="pq-modal-body pq-form-body">
				<label>To <input type="email" id="pq-em-to"></label>
				<label>Subject <input type="text" id="pq-em-subject"></label>
				<label>Message <textarea id="pq-em-body" rows="9"></textarea></label>
				<label class="pq-check"><input type="checkbox" id="pq-em-copy" checked> Send me a copy</label>
				<p class="description">The email includes a secure link where your customer can view, print or save the document as a PDF.</p>
			</div>
			<div class="pq-modal-foot"><span id="pq-em-msg" class="description"></span><button type="button" class="button button-primary" id="pq-em-send">Send email</button></div>
		</div>
	</div>
	<noscript><p>This editor needs JavaScript.</p></noscript>
</div>
	<?php
}
