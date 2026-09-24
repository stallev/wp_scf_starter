/**
 * FAQ admin accordion: sortable order + delete with loader.
 */
(function ($) {
	'use strict';

	var cfg = window.STARTER_FAQ_ADMIN || {};
	var $loader = $('#starter-faq-loader');
	var $status = $('#starter-faq-status');
	var busy = 0;

	function setBusy(on) {
		busy += on ? 1 : -1;
		if (busy < 0) busy = 0;
		if (busy > 0) {
			$loader.prop('hidden', false).attr('aria-hidden', 'false');
		} else {
			$loader.prop('hidden', true).attr('aria-hidden', 'true');
		}
	}

	function flash(msg, isError) {
		$status
			.text(msg || '')
			.toggleClass('is-error', !!isError)
			.toggleClass('is-ok', !isError && !!msg);
	}

	function saveList($list) {
		var location = $list.data('location');
		if (!location || location === '_other') return;

		var ids = [];
		$list.find('.starter-faq-row').each(function () {
			ids.push(parseInt($(this).data('id'), 10));
		});

		setBusy(true);
		flash('');

		$.post(cfg.ajaxUrl, {
			action: 'starter_faq_reorder',
			nonce: cfg.nonce,
			location: location,
			ids: ids
		})
			.done(function (res) {
				if (res && res.success) {
					$list.find('.starter-faq-row').each(function (i) {
						$(this).find('.starter-faq-row__order').text(String((i + 1) * 10));
					});
					flash((cfg.i18n && cfg.i18n.saved) || 'OK');
				} else {
					flash((cfg.i18n && cfg.i18n.error) || 'Error', true);
				}
			})
			.fail(function () {
				flash((cfg.i18n && cfg.i18n.error) || 'Error', true);
			})
			.always(function () {
				setBusy(false);
			});
	}

	$(function () {
		$('.starter-faq-list').each(function () {
			var $list = $(this);
			if ($list.data('location') === '_other') return;

			$list.sortable({
				handle: '.starter-faq-row__handle',
				axis: 'y',
				update: function () {
					saveList($list);
				}
			});
		});

		$('#starter-faq-admin').on('click', '.starter-faq-row__delete', function (e) {
			e.preventDefault();
			var id = parseInt($(this).data('id'), 10);
			if (!id) return;
			if (!window.confirm((cfg.i18n && cfg.i18n.confirmDelete) || 'Delete?')) return;

			var $row = $(this).closest('.starter-faq-row');
			var $list = $row.closest('.starter-faq-list');

			setBusy(true);
			flash('');

			$.post(cfg.ajaxUrl, {
				action: 'starter_faq_delete',
				nonce: cfg.nonce,
				id: id
			})
				.done(function (res) {
					if (res && res.success) {
						$row.slideUp(160, function () {
							$row.remove();
							if ($list.children().length) {
								saveList($list);
							}
						});
					} else {
						flash((cfg.i18n && cfg.i18n.error) || 'Error', true);
					}
				})
				.fail(function () {
					flash((cfg.i18n && cfg.i18n.error) || 'Error', true);
				})
				.always(function () {
					setBusy(false);
				});
		});
	});
})(jQuery);
