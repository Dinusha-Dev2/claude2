/* PMT Quotations: document editor */
(function ($) {
	'use strict';
	var C = window.PQ;
	if (!C) { return; }
	var cfg = C.cfg;
	var S = C.doc;
	var links = C.links || {};
	var dirty = false;
	var saving = false;
	var uidCounter = 0;
	var previewTimer = null;
	var previewReq = null;
	var toastTimer = null;
	var expiryTouched = false;
	var added = {};

	/* ------------------------------------------------------------ */
	/* Helpers                                                       */
	/* ------------------------------------------------------------ */

	function esc(s) {
		return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function num(v) {
		if (typeof v === 'number') { return isFinite(v) ? v : 0; }
		var n = parseFloat(String(v === null || v === undefined ? '' : v).replace(/,/g, ''));
		return isFinite(n) ? n : 0;
	}
	function rnd(v, d) {
		if (!isFinite(v)) { return 0; }
		var sign = v < 0 ? -1 : 1;
		v = Math.abs(v);
		var x = Math.round(parseFloat(v.toFixed(10) + 'e' + d));
		return sign * parseFloat(x + 'e-' + d);
	}
	function getPath(obj, path) {
		return path.split('.').reduce(function (o, k) { return o ? o[k] : undefined; }, obj);
	}
	function setPath(obj, path, val) {
		var parts = path.split('.');
		var last = parts.pop();
		var o = parts.reduce(function (acc, k) { if (!acc[k] || typeof acc[k] !== 'object') { acc[k] = {}; } return acc[k]; }, obj);
		o[last] = val;
	}
	function currency(code) {
		for (var i = 0; i < cfg.currencies.length; i++) {
			if (cfg.currencies[i].code === code) { return cfg.currencies[i]; }
		}
		return { code: code, symbol: code, position: 'before', space: 1 };
	}
	function decimalsFor(code) {
		for (var i = 0; i < cfg.currencies.length; i++) {
			var c = cfg.currencies[i];
			if (c.code === code && c.decimals !== '' && c.decimals !== null && c.decimals !== undefined) { return parseInt(c.decimals, 10); }
		}
		return cfg.decimals;
	}
	function numFmt(v, code) {
		var d = decimalsFor(code || S.currency);
		var parts = Math.abs(v).toFixed(d).split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, cfg.thouSep);
		return parts.join(d > 0 ? cfg.decSep : '');
	}
	function money(v, code) {
		var cur = currency(code || S.currency);
		var txt = numFmt(Math.abs(v), code || S.currency);
		var sp = cur.space ? ' ' : '';
		var out = cur.position === 'after' ? txt + sp + cur.symbol : cur.symbol + sp + txt;
		return (v < -0.0000001 ? '-' : '') + out;
	}
	function currencySymbol(code) { return currency(code || S.currency).symbol; }
	function updateCurrencyLabels() {
		var sym = currencySymbol();
		$('[data-cur-symbol]').text(sym);
		$('[data-cur-option]').text(sym);
	}
	function symbolGroup(inputHtml) {
		return '<span class="pq-inputgroup"><span class="pq-sym">' + esc(currencySymbol()) + '</span>' + inputHtml + '</span>';
	}
	function addDays(ymd, n) {
		var p = String(ymd || cfg.today).split('-');
		var d = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2] + n));
		return d.getUTCFullYear() + '-' + ('0' + (d.getUTCMonth() + 1)).slice(-2) + '-' + ('0' + d.getUTCDate()).slice(-2);
	}
	function newUid() { uidCounter += 1; return 'u' + uidCounter; }
	function typeLabel(t) { return cfg.labels[t || S.type] || cfg.types[t || S.type]; }

	/* ------------------------------------------------------------ */
	/* Totals (mirrors the server so figures match the document)     */
	/* ------------------------------------------------------------ */

	function calc() {
		var d = decimalsFor(S.currency);
		var sub = 0;
		var lines = S.items.map(function (it) {
			var qty = Math.max(0, num(it.qty));
			var price = Math.max(0, num(it.price));
			var disc = Math.min(100, Math.max(0, num(it.discount)));
			var line = rnd(qty * price * (1 - disc / 100), d);
			sub += line;
			return line;
		});
		sub = rnd(sub, d);
		var dv = Math.max(0, num(S.discount.value));
		var discAmt = S.discount.type === 'fixed' ? Math.min(sub, rnd(dv, d)) : rnd(sub * Math.min(100, dv) / 100, d);
		var ship = rnd(Math.max(0, num(S.shipping.amount)), d);
		var net = rnd(sub - discAmt, d);
		var rate = Math.max(0, num(S.tax.rate));
		var base = net + (cfg.taxShipping ? ship : 0);
		var tax, total;
		if (S.tax.mode === 'none' || rate <= 0) {
			tax = 0; total = rnd(net + ship, d);
		} else if (S.tax.mode === 'inclusive') {
			tax = rnd(base - base / (1 + rate / 100), d); total = rnd(net + ship, d);
		} else {
			tax = rnd(base * rate / 100, d); total = rnd(net + ship + tax, d);
		}
		var paid = 0;
		S.payments.forEach(function (p) { paid += Math.max(0, num(p.amount)); });
		paid = rnd(paid, d);
		return { lines: lines, subtotal: sub, discount: discAmt, shipping: ship, tax: tax, total: total, paid: paid, balance: rnd(total - paid, d) };
	}

	/* ------------------------------------------------------------ */
	/* State                                                         */
	/* ------------------------------------------------------------ */

	function prepareState() {
		S.items = (S.items || []).map(function (it) {
			it._uid = newUid();
			it.img = it.img || '';
			return it;
		});
		S.payments = S.payments || [];
		S.blocks = S.blocks || [];
		S.banks = S.banks || [];
		S.customer = S.customer || {};
		S.discount = S.discount || { type: 'percent', value: 0 };
		S.shipping = S.shipping || { amount: 0 };
		S.tax = S.tax || { label: '', rate: 0, mode: 'exclusive' };
		S.customer.currency = S.customer.currency || '';
		expiryTouched = !!S.id;
	}

	function payload() {
		var d = $.extend(true, {}, S);
		d.items = S.items.map(function (it) {
			return { pid: it.pid, name: it.name, desc: it.desc, sku: it.sku, img_id: it.img_id, qty: it.qty, unit: it.unit, price: it.price, discount: it.discount };
		});
		return JSON.stringify(d);
	}

	function changed() {
		dirty = true;
		$('#pq-dirty').prop('hidden', false);
		renderSummary();
		schedulePreview();
	}

	/* ------------------------------------------------------------ */
	/* Notifications                                                 */
	/* ------------------------------------------------------------ */

	function toast(msg, kind) {
		var $t = $('#pq-toast');
		$t.removeClass('is-error is-ok').addClass(kind === 'error' ? 'is-error' : 'is-ok').text(msg).prop('hidden', false);
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { $t.prop('hidden', true); }, kind === 'error' ? 9000 : 3500);
	}

	function post(action, data) {
		return $.post(C.ajax, $.extend({ action: action, nonce: C.nonce }, data || {}));
	}
	function failMessage(xhr) {
		if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) { return xhr.responseJSON.data.message; }
		return 'Something went wrong. Check your connection and try again.';
	}

	/* ------------------------------------------------------------ */
	/* Bound fields                                                  */
	/* ------------------------------------------------------------ */

	function hydrate() {
		$('#pq-form [data-bind]').each(function () {
			var v = getPath(S, this.getAttribute('data-bind'));
			if (this.type === 'checkbox') { this.checked = !!v; } else { this.value = (v === null || v === undefined) ? '' : v; }
		});
	}

	function buildStatuses() {
		var list = cfg.statuses[S.type] || {};
		var html = '';
		Object.keys(list).forEach(function (k) { html += '<option value="' + esc(k) + '">' + esc(list[k]) + '</option>'; });
		var $sel = $('[data-bind="status"]').html(html);
		if (!list[S.status]) { S.status = 'draft'; }
		$sel.val(S.status);
	}

	function applyType() {
		var isQuote = S.type === 'quotation';
		$('[data-expiry-label]').text(isQuote ? 'Valid until' : 'Due date');
		$('#pq-pay-card').prop('hidden', isQuote);
		$('[data-only]').each(function () { $(this).prop('hidden', $(this).attr('data-only') !== S.type); });
		buildStatuses();
		renderBar();
	}

	$('#pq-form').on('input change', '[data-bind]', function () {
		var path = this.getAttribute('data-bind');
		var v = this.type === 'checkbox' ? this.checked : this.value;
		if (path === 'currency') { onCurrencyChange(v); return; }
		setPath(S, path, v);
		if (path === 'type') {
			if (!S.id) {
				if (S.number_auto && cfg.nextNumbers[S.type]) { S.number = cfg.nextNumbers[S.type]; $('[data-bind="number"]').val(S.number); }
				if (!expiryTouched) { S.expiry_date = addDays(S.issue_date, S.type === 'quotation' ? cfg.validDays : cfg.dueDays); $('[data-bind="expiry_date"]').val(S.expiry_date); }
			}
			applyType();
		} else if (path === 'number') {
			S.number_auto = false;
		} else if (path === 'expiry_date') {
			expiryTouched = true;
		} else if (path === 'issue_date' && !expiryTouched && S.issue_date) {
			S.expiry_date = addDays(S.issue_date, S.type === 'quotation' ? cfg.validDays : cfg.dueDays);
			$('[data-bind="expiry_date"]').val(S.expiry_date);
		} else if (path === 'status') {
			renderBar();
		}
		changed();
	});

	/* ------------------------------------------------------------ */
	/* Top bar                                                       */
	/* ------------------------------------------------------------ */

	function displayStatus() {
		var list = cfg.statuses[S.type] || {};
		var st = S.status;
		var today = cfg.today;
		if (S.type === 'quotation' && st === 'sent' && S.expiry_date && S.expiry_date < today) { return ['expired', 'Expired']; }
		if (S.type !== 'quotation' && (st === 'sent' || st === 'partial') && S.expiry_date && S.expiry_date < today && calc().balance > 0) { return ['overdue', 'Overdue']; }
		return [st, list[st] || st];
	}

	function renderBar() {
		var title = S.id ? typeLabel() + ' ' + (S.number || '') : 'New ' + typeLabel().toLowerCase();
		$('#pq-title').text(title.trim());
		var ds = displayStatus();
		$('#pq-badge').attr('class', 'pq-badge pq-st-' + ds[0]).text(ds[1]);
		var saved = !!S.id;
		$('[data-needs-saved]').each(function () {
			if (this.tagName === 'DETAILS') { $(this).toggleClass('is-disabled', !saved); } else { $(this).prop('disabled', !saved); }
		});
		document.title = title.trim() + ' - Quotations';
	}

	function renderLinks() {
		var rows = [];
		if (links.source) { rows.push('From <a href="' + esc(links.source) + '">' + esc(S.source_number || 'quotation') + '</a>'); }
		if (links.converted) { rows.push('Converted to <a href="' + esc(links.converted) + '">' + esc(links.converted_number || 'document') + '</a>'); }
		if (links.rfq) { rows.push('Made from a <a href="' + esc(links.rfq) + '">bulk order request</a>'); }
		if (links.sent_log && links.sent_log.length) { rows.push('Emailed ' + esc(links.sent_log[0])); }
		if (links.viewed) { rows.push('Opened by customer ' + esc(links.viewed)); }
		$('#pq-links').html(rows.join(' <span class="pq-sep">|</span> ')).prop('hidden', !rows.length);
	}

	/* ------------------------------------------------------------ */
	/* Customer                                                      */
	/* ------------------------------------------------------------ */

	var custTimer = null;
	function renderCustomerLink() {
		var $b = $('#pq-cust-linked');
		if (S.customer && S.customer.id) {
			$b.html('Saved customer. Changes you make here update their saved details. <button type="button" class="button-link" data-act="unlink-customer">Treat as a different customer</button>').prop('hidden', false);
		} else {
			$b.prop('hidden', true).empty();
		}
	}

	function searchCustomers(q) {
		post('pq_customers', { q: q }).done(function (res) {
			var items = (res && res.success && res.data.items) || [];
			var $r = $('#pq-cust-results');
			if (!items.length) {
				$r.html('<div class="pq-result-empty">' + (q ? 'No saved customer matches. Fill in the details below and they will be saved with this document.' : 'No saved customers yet.') + '</div>').prop('hidden', false);
				return;
			}
			var html = '';
			items.forEach(function (c, i) {
				html += '<button type="button" class="pq-result" data-i="' + i + '"><strong>' + esc(c.label) + '</strong><span>' + esc(c.sub) + '</span></button>';
			});
			$r.html(html).data('items', items).prop('hidden', false);
		});
	}

	$('#pq-cust-search').on('input focus', function () {
		var q = $.trim(this.value);
		clearTimeout(custTimer);
		custTimer = setTimeout(function () { searchCustomers(q); }, 220);
	});
	$(document).on('click', function (e) {
		if (!$(e.target).closest('.pq-search').length) { $('#pq-cust-results').prop('hidden', true); }
	});
	$('#pq-cust-results').on('click', '.pq-result', function () {
		var items = $('#pq-cust-results').data('items') || [];
		var c = items[+$(this).attr('data-i')];
		if (!c) { return; }
		S.customer = { id: c.id, name: c.name, company: c.company, email: c.email, phone: c.phone, address: c.address, city: c.city, postcode: c.postcode, country: c.country, tax_no: c.tax_no, currency: c.currency || '' };
		S.save_customer = true;
		if (S.customer.currency && S.customer.currency !== S.currency && !hasMoney()) {
			S.currency = S.customer.currency;
			updateCurrencyLabels();
			renderItems();
		}
		hydrate();
		renderCustomerLink();
		$('#pq-cust-results').prop('hidden', true);
		$('#pq-cust-search').val('');
		changed();
	});

	/* ------------------------------------------------------------ */
	/* Items                                                         */
	/* ------------------------------------------------------------ */

	function blankItem() {
		return { _uid: newUid(), pid: 0, name: '', desc: '', sku: '', img_id: 0, img: '', qty: 1, unit: cfg.defaultUnit, price: '', discount: 0 };
	}

	function thumbHtml(it) {
		if (it.img) {
			return '<img src="' + esc(it.img) + '" alt=""><button type="button" class="pq-thumb-x" data-act="img-remove" aria-label="Remove image">&times;</button>';
		}
		return '<span class="pq-thumb-add">Add image</span>';
	}

	function itemHtml(it, tot) {
		return '<div class="pq-item" data-uid="' + it._uid + '">' +
			'<span class="pq-handle" title="Drag to reorder" aria-hidden="true"></span>' +
			'<div class="pq-thumb" data-act="img-pick" role="button" tabindex="0" aria-label="Choose image for this item">' + thumbHtml(it) + '</div>' +
			'<div class="pq-item-main">' +
				'<input type="text" class="pq-i-name" data-f="name" value="' + esc(it.name) + '" placeholder="Item name" aria-label="Item name">' +
				'<textarea class="pq-i-desc" rows="2" data-f="desc" placeholder="Description (optional)" aria-label="Description">' + esc(it.desc) + '</textarea>' +
				'<div class="pq-item-meta"><input type="text" data-f="sku" value="' + esc(it.sku) + '" placeholder="SKU" aria-label="SKU">' +
				(it.pid ? '<span class="pq-pill">From store</span>' : '<span class="pq-pill pq-pill-custom">Custom item</span>') + '</div>' +
			'</div>' +
			'<div class="pq-item-nums">' +
				'<label>Qty<input type="number" min="0" step="any" data-f="qty" value="' + esc(it.qty) + '"></label>' +
				'<label>Unit<input type="text" list="pq-units" data-f="unit" value="' + esc(it.unit) + '"></label>' +
				'<label>Price' + symbolGroup('<input type="number" min="0" step="any" data-f="price" value="' + esc(it.price) + '" placeholder="0.00">') + '</label>' +
				'<label>Disc. %<input type="number" min="0" max="100" step="any" data-f="discount" value="' + esc(it.discount) + '"></label>' +
				'<div class="pq-line-total"><span>Amount</span><strong data-total>' + esc(money(tot)) + '</strong></div>' +
			'</div>' +
			'<div class="pq-item-tools">' +
				'<button type="button" class="pq-ico" data-act="item-up" aria-label="Move up" title="Move up">&uarr;</button>' +
				'<button type="button" class="pq-ico" data-act="item-down" aria-label="Move down" title="Move down">&darr;</button>' +
				'<button type="button" class="pq-ico pq-ico-del" data-act="item-del" aria-label="Remove item" title="Remove item">&times;</button>' +
			'</div>' +
		'</div>';
	}

	function renderItems() {
		var $list = $('#pq-items');
		if (!$('#pq-units').length) {
			var dl = '<datalist id="pq-units">';
			cfg.units.forEach(function (u) { dl += '<option value="' + esc(u) + '">'; });
			$('body').append(dl + '</datalist>');
		}
		var lines = calc().lines;
		$list.html(S.items.map(function (it, i) { return itemHtml(it, lines[i] || 0); }).join(''));
		$('#pq-items-empty').prop('hidden', S.items.length > 0);
		if ($list.data('ui-sortable') === undefined && $.fn.sortable) {
			$list.sortable({
				handle: '.pq-handle',
				items: '.pq-item',
				axis: 'y',
				tolerance: 'pointer',
				update: function () {
					var order = $list.children('.pq-item').map(function () { return $(this).attr('data-uid'); }).get();
					S.items.sort(function (a, b) { return order.indexOf(a._uid) - order.indexOf(b._uid); });
					changed();
					renderItems();
				}
			});
		}
		renderSummary();
	}

	function itemByUid(uid) {
		for (var i = 0; i < S.items.length; i++) { if (S.items[i]._uid === uid) { return i; } }
		return -1;
	}

	function refreshLineTotals() {
		var lines = calc().lines;
		$('#pq-items .pq-item').each(function (i) { $(this).find('[data-total]').text(money(lines[i] || 0)); });
	}

	$('#pq-items').on('input change', '[data-f]', function () {
		var i = itemByUid($(this).closest('.pq-item').attr('data-uid'));
		if (i < 0) { return; }
		S.items[i][this.getAttribute('data-f')] = this.value;
		refreshLineTotals();
		changed();
	});

	function pickImage(done) {
		if (!window.wp || !wp.media) { toast('The media library is not available on this page.', 'error'); return; }
		var frame = wp.media({ title: 'Choose item image', button: { text: 'Use this image' }, library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var url = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
			done(a.id, url);
		});
		frame.open();
	}

	$('#pq-items').on('click keydown', '[data-act="img-pick"]', function (e) {
		if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; }
		if ($(e.target).closest('[data-act="img-remove"]').length) { return; }
		e.preventDefault();
		var uid = $(this).closest('.pq-item').attr('data-uid');
		pickImage(function (id, url) {
			var i = itemByUid(uid);
			if (i < 0) { return; }
			S.items[i].img_id = id;
			S.items[i].img = url;
			$('.pq-item[data-uid="' + uid + '"] .pq-thumb').html(thumbHtml(S.items[i]));
			changed();
		});
	});

	$('#pq-items').on('click', '[data-act]', function (e) {
		var act = $(this).attr('data-act');
		var $row = $(this).closest('.pq-item');
		var i = itemByUid($row.attr('data-uid'));
		if (i < 0 || act === 'img-pick') { return; }
		e.preventDefault();
		e.stopPropagation();
		if (act === 'img-remove') {
			S.items[i].img_id = 0; S.items[i].img = '';
			$row.find('.pq-thumb').html(thumbHtml(S.items[i]));
		} else if (act === 'item-del') {
			S.items.splice(i, 1);
			renderItems();
		} else if (act === 'item-up' && i > 0) {
			S.items.splice(i - 1, 0, S.items.splice(i, 1)[0]);
			renderItems();
		} else if (act === 'item-down' && i < S.items.length - 1) {
			S.items.splice(i + 1, 0, S.items.splice(i, 1)[0]);
			renderItems();
		}
		changed();
	});

	function addCustomItem() {
		var it = blankItem();
		S.items.push(it);
		renderItems();
		changed();
		$('.pq-item[data-uid="' + it._uid + '"] .pq-i-name').trigger('focus');
	}

	/* ------------------------------------------------------------ */
	/* Store product picker                                          */
	/* ------------------------------------------------------------ */

	var prod = { q: '', cat: 0, page: 1, pages: 1, loading: false, catsLoaded: false, timer: null, token: 0 };

	function openModal(id) {
		$(id).prop('hidden', false);
		$('body').addClass('pq-noscroll');
	}
	function closeModals() {
		$('.pq-modal').prop('hidden', true);
		$('body').removeClass('pq-noscroll');
	}
	$(document).on('keydown', function (e) { if (e.key === 'Escape') { closeModals(); } });
	$(document).on('click', '.pq-modal', function (e) { if (e.target === this) { closeModals(); } });

	function storePriceFor(p) {
		if (p.price === '' || p.price === null || p.price === undefined) { return ''; }
		if (cfg.storeCurrency && cfg.storeCurrency === S.currency) { return rnd(num(p.price), decimalsFor(S.currency)); }
		return '';
	}

	function productCard(p) {
		var priceTxt = 'No store price';
		if (p.price !== '' && cfg.storeCurrency) { priceTxt = 'Store price ' + cfg.storeCurrency + ' ' + numFmt(num(p.price), cfg.storeCurrency); }
		var start = storePriceFor(p);
		var n = added[p.id] || 0;
		return '<div class="pq-prod" data-id="' + p.id + '">' +
			'<div class="pq-prod-img">' + (p.thumb ? '<img src="' + esc(p.thumb) + '" alt="" loading="lazy">' : '<span>No image</span>') + '</div>' +
			'<div class="pq-prod-body"><strong>' + esc(p.name) + '</strong>' +
			'<span class="description">' + esc(p.sku ? 'SKU ' + p.sku : p.cat) + '</span>' +
			'<span class="description">' + esc(priceTxt) + '</span></div>' +
			'<div class="pq-prod-inputs">' +
				'<label>Qty<input type="number" class="pq-prod-qty" min="0" step="any" value="1"></label>' +
				'<label>Price' + symbolGroup('<input type="number" class="pq-prod-price" min="0" step="any" value="' + esc(start) + '" placeholder="0.00">') + '</label>' +
			'</div>' +
			'<button type="button" class="button" data-add>' + (n ? 'Add again (' + n + ')' : 'Add') + '</button></div>';
	}

	var prodItems = {};
	function loadProducts(reset) {
		if (!reset && prod.loading) { return; }
		if (reset) { prod.page = 1; $('#pq-prod-list').empty(); prodItems = {}; $('#pq-prod-more').prop('hidden', true); }
		var token = ++prod.token;
		prod.loading = true;
		$('#pq-prod-msg').text('Loading products...');
		post('pq_products', { q: prod.q, cat: prod.cat, page: prod.page, with_cats: prod.catsLoaded ? 0 : 1 }).done(function (res) {
			if (token !== prod.token) { return; }
			prod.loading = false;
			if (!res || !res.success) { $('#pq-prod-msg').text('Products could not be loaded.'); return; }
			var d = res.data;
			if (!prod.catsLoaded) {
				prod.catsLoaded = true;
				var opts = '<option value="0">All categories</option>';
				(d.cats || []).forEach(function (c) { opts += '<option value="' + c.id + '">' + esc(c.name) + '</option>'; });
				$('#pq-prod-cat').html(opts).val(String(prod.cat));
			}
			var html = '';
			d.items.forEach(function (p) { prodItems[p.id] = p; html += productCard(p); });
			$('#pq-prod-list').append(html);
			prod.pages = d.pages;
			$('#pq-prod-more').prop('hidden', prod.page >= d.pages);
			$('#pq-prod-msg').text(d.total ? '' : 'No products match your search.');
		}).fail(function (xhr) {
			if (token !== prod.token) { return; }
			prod.loading = false;
			$('#pq-prod-msg').text(failMessage(xhr));
		});
	}

	function addProduct(p, qty, price) {
		var it = blankItem();
		it.pid = p.id;
		it.name = p.name;
		it.desc = p.desc || '';
		it.sku = p.sku || '';
		it.img_id = p.img_id || 0;
		it.img = p.thumb || '';
		it.qty = (qty === undefined || qty === '') ? 1 : qty;
		it.price = (price === undefined) ? storePriceFor(p) : price;
		S.items.push(it);
		added[p.id] = (added[p.id] || 0) + 1;
		renderItems();
		changed();
		var total = 0;
		Object.keys(added).forEach(function (k) { total += added[k]; });
		$('#pq-prod-added').text(total + (total === 1 ? ' item added' : ' items added'));
		$('.pq-prod[data-id="' + p.id + '"] [data-add]').text('Add again (' + added[p.id] + ')');
	}

	$('#pq-prod-list').on('click', '[data-add]', function () {
		var $card = $(this).closest('.pq-prod');
		var p = prodItems[$card.attr('data-id')];
		if (p) { addProduct(p, $card.find('.pq-prod-qty').val(), $card.find('.pq-prod-price').val()); }
	});
	$('#pq-prod-q').on('input', function () {
		var v = $.trim(this.value);
		clearTimeout(prod.timer);
		prod.timer = setTimeout(function () { prod.q = v; loadProducts(true); }, 300);
	});
	$('#pq-prod-cat').on('change', function () { prod.cat = +this.value; loadProducts(true); });
	$('#pq-prod-more').on('click', function () { prod.page += 1; loadProducts(false); });

	function openProducts() {
		if (!cfg.hasStore) { toast('WooCommerce products were not found on this site. Use "Add custom item" instead.', 'error'); return; }
		openModal('#pq-m-products');
		$('#pq-prod-q').trigger('focus');
		if (!prod.catsLoaded) { loadProducts(true); }
	}

	/* ------------------------------------------------------------ */
	/* Currency change and price tools                               */
	/* ------------------------------------------------------------ */

	var lastSnap = null;

	function snapshot() {
		lastSnap = JSON.stringify({ items: S.items, discount: S.discount, shipping: S.shipping, payments: S.payments });
		$('[data-act="undo-prices"]').prop('hidden', false);
	}
	function undoPrices() {
		if (!lastSnap) { return; }
		var snap = JSON.parse(lastSnap);
		S.items = snap.items; S.discount = snap.discount; S.shipping = snap.shipping; S.payments = snap.payments;
		lastSnap = null;
		$('[data-act="undo-prices"]').prop('hidden', true);
		afterPriceChange();
		toast('Price change undone');
	}
	function afterPriceChange() {
		updateCurrencyLabels();
		hydrate();
		renderItems();
		renderPayments();
		renderSummary();
		renderBar();
		changed();
	}

	function hasMoney() {
		if (num(S.shipping.amount) > 0) { return true; }
		if (S.discount.type === 'fixed' && num(S.discount.value) > 0) { return true; }
		if (S.items.some(function (it) { return num(it.price) > 0; })) { return true; }
		return S.payments.some(function (p) { return num(p.amount) > 0; });
	}

	/** Changing the currency only changes the symbol. Prices are never converted. */
	function onCurrencyChange(to) {
		if (to === S.currency) { return; }
		S.currency = to;
		updateCurrencyLabels();
		renderItems();
		renderPayments();
		renderSummary();
		renderBar();
		changed();
	}

	function ptSync() {
		var a = $('#pq-pt-action').val();
		$('#pq-pt-value-wrap').prop('hidden', a === 'round' || a === 'clear-discount');
		$('#pq-pt-round-wrap').prop('hidden', a !== 'round');
		var priced = S.items.filter(function (it) { return String(it.price) !== ''; }).length;
		var info = { raise: 'Applies to the ' + priced + ' items that have a price.', lower: 'Applies to the ' + priced + ' items that have a price.', discount: 'Sets the discount on all ' + S.items.length + ' items.', round: 'Applies to the ' + priced + ' items that have a price.', 'clear-discount': 'Sets every line discount to zero.' };
		$('#pq-pt-info').text(info[a] || '');
		$('#pq-pt-msg').text('');
	}
	$('#pq-pt-action').on('change', ptSync);

	$('#pq-pt-apply').on('click', function () {
		var a = $('#pq-pt-action').val();
		var pct = num($('#pq-pt-value').val());
		var d = decimalsFor(S.currency);
		if (!S.items.length) { $('#pq-pt-msg').text('Add some items first.'); return; }
		if ((a === 'raise' || a === 'lower' || a === 'discount') && (pct < 0 || (a !== 'raise' && pct > 100))) { $('#pq-pt-msg').text(a === 'raise' ? 'Enter zero or more.' : 'Enter a percentage from 0 to 100.'); return; }
		snapshot();
		var n = 0;
		S.items.forEach(function (it) {
			var has = String(it.price) !== '';
			if (a === 'discount') { it.discount = String(pct); n++; }
			else if (a === 'clear-discount') { it.discount = 0; n++; }
			else if (has && a === 'raise') { it.price = String(rnd(num(it.price) * (1 + pct / 100), d)); n++; }
			else if (has && a === 'lower') { it.price = String(rnd(num(it.price) * (1 - pct / 100), d)); n++; }
			else if (has && a === 'round') {
				var step = $('#pq-pt-round').val();
				var v = num(it.price);
				var r = step === 'up1' ? Math.ceil(v) : Math.round(v / parseFloat(step)) * parseFloat(step);
				it.price = String(rnd(r, d)); n++;
			}
		});
		closeModals();
		afterPriceChange();
		toast('Updated ' + n + (n === 1 ? ' line' : ' lines'));
	});

	/* ------------------------------------------------------------ */
	/* Terms, banks, payments, summary                               */
	/* ------------------------------------------------------------ */

	function renderBlocks() {
		var html = '';
		S.blocks.forEach(function (b, i) {
			html += '<div class="pq-block" data-i="' + i + '">' +
				'<div class="pq-block-top"><input type="text" data-b="title" value="' + esc(b.title) + '" placeholder="Heading" aria-label="Heading">' +
				'<select data-b="style" aria-label="Style"><option value="">Standard</option><option value="highlight"' + (b.style === 'highlight' ? ' selected' : '') + '>Highlighted</option></select>' +
				'<button type="button" class="pq-ico" data-act="block-up" aria-label="Move up">&uarr;</button>' +
				'<button type="button" class="pq-ico" data-act="block-down" aria-label="Move down">&darr;</button>' +
				'<button type="button" class="pq-ico pq-ico-del" data-act="block-del" aria-label="Remove block">&times;</button></div>' +
				'<textarea rows="3" data-b="body" placeholder="Text" aria-label="Text">' + esc(b.body) + '</textarea></div>';
		});
		$('#pq-blocks').html(html);
		$('#pq-blocks-empty').prop('hidden', S.blocks.length > 0);
	}

	$('#pq-blocks').on('input change', '[data-b]', function () {
		var i = +$(this).closest('.pq-block').attr('data-i');
		S.blocks[i][this.getAttribute('data-b')] = this.value;
		changed();
	});
	$('#pq-blocks').on('click', '[data-act]', function (e) {
		e.preventDefault();
		var act = $(this).attr('data-act');
		var i = +$(this).closest('.pq-block').attr('data-i');
		if (act === 'block-del') { S.blocks.splice(i, 1); }
		else if (act === 'block-up' && i > 0) { S.blocks.splice(i - 1, 0, S.blocks.splice(i, 1)[0]); }
		else if (act === 'block-down' && i < S.blocks.length - 1) { S.blocks.splice(i + 1, 0, S.blocks.splice(i, 1)[0]); }
		renderBlocks();
		changed();
	});

	function renderBanks() {
		var html = '';
		cfg.banks.forEach(function (b) {
			html += '<label class="pq-check"><input type="checkbox" data-bank="' + esc(b.id) + '"' + (S.banks.indexOf(b.id) > -1 ? ' checked' : '') + '> ' + esc(b.label || b.id) + '</label>';
		});
		$('#pq-banks').html(html || '<p class="description">No bank accounts set up yet.</p>');
	}
	$('#pq-banks').on('change', '[data-bank]', function () {
		var id = $(this).attr('data-bank');
		var at = S.banks.indexOf(id);
		if (this.checked && at < 0) { S.banks.push(id); }
		if (!this.checked && at > -1) { S.banks.splice(at, 1); }
		changed();
	});

	function renderPayments() {
		var html = '';
		S.payments.forEach(function (p, i) {
			html += '<div class="pq-pay" data-i="' + i + '">' +
				'<input type="date" data-p="date" value="' + esc(p.date) + '" aria-label="Date">' +
				symbolGroup('<input type="number" min="0" step="any" data-p="amount" value="' + esc(p.amount) + '" placeholder="Amount" aria-label="Amount">') +
				'<input type="text" list="pq-methods" data-p="method" value="' + esc(p.method) + '" placeholder="Method" aria-label="Method">' +
				'<input type="text" data-p="note" value="' + esc(p.note) + '" placeholder="Note (optional)" aria-label="Note">' +
				'<button type="button" class="pq-ico pq-ico-del" data-act="pay-del" aria-label="Remove payment">&times;</button></div>';
		});
		if (!$('#pq-methods').length) {
			$('body').append('<datalist id="pq-methods"><option value="Bank transfer"><option value="Cash"><option value="Card"><option value="Cheque"><option value="Online"></datalist>');
		}
		$('#pq-payments').html(html);
		$('#pq-pay-empty').prop('hidden', S.payments.length > 0);
	}
	$('#pq-payments').on('input change', '[data-p]', function () {
		var i = +$(this).closest('.pq-pay').attr('data-i');
		S.payments[i][this.getAttribute('data-p')] = this.value;
		changed();
	});
	$('#pq-payments').on('click', '[data-act="pay-del"]', function (e) {
		e.preventDefault();
		S.payments.splice(+$(this).closest('.pq-pay').attr('data-i'), 1);
		renderPayments();
		changed();
	});

	function renderSummary() {
		var c = calc();
		var rows = '<dt>Subtotal</dt><dd>' + esc(money(c.subtotal)) + '</dd>';
		if (c.discount > 0) { rows += '<dt>Discount</dt><dd>-' + esc(money(c.discount)) + '</dd>'; }
		if (c.shipping > 0) { rows += '<dt>Delivery</dt><dd>' + esc(money(c.shipping)) + '</dd>'; }
		if (S.tax.mode !== 'none' && num(S.tax.rate) > 0) {
			rows += '<dt>' + esc(S.tax.label || 'Tax') + ' (' + esc(String(num(S.tax.rate))) + '%' + (S.tax.mode === 'inclusive' ? ' included' : '') + ')</dt><dd>' + esc(money(c.tax)) + '</dd>';
		}
		rows += '<dt class="pq-sum-total">Total</dt><dd class="pq-sum-total">' + esc(money(c.total)) + '</dd>';
		if (S.type !== 'quotation' && c.paid > 0) {
			rows += '<dt>Paid</dt><dd>-' + esc(money(c.paid)) + '</dd><dt class="pq-sum-due">Balance due</dt><dd class="pq-sum-due">' + esc(money(c.balance)) + '</dd>';
		}
		$('#pq-summary').html('<dl>' + rows + '</dl>');
	}

	/* ------------------------------------------------------------ */
	/* Live preview                                                  */
	/* ------------------------------------------------------------ */

	function fitPreview() {
		var frame = document.getElementById('pq-frame');
		var wrap = document.getElementById('pq-preview-wrap');
		if (!frame || !wrap) { return; }
		var W = 840;
		var scale = Math.min(1, wrap.clientWidth / W) || 1;
		var h = 1123;
		try { h = Math.max(h, frame.contentDocument.documentElement.scrollHeight); } catch (e) { /* ignore */ }
		frame.style.width = W + 'px';
		frame.style.height = h + 'px';
		frame.style.transform = 'scale(' + scale + ')';
		wrap.style.height = Math.ceil(h * scale) + 'px';
	}

	function schedulePreview(delay) {
		clearTimeout(previewTimer);
		previewTimer = setTimeout(refreshPreview, delay === undefined ? 650 : delay);
	}

	function refreshPreview() {
		if (previewReq && previewReq.abort) { previewReq.abort(); }
		var frame = document.getElementById('pq-frame');
		previewReq = post('pq_preview', { doc: payload() }).done(function (res) {
			if (!res || !res.success) { return; }
			var top = 0;
			var holder = document.querySelector('.pq-preview');
			if (holder) { top = holder.scrollTop; }
			frame.onload = function () {
				fitPreview();
				if (holder) { holder.scrollTop = top; }
			};
			frame.srcdoc = res.data.html;
		});
	}
	$(window).on('resize', fitPreview);

	/* ------------------------------------------------------------ */
	/* Save and actions                                              */
	/* ------------------------------------------------------------ */

	function adopt(doc) {
		S = doc;
		prepareState();
		expiryTouched = true;
		hydrate();
		updateCurrencyLabels();
		buildStatuses();
		applyType();
		renderCustomerLink();
		renderItems();
		renderBlocks();
		renderBanks();
		renderPayments();
		renderSummary();
		renderBar();
	}

	function save(done) {
		if (saving) { return; }
		saving = true;
		var $btn = $('[data-act="save"]').prop('disabled', true).text('Saving...');
		post('pq_save', { doc: payload() }).done(function (res) {
			if (!res || !res.success) {
				toast((res && res.data && res.data.message) || 'The document could not be saved.', 'error');
				return;
			}
			var d = res.data;
			links = d.links || links;
			adopt(d.doc);
			dirty = false;
			$('#pq-dirty').prop('hidden', true);
			if (window.history && history.replaceState) { history.replaceState(null, '', d.edit); }
			renderLinks();
			toast('Saved');
			schedulePreview(0);
			if (typeof done === 'function') { done(); }
		}).fail(function (xhr) {
			toast(failMessage(xhr), 'error');
		}).always(function () {
			saving = false;
			$btn.prop('disabled', false).text('Save');
		});
	}

	function ensureSaved(next) {
		if (!S.id || dirty) { save(next); } else { next(); }
	}

	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text).then(function () { toast('Link copied'); }, function () { window.prompt('Copy this link:', text); });
		}
		window.prompt('Copy this link:', text);
	}

	function openEmail() {
		openModal('#pq-m-email');
		$('#pq-em-msg').text('Loading...');
		post('pq_email_defaults', { id: S.id }).done(function (res) {
			if (res && res.success) {
				$('#pq-em-to').val(res.data.to);
				$('#pq-em-subject').val(res.data.subject);
				$('#pq-em-body').val(res.data.body);
				$('#pq-em-msg').text('');
			} else {
				$('#pq-em-msg').text((res && res.data && res.data.message) || 'Could not load the email.');
			}
		});
	}

	$('#pq-em-send').on('click', function () {
		var $b = $(this).prop('disabled', true);
		$('#pq-em-msg').text('Sending...');
		post('pq_send', {
			id: S.id, to: $('#pq-em-to').val(), subject: $('#pq-em-subject').val(), body: $('#pq-em-body').val(),
			copy: $('#pq-em-copy').is(':checked') ? 1 : 0
		}).done(function (res) {
			if (res && res.success) {
				links = res.data.links || links;
				S.status = res.data.doc.status;
				hydrate();
				renderBar();
				renderLinks();
				closeModals();
				toast('Email sent');
			} else {
				$('#pq-em-msg').text((res && res.data && res.data.message) || 'The email could not be sent.');
			}
		}).fail(function (xhr) {
			$('#pq-em-msg').text(failMessage(xhr));
		}).always(function () { $b.prop('disabled', false); });
	});

	$(document).on('click', '[data-act]', function (e) {
		var act = $(this).attr('data-act');
		var inItems = $(this).closest('#pq-items, #pq-blocks, #pq-payments').length;
		if (inItems) { return; }
		switch (act) {
			case 'save': e.preventDefault(); save(); break;
			case 'print': e.preventDefault(); ensureSaved(function () { window.open(links.public, '_blank', 'noopener'); }); break;
			case 'email': e.preventDefault(); ensureSaved(openEmail); break;
			case 'copy': e.preventDefault(); ensureSaved(function () { copyText(links.public); }); break;
			case 'refresh': e.preventDefault(); refreshPreview(); break;
			case 'add-custom': e.preventDefault(); addCustomItem(); break;
			case 'price-tools': e.preventDefault(); ptSync(); openModal('#pq-m-prices'); break;
			case 'undo-prices': e.preventDefault(); undoPrices(); break;
			case 'add-store': e.preventDefault(); openProducts(); break;
			case 'close-modal': e.preventDefault(); closeModals(); break;
			case 'add-block':
				e.preventDefault();
				S.blocks.push({ title: '', body: '', style: '' });
				renderBlocks(); changed();
				$('#pq-blocks .pq-block:last [data-b="title"]').trigger('focus');
				break;
			case 'reset-blocks':
				e.preventDefault();
				if (!S.blocks.length || window.confirm('Replace the terms on this document with your default terms?')) {
					S.blocks = $.extend(true, [], cfg.defaultBlocks);
					renderBlocks(); changed();
				}
				break;
			case 'add-payment':
				e.preventDefault();
				var bal = calc().balance;
				S.payments.push({ date: cfg.today, amount: bal > 0 ? bal : '', method: '', note: '' });
				renderPayments(); changed();
				break;
			case 'unlink-customer':
				e.preventDefault();
				S.customer.id = 0;
				renderCustomerLink(); changed();
				break;
			case 'convert':
				e.preventDefault();
				if (window.confirm('Create ' + (($(this).attr('data-to') === 'invoice') ? 'an invoice' : 'a proforma invoice') + ' from this quotation? The quotation will be marked as converted.')) {
					var to = $(this).attr('data-to');
					ensureSaved(function () { window.location.href = links[to]; });
				}
				break;
			case 'duplicate':
				e.preventDefault();
				ensureSaved(function () { window.location.href = links.duplicate; });
				break;
			case 'trash':
				e.preventDefault();
				if (window.confirm('Move this document to the trash?')) {
					dirty = false;
					window.location.href = links.trash;
				}
				break;
		}
		if ($(this).closest('.pq-more').length) { $(this).closest('.pq-more').removeAttr('open'); }
	});

	$(document).on('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) { e.preventDefault(); save(); }
	});
	$(window).on('beforeunload', function () { if (dirty) { return 'You have unsaved changes.'; } });

	/* ------------------------------------------------------------ */
	/* Start                                                         */
	/* ------------------------------------------------------------ */

	prepareState();
	updateCurrencyLabels();
	hydrate();
	applyType();
	renderCustomerLink();
	renderItems();
	renderBlocks();
	renderBanks();
	renderPayments();
	renderSummary();
	renderBar();
	renderLinks();
	if (C.notice === 'duplicated') { toast('Duplicated. This is a new draft.'); }
	if (C.notice === 'converted') { toast('Created from your quotation. Review it, then save.'); }
	schedulePreview(100);
}(jQuery));
