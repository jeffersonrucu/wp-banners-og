/**
 * Banners OG — the WooCommerce product layout.
 *
 * The copy comes from the shared fields; the photo comes from the product
 * being edited, localized by Banners_OG_Woocommerce::enqueue().
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

  function photo() {
    return (window.BannersOGWoo || {}).image || '';
  }

  /* The photo is a background instead of an <img>: html2canvas honours
     background-size, while object-fit comes out stretched. */
  function panel(ctx) {
    var url = photo();

    if (url) {
      return '<div class="bog-shot" style="background-image:url(\'' + cssUrl(url) + '\')"></div>';
    }

    return '<div class="bog-shot bog-shot--empty">' + ctx.image(ctx.images.mark, 'bog-mark') + '</div>';
  }

  api.registerTemplate('product', function (f, ctx) {
    return '' +
      '<div class="bog-canvas bog-canvas--product">' +
        '<div class="bog-content">' +
          '<div class="bog-eyebrow">' + ctx.esc(f.eyebrow) + '</div>' +
          '<div class="bog-title">' + ctx.esc(f.title) + '</div>' +
          (f.price ? '<div class="bog-price">' + ctx.esc(f.price) + '</div>' : '') +
          '<div class="bog-divider"></div>' +
          '<div class="bog-sub">' + ctx.esc(f.sub) + '</div>' +
          '<div class="bog-footer">' +
            '<div class="bog-brand">' + ctx.esc(ctx.brand) + '</div>' +
            '<div class="bog-dot"></div>' +
            '<div class="bog-foot">' + ctx.esc(f.foot) + '</div>' +
          '</div>' +
        '</div>' +
        '<div class="bog-side">' + panel(ctx) + '</div>' +
      '</div>';
  });
})(window);
