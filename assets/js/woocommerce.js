/**
 * Banners OG — the WooCommerce product layout.
 *
 * The photo of the product is the placeholder of the `photo` field, filled in
 * by Banners_OG_Woocommerce::post_defaults(). The price is not a field at all:
 * it is always the current price of the product, and the form only says whether
 * to print it.
 */
(function (window) {
  'use strict';

  var api = window.BannersOG;

  if (!api || typeof api.registerTemplate !== 'function') {
    return;
  }

  /* URL safe to sit inside url('…') of a style attribute. encodeURI() is not
     enough: it leaves quotes and parentheses, which close the value early. */
  function cssUrl(url) {
    return String(url).replace(/['"()\s]/g, function (char) {
      return '%' + char.charCodeAt(0).toString(16).toUpperCase();
    });
  }

  /* The photo is a background instead of an <img>: html2canvas honours
     background-size, while object-fit comes out stretched. */
  function panel(f, ctx) {
    var url = f.show_photo ? (f.photo || '') : '';

    if (url) {
      return '<div class="bog-shot" style="background-image:url(\'' + cssUrl(url) + '\')"></div>';
    }

    return '<div class="bog-shot bog-shot--empty">' + ctx.image(ctx.images.mark, 'bog-mark') + '</div>';
  }

  function price(f) {
    return f.show_price ? ((window.BannersOGWoo || {}).price || '') : '';
  }

  api.registerTemplate('product', function (f, ctx) {
    var amount = price(f);
    // Product names run long, and the panel leaves the text half the canvas.
    var title = ctx.clamp(f.title, 80);

    return '' +
      '<div class="bog-canvas bog-canvas--product">' +
        '<div class="bog-content">' +
          '<div class="bog-eyebrow">' + ctx.esc(ctx.clamp(f.eyebrow, 34)) + '</div>' +
          '<div class="bog-title' + ctx.titleSize(title, 24, 44) + '">' + ctx.esc(title) + '</div>' +
          (amount ? '<div class="bog-price">' + ctx.esc(amount) + '</div>' : '') +
          '<div class="bog-divider"></div>' +
          '<div class="bog-sub">' + ctx.esc(ctx.clamp(f.sub, 120)) + '</div>' +
          '<div class="bog-footer">' +
            '<div class="bog-brand">' + ctx.esc(f.brand || ctx.brand) + '</div>' +
            '<div class="bog-dot"></div>' +
            '<div class="bog-foot">' + ctx.esc(ctx.clamp(f.foot, 52)) + '</div>' +
          '</div>' +
        '</div>' +
        '<div class="bog-side">' + panel(f, ctx) + '</div>' +
      '</div>';
  });
})(window);
