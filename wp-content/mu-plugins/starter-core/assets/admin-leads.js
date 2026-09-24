/**
 * Confirm + loader before trashing / permanently deleting starter_lead entries.
 */
(function () {
  'use strict';

  var cfg = window.starterLeadAdmin || {};
  var msgTrash = cfg.confirmTrash || 'Удалить эту заявку в корзину?';
  var msgDelete = cfg.confirmDelete || 'Удалить заявку навсегда?';
  var msgBulk = cfg.confirmBulk || 'Удалить выбранные заявки?';
  var msgLoading = cfg.loading || 'Удаление…';

  function isDeleteLink(href) {
    if (!href) return false;
    return href.indexOf('action=trash') !== -1 || href.indexOf('action=delete') !== -1;
  }

  function messageForHref(href) {
    if (href && href.indexOf('action=delete') !== -1) {
      return msgDelete;
    }
    return msgTrash;
  }

  function showLoader() {
    if (document.getElementById('starter-lead-delete-loader')) {
      return;
    }
    var overlay = document.createElement('div');
    overlay.id = 'starter-lead-delete-loader';
    overlay.className = 'starter-lead-loader';
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.innerHTML =
      '<div class="starter-lead-loader__box">' +
      '<span class="starter-lead-loader__spinner" aria-hidden="true"></span>' +
      '<span class="starter-lead-loader__text"></span>' +
      '</div>';
    var text = overlay.querySelector('.starter-lead-loader__text');
    if (text) {
      text.textContent = msgLoading;
    }
    document.body.appendChild(overlay);
    document.body.classList.add('starter-lead-loader-active');
  }

  document.addEventListener(
    'click',
    function (e) {
      var el = e.target;
      if (!el || !el.closest) return;

      var link = el.closest('a.submitdelete, a[href*="action=trash"], a[href*="action=delete"]');
      if (!link) return;

      if (!document.body.classList.contains('post-type-starter_lead')) return;

      var href = link.getAttribute('href') || '';
      if (!isDeleteLink(href) && !link.classList.contains('submitdelete')) return;

      if (!window.confirm(messageForHref(href))) {
        e.preventDefault();
        e.stopPropagation();
        return;
      }

      showLoader();
    },
    true
  );

  document.addEventListener(
    'submit',
    function (e) {
      var form = e.target;
      if (!form || form.id !== 'posts-filter') return;
      if (!document.body.classList.contains('post-type-starter_lead')) return;

      var top = document.getElementById('bulk-action-selector-top');
      var bottom = document.getElementById('bulk-action-selector-bottom');
      var action = (top && top.value) || (bottom && bottom.value) || '';

      if (action !== 'trash' && action !== 'delete') return;

      var msg = action === 'delete' ? msgDelete : msgBulk;
      if (!window.confirm(msg)) {
        e.preventDefault();
        return;
      }

      showLoader();
    },
    true
  );
})();
