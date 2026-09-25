/*
  Block editor (posts): give every core/heading H2/H3 without an anchor a stable h-xxxxxxxxxx
  anchor, so TOC links survive heading text edits. Manually set anchors are never overwritten.
  The same stamping runs on save in PHP (inc/post-toc.php) for posts created outside the editor.
*/
(function () {
  "use strict";

  var wp = window.wp;
  if (!wp || !wp.data || !wp.data.subscribe || !wp.data.select || !wp.data.dispatch) return;

  var CHARS = "abcdefghijklmnopqrstuvwxyz0123456789";
  var busy = false;

  function anchor() {
    var id = "h-";
    for (var i = 0; i < 10; i++) id += CHARS.charAt(Math.floor(Math.random() * CHARS.length));
    return id;
  }

  function walk(blocks, editor) {
    (blocks || []).forEach(function (block) {
      if (block.name === "core/heading") {
        var level = (block.attributes && block.attributes.level) || 2;
        var current = String((block.attributes && block.attributes.anchor) || "").trim();
        if ((level === 2 || level === 3) && current === "") {
          editor.updateBlockAttributes(block.clientId, { anchor: anchor() });
        }
      }
      if (block.innerBlocks && block.innerBlocks.length) walk(block.innerBlocks, editor);
    });
  }

  wp.data.subscribe(function () {
    if (busy) return;
    var select = wp.data.select("core/block-editor");
    if (!select || typeof select.getBlocks !== "function") return;
    busy = true;
    try {
      walk(select.getBlocks(), wp.data.dispatch("core/block-editor"));
    } finally {
      busy = false;
    }
  });
})();
