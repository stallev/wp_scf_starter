/*
  Theme front-end. Loaded on every page with `defer` (inc/assets.php).

  Rules:
  - one feature() block per feature, each isolated by try/catch and exiting early when its markup
    is absent — one failing feature never breaks the others;
  - appearance lives in main.css: JS only toggles is-* classes and ARIA state (no inline styles);
  - no fetch() of prototype data files (data/*.json → 404 in WordPress, K9): data comes from the
    markup or from window.STARTER_* objects printed by PHP.
*/
(function () {
  "use strict";

  var desktop = window.matchMedia("(min-width: 1024px)");
  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

  // Each feature runs in its own try/catch: an exception in one never stops the others.
  function feature(fn) {
    try {
      fn();
    } catch (err) {
      if (window.console) window.console.error(err);
    }
  }

  /* ---- mobile menu (burger + drawer) ---- */
  feature(function () {
    var burger = document.getElementById("burger");
    var menu = document.getElementById("menu");
    if (!burger || !menu) return;

    var overlay = document.createElement("div");
    overlay.className = "menu__overlay";
    overlay.setAttribute("aria-hidden", "true");
    menu.parentNode.insertBefore(overlay, menu);

    function collapseSubs() {
      menu.querySelectorAll(".menu__item--has-sub.is-open").forEach(function (item) {
        item.classList.remove("is-open");
        var btn = item.querySelector(".menu__link--btn");
        if (btn) btn.setAttribute("aria-expanded", "false");
      });
    }

    function setOpen(open) {
      burger.setAttribute("aria-expanded", open ? "true" : "false");
      var label = burger.getAttribute(open ? "data-label-close" : "data-label-open");
      if (label) burger.setAttribute("aria-label", label);
      menu.classList.toggle("is-open", open);
      overlay.classList.toggle("is-open", open);
      document.documentElement.classList.toggle("is-locked", open);
      if (open) {
        // Move focus into the drawer (it becomes visible in the same frame).
        window.requestAnimationFrame(function () {
          var first = menu.querySelector("a, button");
          if (first) first.focus();
        });
      } else {
        collapseSubs();
        // Return focus to the toggle when it was inside the closing drawer.
        if (menu.contains(document.activeElement)) burger.focus();
      }
    }

    burger.addEventListener("click", function () {
      setOpen(burger.getAttribute("aria-expanded") !== "true");
    });
    overlay.addEventListener("click", function () { setOpen(false); });
    menu.addEventListener("click", function (e) {
      if (e.target.closest("a")) setOpen(false);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && menu.classList.contains("is-open")) {
        setOpen(false);
        burger.focus();
      }
    });
    desktop.addEventListener("change", function (e) { if (e.matches) setOpen(false); });

    menu.querySelectorAll(".menu__item--has-sub").forEach(function (item) {
      var btn = item.querySelector(".menu__link--btn");
      if (!btn) return;
      btn.addEventListener("click", function () {
        var open = !item.classList.contains("is-open");
        collapseSubs();
        item.classList.toggle("is-open", open);
        btn.setAttribute("aria-expanded", open ? "true" : "false");
      });
    });
  });

  /* ---- desktop dropdowns ---- */
  feature(function () {
    var items = Array.prototype.slice.call(document.querySelectorAll(".nav__item--has-sub"));
    if (!items.length) return;

    function setOpen(item, open) {
      item.classList.toggle("is-open", open);
      var toggle = item.querySelector(".nav__toggle");
      if (toggle) toggle.setAttribute("aria-expanded", open ? "true" : "false");
    }
    function closeAll(except) {
      items.forEach(function (item) { if (item !== except) setOpen(item, false); });
    }

    items.forEach(function (item) {
      var toggle = item.querySelector(".nav__toggle");
      var timer = null;
      if (toggle) {
        toggle.addEventListener("click", function () {
          var open = !item.classList.contains("is-open");
          closeAll(item);
          setOpen(item, open);
        });
      }
      item.addEventListener("mouseenter", function () {
        if (!desktop.matches) return;
        clearTimeout(timer);
        closeAll(item);
        setOpen(item, true);
      });
      item.addEventListener("mouseleave", function () {
        if (!desktop.matches) return;
        timer = setTimeout(function () { setOpen(item, false); }, 160);
      });
      item.addEventListener("focusout", function (e) {
        if (!item.contains(e.relatedTarget)) setOpen(item, false);
      });
    });

    document.addEventListener("click", function (e) {
      if (!e.target.closest(".nav__item--has-sub")) closeAll(null);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") closeAll(null);
    });
  });

  /* ---- header shadow after scroll ---- */
  feature(function () {
    var header = document.getElementById("header");
    if (!header) return;
    var ticking = false;
    function update() {
      header.classList.toggle("is-stuck", window.scrollY > 8);
      ticking = false;
    }
    window.addEventListener("scroll", function () {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(update);
      }
    }, { passive: true });
    update();
  });

  /* ---- scroll reveal (.reveal → .is-in). Never put .reveal on first-screen blocks (K1). ---- */
  feature(function () {
    // Opt in first: CSS hides .reveal only under html.has-reveal, so without JS content stays visible.
    var nodes = document.querySelectorAll(".reveal");
    if (!nodes.length) return;
    document.documentElement.classList.add("has-reveal");

    function showAll() {
      nodes.forEach(function (el) { el.classList.add("is-in"); });
    }
    if (reduceMotion.matches || !("IntersectionObserver" in window)) {
      showAll();
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry, i) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        setTimeout(function () { el.classList.add("is-in"); }, Math.min(i * 70, 280));
        io.unobserve(el);
      });
    }, { rootMargin: "0px 0px -8% 0px", threshold: 0.08 });

    nodes.forEach(function (el) { io.observe(el); });
  });

  /* ---- FAQ accordion ---- */
  feature(function () {
    // Server markup shows every answer (aria-expanded="true"); enhance → collapse and toggle.
    var lists = document.querySelectorAll(".faq");
    if (!lists.length) return;
    lists.forEach(function (list) { list.classList.add("is-enhanced"); });
    var items = document.querySelectorAll(".faq .faq__item");

    items.forEach(function (item) {
      var q = item.querySelector(".faq__q");
      if (!q) return;
      q.setAttribute("aria-expanded", "false");
      q.addEventListener("click", function () {
        var open = item.classList.toggle("is-open");
        q.setAttribute("aria-expanded", open ? "true" : "false");
      });
    });
  });

  /* ---- lead forms: AJAX → admin-ajax (starter_submit_lead) ---- */
  feature(function () {
    var forms = document.querySelectorAll("form.js-lead");
    if (!forms.length) return;

    var cfg = window.STARTER_LEAD || {};
    var i18n = cfg.i18n || {};

    function message(key, fallback) {
      return i18n[key] || fallback;
    }

    function setError(form, text) {
      var box = form.querySelector(".lead-form__error");
      form.classList.toggle("is-error", !!text);
      if (box) box.textContent = text || "";
    }

    function markInvalid(field, invalid) {
      field.classList.toggle("is-invalid", invalid);
      field.setAttribute("aria-invalid", invalid ? "true" : "false");
    }

    function validate(form) {
      var firstInvalid = null;
      var consentMissing = false;
      form.querySelectorAll("[required]").forEach(function (field) {
        var ok = field.type === "checkbox" ? field.checked : field.value.trim() !== "";
        markInvalid(field, !ok);
        if (!ok && field.type === "checkbox") consentMissing = true;
        if (!ok && !firstInvalid) firstInvalid = field;
      });
      if (firstInvalid) {
        firstInvalid.focus();
        return consentMissing && firstInvalid.type === "checkbox"
          ? message("consent", "Consent is required.")
          : message("required", "Please fill in the required fields.");
      }
      return "";
    }

    function errorFor(status, json) {
      if (json && json.data && json.data.message) return json.data.message;
      if (status === 403) return message("nonce", "Session expired. Reload the page.");
      if (status === 429) return message("rateLimit", "Too many requests. Try again later.");
      return message("error", "Could not send the form. Please try again.");
    }

    forms.forEach(function (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        if (form.classList.contains("is-sending")) return;

        var invalid = validate(form);
        setError(form, invalid);
        if (invalid) return;

        var url = cfg.ajaxUrl || form.getAttribute("action");
        var body = new FormData(form);
        if (cfg.action) body.set("action", cfg.action);

        var submit = form.querySelector('[type="submit"]');
        form.classList.add("is-sending");
        if (submit) submit.disabled = true;

        fetch(url, { method: "POST", body: body, credentials: "same-origin" })
          .then(function (res) {
            return res.json().catch(function () { return null; }).then(function (json) {
              return { status: res.status, json: json };
            });
          })
          .then(function (result) {
            if (result.status === 200 && result.json && result.json.success) {
              form.classList.remove("is-error");
              form.classList.add("is-sent");
              var done = form.querySelector(".lead-form__done");
              if (done) done.focus({ preventScroll: true });
              var source = form.querySelector('[name="source"]');
              document.dispatchEvent(new CustomEvent("starter:lead:success", {
                detail: { formId: form.id || "", source: source ? source.value : "" }
              }));
              return;
            }
            // 400 (validation), 403 (nonce), 429 (rate limit), 5xx — show the server message.
            setError(form, errorFor(result.status, result.json));
          })
          .catch(function () {
            setError(form, message("network", "Network error. Check the connection and retry."));
          })
          .then(function () {
            form.classList.remove("is-sending");
            if (submit) submit.disabled = false;
          });
      });

      form.addEventListener("input", function (e) {
        if (e.target.classList && e.target.classList.contains("is-invalid")) markInvalid(e.target, false);
      });
      form.addEventListener("change", function (e) {
        if (e.target.type === "checkbox" && e.target.checked) markInvalid(e.target, false);
      });
    });
  });

  /* ---- lightbox ([data-lightbox] triggers, grouped by data-lightbox-group) ---- */
  feature(function () {
    if (!document.querySelector("[data-lightbox]") || typeof HTMLDialogElement !== "function") return;

    var t = window.STARTER_I18N || {};
    var dialog, img, caption, prev, next, items = [], index = 0, trigger = null;

    function attr(text) {
      return String(text).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function build() {
      dialog = document.createElement("dialog");
      dialog.className = "lightbox";
      dialog.innerHTML =
        '<button class="lightbox__close" type="button" aria-label="' + attr(t.close || 'Close') + '">&times;</button>' +
        '<button class="lightbox__nav lightbox__nav--prev" type="button" aria-label="' + attr(t.prev || 'Previous') + '">&lsaquo;</button>' +
        '<figure class="lightbox__figure"><img class="lightbox__img" alt="" decoding="async">' +
        '<figcaption class="lightbox__cap"></figcaption></figure>' +
        '<button class="lightbox__nav lightbox__nav--next" type="button" aria-label="' + attr(t.next || 'Next') + '">&rsaquo;</button>';
      document.body.appendChild(dialog);
      img = dialog.querySelector(".lightbox__img");
      caption = dialog.querySelector(".lightbox__cap");
      prev = dialog.querySelector(".lightbox__nav--prev");
      next = dialog.querySelector(".lightbox__nav--next");
      dialog.querySelector(".lightbox__close").addEventListener("click", function () { dialog.close(); });
      prev.addEventListener("click", function () { show(index - 1); });
      next.addEventListener("click", function () { show(index + 1); });
      dialog.addEventListener("click", function (e) { if (e.target === dialog) dialog.close(); });
      dialog.addEventListener("keydown", function (e) {
        if (e.key === "ArrowLeft") show(index - 1);
        if (e.key === "ArrowRight") show(index + 1);
      });
      dialog.addEventListener("close", function () {
        document.documentElement.classList.remove("is-locked");
        img.removeAttribute("src");
        img.removeAttribute("srcset");
        if (trigger) trigger.focus({ preventScroll: true });
      });
    }

    function show(i) {
      if (!items.length) return;
      index = (i + items.length) % items.length;
      var node = items[index];
      var srcset = node.getAttribute("data-lightbox-srcset");
      if (srcset) {
        img.setAttribute("srcset", srcset);
        img.setAttribute("sizes", "100vw");
      } else {
        img.removeAttribute("srcset");
      }
      img.src = node.getAttribute("data-lightbox-src") || "";
      img.alt = node.getAttribute("data-lightbox-title") || "";
      var title = node.getAttribute("data-lightbox-title") || "";
      var desc = node.getAttribute("data-lightbox-desc") || "";
      caption.textContent = [title, desc].filter(Boolean).join(" · ");
      prev.hidden = next.hidden = items.length < 2;
    }

    document.addEventListener("click", function (e) {
      var node = e.target.closest("[data-lightbox]");
      if (!node) return;
      e.preventDefault();
      if (!dialog) build();
      var group = node.getAttribute("data-lightbox-group");
      items = group
        ? Array.prototype.slice.call(document.querySelectorAll('[data-lightbox][data-lightbox-group="' + (window.CSS && CSS.escape ? CSS.escape(group) : group.replace(/["\\]/g, "")) + '"]'))
        : [node];
      items = items.filter(function (el) { return !el.closest(".is-hidden"); });
      trigger = node;
      show(Math.max(0, items.indexOf(node)));
      document.documentElement.classList.add("is-locked");
      dialog.showModal();
      document.dispatchEvent(new CustomEvent("starter:lightbox:open", {
        detail: { group: group || "", index: index + 1, count: items.length }
      }));
    });
  });

  /* ---- embed facade: iframe only after a click (map / video / widget) ---- */
  feature(function () {
    var boxes = document.querySelectorAll(".embed-facade[data-src]");
    if (!boxes.length) return;

    boxes.forEach(function (box) {
      var btn = box.querySelector(".embed-facade__btn");
      if (!btn) return;
      btn.addEventListener("click", function () {
        var frame = document.createElement("iframe");
        frame.className = "embed-facade__frame";
        frame.src = box.getAttribute("data-src");
        frame.title = box.getAttribute("data-title") || "";
        frame.loading = "lazy";
        frame.referrerPolicy = "strict-origin-when-cross-origin";
        var allow = box.getAttribute("data-allow");
        if (allow) {
          frame.setAttribute("allow", allow);
          frame.allowFullscreen = true;
        }
        box.classList.add("is-loaded");
        box.replaceChildren(frame);
        frame.focus();
      });
    });
  });
})();
