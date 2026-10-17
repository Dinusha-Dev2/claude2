<?php
/**
 * Settings page. Everything the client may want to change lives here.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pq_n( $path ) {
	$out = 'pq_settings';
	foreach ( (array) $path as $p ) { $out .= '[' . $p . ']'; }
	return $out;
}

function pq_field_text( $path, $val, $label, $args = array() ) {
	$args = wp_parse_args( $args, array( 'type' => 'text', 'hint' => '', 'class' => 'regular-text', 'attrs' => '' ) );
	echo '<label class="pq-field">' . esc_html( $label ) . '<input type="' . esc_attr( $args['type'] ) . '" class="' . esc_attr( $args['class'] ) . '" name="' . esc_attr( pq_n( $path ) ) . '" value="' . esc_attr( $val ) . '" ' . $args['attrs'] . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $args['hint'] ) { echo '<span class="description">' . esc_html( $args['hint'] ) . '</span>'; }
	echo '</label>';
}

function pq_field_select( $path, $val, $label, $options, $hint = '' ) {
	echo '<label class="pq-field">' . esc_html( $label ) . '<select name="' . esc_attr( pq_n( $path ) ) . '">';
	foreach ( $options as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) $val, (string) $k, false ) . '>' . esc_html( $v ) . '</option>'; }
	echo '</select>';
	if ( $hint ) { echo '<span class="description">' . esc_html( $hint ) . '</span>'; }
	echo '</label>';
}

function pq_field_check( $path, $val, $label ) {
	echo '<label class="pq-check"><input type="checkbox" name="' . esc_attr( pq_n( $path ) ) . '" value="1"' . checked( ! empty( $val ), true, false ) . '> ' . esc_html( $label ) . '</label>';
}

function pq_field_area( $path, $val, $label, $rows = 3, $hint = '' ) {
	echo '<label class="pq-field">' . esc_html( $label ) . '<textarea class="large-text" rows="' . (int) $rows . '" name="' . esc_attr( pq_n( $path ) ) . '">' . esc_textarea( $val ) . '</textarea>';
	if ( $hint ) { echo '<span class="description">' . esc_html( $hint ) . '</span>'; }
	echo '</label>';
}

function pq_logo_picker( $id ) {
	$url = $id ? wp_get_attachment_image_url( (int) $id, 'medium' ) : '';
	echo '<div class="pq-field"><span>Logo</span><div class="pq-logo-pick">';
	echo '<input type="hidden" name="' . esc_attr( pq_n( array( 'company', 'logo_id' ) ) ) . '" value="' . (int) $id . '" class="pq-logo-id">';
	echo '<div class="pq-logo-prev">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '<span class="description">No logo chosen</span>' ) . '</div>';
	echo '<button type="button" class="button pq-pick-logo">Choose logo</button> <button type="button" class="button-link pq-clear-logo"' . ( $id ? '' : ' hidden' ) . '>Remove</button>';
	echo '</div></div>';
}

/** Repeater rows. $fields: key => array( label, type, [options] ). */
function pq_repeater( $path, $rows, $fields, $add_label, $empty_row ) {
	$name = pq_n( $path );
	echo '<div class="pq-rep" data-name="' . esc_attr( $name ) . '"><div class="pq-rep-rows">';
	$render = function ( $row, $idx ) use ( $name, $fields ) {
		echo '<div class="pq-rep-row">';
		foreach ( $fields as $key => $f ) {
			$field_name = $name . '[' . $idx . '][' . $key . ']';
			$val        = isset( $row[ $key ] ) ? $row[ $key ] : '';
			if ( 'hidden' === $f[1] ) {
				echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $val ) . '">';
				continue;
			}
			echo '<label class="pq-rep-f pq-w-' . esc_attr( isset( $f[3] ) ? $f[3] : 'm' ) . '"><span>' . esc_html( $f[0] ) . '</span>';
			if ( 'textarea' === $f[1] ) {
				echo '<textarea rows="3" name="' . esc_attr( $field_name ) . '">' . esc_textarea( $val ) . '</textarea>';
			} elseif ( 'select' === $f[1] ) {
				echo '<select name="' . esc_attr( $field_name ) . '">';
				foreach ( $f[2] as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) $val, (string) $k, false ) . '>' . esc_html( $v ) . '</option>'; }
				echo '</select>';
			} elseif ( 'check' === $f[1] ) {
				echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '" value="1"' . checked( ! empty( $val ), true, false ) . '>';
			} else {
				echo '<input type="text" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $val ) . '">';
			}
			echo '</label>';
		}
		echo '<button type="button" class="button-link pq-rep-del" aria-label="Remove this row">Remove</button></div>';
	};
	foreach ( array_values( $rows ) as $i => $row ) { $render( $row, $i ); }
	echo '</div><template class="pq-rep-tpl">';
	$render( $empty_row, '__I__' );
	echo '</template><button type="button" class="button pq-rep-add">' . esc_html( $add_label ) . '</button></div>';
}

function pq_render_settings_page() {
	if ( ! pq_can() ) { wp_die( 'You are not allowed to open this page.' ); }
	$s = pq_settings();
	$tabs = array(
		'business' => 'Business',
		'numbering' => 'Numbers and dates',
		'currency' => 'Currency and tax',
		'template' => 'Template',
		'display'  => 'Show / hide',
		'terms'    => 'Terms and banks',
		'email'    => 'Email',
		'data'     => 'Data',
	);
	$no_preview = array( 'email', 'data' );
	?>
<div class="wrap pq-app pq-settings">
	<h1>Quotation settings</h1>
	<?php settings_errors(); ?>
	<form method="post" action="options.php" id="pq-settings-form">
		<?php settings_fields( 'pq_settings_group' ); ?>
		<input type="hidden" name="pq_settings[_form]" value="1">
		<div class="pq-tabs" role="tablist">
			<?php foreach ( $tabs as $k => $label ) { echo '<button type="button" role="tab" class="pq-tab" data-tab="' . esc_attr( $k ) . '" data-preview="' . ( in_array( $k, $no_preview, true ) ? '0' : '1' ) . '">' . esc_html( $label ) . '</button>'; } ?>
		</div>

		<div class="pq-set-cols">
		<div class="pq-set-main">

		<!-- Business -->
		<section class="pq-pane" data-pane="business">
			<div class="pq-card">
				<h2>Your business</h2>
				<p class="description">Shown at the top of every document.</p>
				<?php
				pq_field_text( array( 'company', 'name' ), $s['company']['name'], 'Business name' );
				pq_logo_picker( $s['company']['logo_id'] );
				pq_field_area( array( 'company', 'address' ), $s['company']['address'], 'Address', 3 );
				echo '<div class="pq-grid pq-g2">';
				pq_field_text( array( 'company', 'phone' ), $s['company']['phone'], 'Phone (Ph.)', array( 'class' => 'widefat', 'hint' => 'Landline. Separate several numbers with |' ) );
				pq_field_text( array( 'company', 'mobile' ), $s['company']['mobile'], 'Mobile (Mob.)', array( 'class' => 'widefat', 'hint' => 'Printed on its own line under the phone.' ) );
				pq_field_text( array( 'company', 'email' ), $s['company']['email'], 'Email', array( 'type' => 'email', 'class' => 'widefat' ) );
				pq_field_text( array( 'company', 'website' ), $s['company']['website'], 'Website', array( 'type' => 'url', 'class' => 'widefat' ) );
				echo '</div>';
				?>
				<h3>Registration details</h3>
				<p class="description">Tax number, registration number, postcode or anything else that must appear under your address.</p>
				<?php pq_repeater( array( 'company', 'details' ), $s['company']['details'], array( 'label' => array( 'Label', 'text', null, 's' ), 'value' => array( 'Value', 'text', null, 'm' ) ), 'Add detail', array( 'label' => '', 'value' => '' ) ); ?>
			</div>
		</section>

		<!-- Numbers and dates -->
		<section class="pq-pane" data-pane="numbering" hidden>
			<div class="pq-card">
				<h2>Document numbers</h2>
				<p class="description">Each type has its own series and counts on its own: a new document gets the next number after the highest one already saved of that type (QT-0002 after QT-0001). A number is only used when a document is saved, and deleting the last document frees its number again.</p>
				<table class="pq-numtable">
					<thead><tr><th>Type</th><th>Prefix</th><th>Start at</th><th>Digits</th><th>Next new number</th></tr></thead>
					<tbody>
					<?php foreach ( pq_types() as $t => $label ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td><input type="text" size="8" name="<?php echo esc_attr( pq_n( array( 'numbering', $t, 'prefix' ) ) ); ?>" value="<?php echo esc_attr( $s['numbering'][ $t ]['prefix'] ); ?>"></td>
							<td><input type="number" min="1" class="small-text" name="<?php echo esc_attr( pq_n( array( 'numbering', $t, 'next' ) ) ); ?>" value="<?php echo (int) $s['numbering'][ $t ]['next']; ?>"></td>
							<td><input type="number" min="0" max="10" class="small-text" name="<?php echo esc_attr( pq_n( array( 'numbering', $t, 'pad' ) ) ); ?>" value="<?php echo (int) $s['numbering'][ $t ]['pad']; ?>"></td>
							<td><strong><?php echo esc_html( pq_next_number( $t, $s )[0] ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">Digits pads the number with zeros: 4 digits makes TXI-0001. "Start at" is the first number of a series, for example 1, or 501 to continue from an old system.</p>
			</div>
			<div class="pq-card">
				<h2>Defaults for new documents</h2>
				<div class="pq-grid pq-g3">
					<?php
					pq_field_text( array( 'defaults', 'valid_days' ), $s['defaults']['valid_days'], 'Quotations and proformas valid for (days)', array( 'type' => 'number', 'class' => 'small-text', 'attrs' => 'min="0"' ) );
					pq_field_text( array( 'defaults', 'due_days' ), $s['defaults']['due_days'], 'Tax invoices due in (days)', array( 'type' => 'number', 'class' => 'small-text', 'attrs' => 'min="0"' ) );
					$unit_opts = array();
					foreach ( pq_units_list( $s ) as $u ) { $unit_opts[ $u ] = $u; }
					pq_field_select( array( 'defaults', 'unit' ), $s['defaults']['unit'], 'Default unit', $unit_opts );
					?>
				</div>
				<?php pq_field_area( array( 'defaults', 'units' ), $s['defaults']['units'], 'Units of measure', 5, 'One per line. These appear in the unit list on each item.' ); ?>
			</div>
			<div class="pq-card">
				<h2>Number and date format</h2>
				<div class="pq-grid pq-g4">
					<?php
					pq_field_text( array( 'format', 'decimals' ), $s['format']['decimals'], 'Decimal places', array( 'type' => 'number', 'class' => 'small-text', 'attrs' => 'min="0" max="4"' ) );
					pq_field_select( array( 'format', 'dec_sep' ), $s['format']['dec_sep'], 'Decimal separator', array( '.' => 'Dot (1,234.50)', ',' => 'Comma (1.234,50)' ) );
					pq_field_select( array( 'format', 'thou_sep' ), $s['format']['thou_sep'], 'Thousands separator', array( ',' => 'Comma', '.' => 'Dot', ' ' => 'Space', "'" => 'Apostrophe', '' => 'None' ) );
					$dates = array();
					foreach ( array( 'j M Y', 'd/m/Y', 'm/d/Y', 'Y/m/d', 'Y-m-d', 'F j, Y' ) as $f ) { $dates[ $f ] = wp_date( $f ); }
					pq_field_select( array( 'format', 'date' ), $s['format']['date'], 'Date format', $dates );
					?>
				</div>
			</div>
		</section>

		<!-- Currency and tax -->
		<section class="pq-pane" data-pane="currency" hidden>
			<div class="pq-card">
				<h2>Currencies</h2>
				<p class="description">Each document picks its own currency. Changing it only changes the symbol shown beside every price and total. Prices are never converted, so type the price in the currency you choose. <strong>Decimals</strong> overrides the general decimal places for that currency.</p>
				<p class="description"><strong>Tax name and rate</strong> are applied automatically when a document is set to that currency, for example GST 8% for USD and VAT 18% for LKR. Leave them empty to use the general tax below; a rate of 0 means no tax. <strong>Amount in words</strong> uses the currency names, for example "US Dollars" and "Cents".</p>
				<?php
				$codes = wp_list_pluck( $s['currencies'], 'code' );
				pq_field_select( array( 'currency' ), $s['currency'], 'Default currency', array_combine( $codes, $codes ), 'Used for new documents. After adding a currency below, save to see it here.' );
				pq_repeater( array( 'currencies' ), $s['currencies'], array(
					'code'     => array( 'Code', 'text', null, 's' ),
					'symbol'   => array( 'Symbol', 'text', null, 's' ),
					'position' => array( 'Symbol position', 'select', array( 'before' => 'Before the amount', 'after' => 'After the amount' ), 'm' ),
					'space'    => array( 'Space', 'check', null, 'xs' ),
					'decimals' => array( 'Decimals', 'select', array( '' => 'Default', '0' => '0', '1' => '1', '2' => '2', '3' => '3', '4' => '4' ), 'xs' ),
				'tax_label' => array( 'Tax name', 'text', null, 's' ),
				'tax_rate'  => array( 'Tax rate (%)', 'text', null, 's' ),
				'word'      => array( 'Currency in words', 'text', null, 'm' ),
				'subword'   => array( 'Cents in words', 'text', null, 's' ),
				), 'Add currency', array( 'code' => '', 'symbol' => '', 'position' => 'before', 'space' => 0, 'decimals' => '', 'tax_label' => '', 'tax_rate' => '', 'word' => '', 'subword' => '' ) );
				?>
			</div>
			<div class="pq-card">
				<h2>General tax</h2>
				<p class="description">Used when a currency has no tax of its own. You can still change the name, rate or method on each document.</p>
				<div class="pq-grid pq-g3">
					<?php
					pq_field_text( array( 'tax', 'label' ), $s['tax']['label'], 'Tax name', array( 'class' => 'regular-text' ) );
					pq_field_text( array( 'tax', 'rate' ), $s['tax']['rate'], 'Tax rate (%)', array( 'type' => 'number', 'class' => 'small-text', 'attrs' => 'min="0" max="100" step="any"' ) );
					pq_field_select( array( 'tax', 'mode' ), $s['tax']['mode'], 'Tax is', array( 'exclusive' => 'Added on top of prices', 'inclusive' => 'Already included in prices', 'none' => 'Not charged' ) );
					?>
				</div>
				<?php pq_field_check( array( 'tax', 'tax_shipping' ), $s['tax']['tax_shipping'], 'Charge tax on the delivery fee too' ); ?>
			</div>
		</section>

		<!-- Template -->
		<section class="pq-pane" data-pane="template" hidden>
			<div class="pq-card">
				<h2>Look</h2>
				<div class="pq-grid pq-g3">
					<label class="pq-field">Accent colour<input type="text" class="pq-color" name="<?php echo esc_attr( pq_n( array( 'template', 'accent' ) ) ); ?>" value="<?php echo esc_attr( $s['template']['accent'] ); ?>" data-default-color="#1a1a1a"></label>
					<?php
					$fopts = array();
					foreach ( pq_fonts() as $k => $f ) { $fopts[ $k ] = $f[0]; }
					pq_field_select( array( 'template', 'font' ), $s['template']['font'], 'Typeface', $fopts );
					pq_field_select( array( 'template', 'size' ), $s['template']['size'], 'Text size', array( 'compact' => 'Compact', 'regular' => 'Regular', 'large' => 'Large' ) );
					pq_field_text( array( 'template', 'logo_height' ), $s['template']['logo_height'], 'Logo height (px)', array( 'type' => 'number', 'class' => 'small-text', 'attrs' => 'min="20" max="160"' ) );
					pq_field_text( array( 'template', 'img_size' ), $s['template']['img_size'], 'Item image size (px)', array( 'type' => 'number', 'class' => 'small-text', 'attrs' => 'min="24" max="120"' ) );
					?>
				</div>
			</div>
			<div class="pq-card">
				<h2>Footer text</h2>
				<p class="description">You can use {days}, {valid_until}, {due_date}, {number} and {company}.</p>
				<?php foreach ( array_keys( pq_types() ) as $t ) { pq_field_text( array( 'footer', $t ), $s['footer'][ $t ], pq_doc_label( $t, $s ), array( 'class' => 'widefat' ) ); } ?>
			</div>
			<div class="pq-card">
				<details>
					<summary><h2 class="pq-inline-h">Words used on the document</h2></summary>
					<p class="description">Rename any heading, for example to translate the document or match your wording.</p>
					<div class="pq-grid pq-g3">
						<?php foreach ( pq_default_labels() as $k => $def ) { pq_field_text( array( 'labels', $k ), $s['labels'][ $k ], ucfirst( str_replace( '_', ' ', $k ) ), array( 'class' => 'widefat', 'attrs' => 'placeholder="' . esc_attr( $def ) . '"' ) ); } ?>
					</div>
				</details>
			</div>
			<div class="pq-card">
				<details>
					<summary><h2 class="pq-inline-h">Custom CSS</h2></summary>
					<p class="description">For fine adjustments. Document classes start with <code>.pqd-</code>.</p>
					<?php pq_field_area( array( 'template', 'custom_css' ), $s['template']['custom_css'], 'CSS', 8 ); ?>
				</details>
			</div>
		</section>

		<!-- Show / hide -->
		<section class="pq-pane" data-pane="display" hidden>
			<div class="pq-card">
				<h2>What each document shows</h2>
				<p class="description">The starting choices for each type. Every document can still switch any of these on or off in its own "Show on this document" box. Use "Preview as" on the right to see each type.</p>
				<table class="pq-showtable">
					<thead><tr><th scope="col">Part of the document</th>
					<?php foreach ( array_keys( pq_types() ) as $t ) { echo '<th scope="col">' . esc_html( pq_doc_label( $t, $s ) ) . '</th>'; } ?>
					</tr></thead>
					<?php
					$fields = pq_display_fields();
					foreach ( pq_display_groups() as $g => $glabel ) {
						echo '<tbody><tr class="pq-showgroup"><th colspan="' . ( 1 + count( pq_types() ) ) . '">' . esc_html( $glabel ) . '</th></tr>';
						foreach ( $fields as $k => $f ) {
							if ( $g !== $f[1] ) { continue; }
							echo '<tr><th scope="row">' . esc_html( $f[0] ) . '</th>';
							foreach ( array_keys( pq_types() ) as $t ) {
								echo '<td>';
								if ( pq_display_applies( $k, $t ) ) {
									echo '<input type="checkbox" name="' . esc_attr( pq_n( array( 'display', $t, $k ) ) ) . '" value="1"' . checked( ! empty( $s['display'][ $t ][ $k ] ), true, false ) . ' aria-label="' . esc_attr( $f[0] . ', ' . pq_doc_label( $t, $s ) ) . '">';
								} else {
									echo '<span class="pq-na" title="Not used on this type">-</span>';
								}
								echo '</td>';
							}
							echo '</tr>';
						}
						echo '</tbody>';
					}
					?>
				</table>
			</div>
		</section>

		<!-- Terms and banks -->
		<section class="pq-pane" data-pane="terms" hidden>
			<div class="pq-card">
				<h2>Default terms and notes</h2>
				<p class="description">New documents start with the blocks set for their type. Edit them per document in the editor. A highlighted block stands out with a grey band, good for policies.</p>
				<?php pq_repeater( array( 'blocks' ), $s['blocks'], array(
					'title' => array( 'Heading', 'text', null, 'm' ),
					'style' => array( 'Style', 'select', array( '' => 'Standard', 'highlight' => 'Highlighted' ), 's' ),
					'on'    => array( 'Starts on', 'select', pq_block_on_options(), 'm' ),
					'body'  => array( 'Text', 'textarea', null, 'l' ),
				), 'Add a block', array( 'title' => '', 'body' => '', 'style' => '', 'on' => '' ) ); ?>
			</div>
			<div class="pq-card">
				<h2>Bank accounts</h2>
				<p class="description">Tick "Include by default" to print an account on every new document. You can switch accounts on or off per document.</p>
				<?php pq_repeater( array( 'banks' ), $s['banks'], array(
					'id'           => array( '', 'hidden' ),
					'bank'         => array( 'Bank', 'text', null, 'm' ),
					'account_name' => array( 'Account name', 'text', null, 'm' ),
					'account_no'   => array( 'Account number', 'text', null, 'm' ),
					'branch'       => array( 'Branch', 'text', null, 'm' ),
					'swift'        => array( 'SWIFT code', 'text', null, 's' ),
					'show'         => array( 'Include by default', 'check', null, 'xs' ),
				), 'Add bank account', array( 'id' => '', 'bank' => '', 'account_name' => '', 'account_no' => '', 'branch' => '', 'swift' => '', 'show' => 1 ) ); ?>
			</div>
		</section>

		<!-- Email -->
		<section class="pq-pane" data-pane="email" hidden>
			<div class="pq-card">
				<h2>Email to customers</h2>
				<p class="description">This is the starting text when you email a document. You can edit it each time before sending.</p>
				<?php
				pq_field_text( array( 'email', 'subject' ), $s['email']['subject'], 'Subject', array( 'class' => 'widefat' ) );
				pq_field_area( array( 'email', 'body' ), $s['email']['body'], 'Message', 10 );
				?>
				<p class="description">Placeholders: {customer} {company} {number} {type} {type_lower} {total} {valid_until} {link}</p>
			</div>
		</section>

		<!-- Data -->
		<section class="pq-pane" data-pane="data" hidden>
			<div class="pq-card">
				<h2>Data</h2>
				<?php pq_field_check( array( 'general', 'delete_on_uninstall' ), $s['general']['delete_on_uninstall'], 'Delete all quotations, customers and settings when this plugin is deleted' ); ?>
				<p class="description">Leave this off to keep your records safe if the plugin is removed by mistake.</p>
			</div>
		</section>

		<p class="submit"><button type="submit" class="button button-primary button-large">Save settings</button></p>
		</div>

		<aside class="pq-set-preview" id="pq-set-preview">
			<div class="pq-preview-bar"><span>Live preview</span><label class="pq-preview-as">Preview as <select id="pq-set-type"><?php foreach ( array_keys( pq_types() ) as $t ) { echo '<option value="' . esc_attr( $t ) . '">' . esc_html( pq_doc_label( $t, $s ) ) . '</option>'; } ?></select></label><button type="button" class="button-link" id="pq-set-refresh">Refresh</button></div>
			<div class="pq-preview-wrap" id="pq-preview-wrap"><iframe id="pq-frame" title="Template preview" tabindex="-1"></iframe></div>
		</aside>
		</div>
	</form>
</div>
	<?php
}
