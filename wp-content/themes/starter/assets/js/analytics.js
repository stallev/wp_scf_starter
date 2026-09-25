/*
  Deferred GA4 (inc/analytics.php). Enqueued only when a GA4 ID is set and the visitor is not an
  administrator. The page already defines window.STARTER_GA4 { id, delayMs }, window.dataLayer and a
  gtag() stub; this file injects gtag.js ONCE — delayMs after `load` or on the first interaction,
  whichever comes first — so the third-party library stays out of the critical path.
  Events fired earlier sit in dataLayer and are replayed by gtag.js.
*/
(function () {
  "use strict";

  var GA = window.STARTER_GA4 || {};
  if (!GA.id) return;

  /* ---- deferred gtag.js loader ---- */
  var requested = false;
  var interactions = ["pointerdown", "keydown", "scroll", "touchstart"];

  function loadGtag() {
    if (requested) return;
    requested = true;
    interactions.forEach(function (type) { window.removeEventListener(type, loadGtag); });
    var s = document.createElement("script");
    s.async = true;
    s.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(GA.id);
    document.head.appendChild(s);
  }

  function schedule() {
    window.setTimeout(loadGtag, typeof GA.delayMs === "number" ? GA.delayMs : 2500);
  }

  if (document.readyState === "complete") {
    schedule();
  } else {
    window.addEventListener("load", schedule, { once: true });
  }
  interactions.forEach(function (type) {
    window.addEventListener(type, loadGtag, { once: true, passive: true });
  });

  /* ---- events ---- */
  function track(name, params) {
    if (typeof window.gtag !== "function") return;
    var data = { page_path: window.location.pathname };
    Object.keys(params || {}).forEach(function (k) { data[k] = params[k]; });
    window.gtag("event", name, data);
  }

  // Contact clicks: phone, Telegram, WhatsApp, Viber, e-mail.
  document.addEventListener("click", function (e) {
    var a = e.target.closest && e.target.closest("a[href]");
    if (!a) return;
    var href = a.getAttribute("href") || "";

    if (/^tel:/i.test(href)) {
      track("click_phone", { link_url: href });
    } else if (/^(https?:\/\/)?(t\.me|telegram\.me)\//i.test(href) || /^tg:/i.test(href)) {
      track("click_telegram", { link_url: href });
    } else if (/^(https?:\/\/)?(wa\.me|api\.whatsapp\.com|chat\.whatsapp\.com)\//i.test(href) || /^whatsapp:/i.test(href)) {
      track("click_whatsapp", { link_url: href });
    } else if (/^viber:/i.test(href)) {
      track("click_viber", { link_url: href });
    } else if (/^mailto:/i.test(href)) {
      track("click_email", { link_url: href });
    }
  }, true);

  // Lead accepted (main.js → CustomEvent).
  document.addEventListener("starter:lead:success", function (e) {
    var d = (e && e.detail) || {};
    track("generate_lead", { form_id: d.formId || "", lead_source: d.source || "" });
  });

  // Lightbox opened (main.js → CustomEvent).
  document.addEventListener("starter:lightbox:open", function (e) {
    var d = (e && e.detail) || {};
    track("lightbox_open", { lightbox_group: d.group || "", item_index: d.index || 0 });
  });
})();
