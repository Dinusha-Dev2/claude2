/* PMT Quotations: settings page */
(function ($) {
	'use strict';
	var P = window.PQS;
	if (!P) { return; }
	var $form = $('#pq-settings-form');
	var timer = null;
	var req = null;

	/* Tabs */
	function showTab(name) {
		var $tab = $('.pq-tab[data-tab="' + name + '"]');
		if (!$tab.length) { name = 'business'; $tab = $('.pq-tab[data-tab="business"]'); }
		$('.pq-tab').removeClass('is-active').attr('aria-selected', 'false');
		$tab.addClass('is-active').attr('aria-selected', 'true');
		$('.pq-pane').prop('hidden', true);
		$('.pq-pane[data-pane="' + name + '"]').prop('hidden', false);
		$('#pq-set-preview').toggle($tab.attr('data-preview') === '1');
		$('.pq-set-cols').toggleClass('no-preview', $tab.attr('data-preview') !== '1');
		try { window.sessionStorage.setItem('pqTab', name); } catch (e) { /* ignore */ }
		fit();
	}
	$('.pq-tab').on('click', function () { showTab($(this).attr('data-tab')); });

	/* Repeaters */
	var seq = 0;
	function uniq() { seq += 1; return String(Date.now()) + seq; }
	$(document).on('click', '.pq-rep-add', function () {
		var $rep = $(this).closest('.pq-rep');
		var html = $rep.find('.pq-rep-tpl').html().replace(/__I__/g, uniq());
		$rep.find('.pq-rep-rows').append(html);
		$rep.find('.pq-rep-row:last input[type="text"], .pq-rep-row:last textarea').first().trigger('focus');
		schedule();
	});
	$(document).on('click', '.pq-rep-del', function () {
		$(this).closest('.pq-rep-row').remove();
		schedule();
	});

	/* Logo */
	$(document).on('click', '.pq-pick-logo', function () {
		var $wrap = $(this).closest('.pq-logo-pick');
		var frame = wp.media({ title: 'Choose your logo', button: { text: 'Use this logo' }, library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var url = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
			$wrap.find('.pq-logo-id').val(a.id);
			$wrap.find('.pq-logo-prev').html($('<img>', { src: url, alt: '' }));
			$wrap.find('.pq-clear-logo').prop('hidden', false);
			schedule();
		});
		frame.open();
	});
	$(document).on('click', '.pq-clear-logo', function () {
		var $wrap = $(this).closest('.pq-logo-pick');
		$wrap.find('.pq-logo-id').val(0);
		$wrap.find('.pq-logo-prev').html('<span class="description">No logo chosen</span>');
		$(this).prop('hidden', true);
		schedule();
	});

	/* Colour picker */
	$('.pq-color').wpColorPicker({ change: function () { setTimeout(schedule, 30); }, clear: function () { setTimeout(schedule, 30); } });

	/* Preview */
	function fit() {
		var frame = document.getElementById('pq-frame');
		var wrap = document.getElementById('pq-preview-wrap');
		if (!frame || !wrap || !wrap.clientWidth) { return; }
		var W = 840;
		var scale = Math.min(1, wrap.clientWidth / W) || 1;
		var h = 1123;
		try { h = Math.max(h, frame.contentDocument.documentElement.scrollHeight); } catch (e) { /* ignore */ }
		frame.style.width = W + 'px';
		frame.style.height = h + 'px';
		frame.style.transform = 'scale(' + scale + ')';
		wrap.style.height = Math.ceil(h * scale) + 'px';
	}
	function refresh() {
		if (req && req.abort) { req.abort(); }
		var frame = document.getElementById('pq-frame');
		req = $.post(P.ajax, { action: 'pq_preview', nonce: P.nonce, sample: 1, sample_type: $('#pq-set-type').val(), form: $form.serialize() }).done(function (res) {
			if (!res || !res.success) { return; }
			frame.onload = fit;
			frame.srcdoc = res.data.html;
		});
	}
	function schedule() {
		clearTimeout(timer);
		timer = setTimeout(refresh, 500);
	}
	$form.on('input change', 'input, select, textarea', schedule);
	$('#pq-set-refresh').on('click', refresh);
	$('#pq-set-type').on('change', function () {
		try { window.sessionStorage.setItem('pqPreviewType', this.value); } catch (e) { /* ignore */ }
		refresh();
	});
	try { var pt = window.sessionStorage.getItem('pqPreviewType'); if (pt) { $('#pq-set-type').val(pt); } } catch (e) { /* ignore */ }
	$(window).on('resize', fit);

	var start = 'business';
	try { start = window.sessionStorage.getItem('pqTab') || 'business'; } catch (e) { /* ignore */ }
	if (window.location.hash) { start = window.location.hash.replace('#', ''); }
	showTab(start);
	refresh();
}(jQuery));
