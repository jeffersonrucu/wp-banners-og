/**
 * Banners OG — media pickers and color inputs of the appearance screen.
 */
(function (window, document) {
  'use strict';

  var strings = window.BannersOGSettings || {};

  function bindMedia(wrapper) {
    var input = wrapper.querySelector('[data-bog-media-input]');
    var preview = wrapper.querySelector('[data-bog-media-preview]');
    var selectBtn = wrapper.querySelector('[data-bog-media-select]');
    var removeBtn = wrapper.querySelector('[data-bog-media-remove]');
    var frame = null;

    if (!input || !selectBtn) {
      return;
    }

    selectBtn.addEventListener('click', function () {
      if (!window.wp || !window.wp.media) {
        return;
      }

      if (!frame) {
        frame = window.wp.media({
          title: strings.chooseTitle || '',
          button: { text: strings.chooseButton || '' },
          library: { type: 'image' },
          multiple: false
        });

        frame.on('select', function () {
          var attachment = frame.state().get('selection').first().toJSON();

          input.value = attachment.id;
          preview.textContent = '';

          var img = document.createElement('img');

          img.src = (attachment.sizes && attachment.sizes.medium)
            ? attachment.sizes.medium.url
            : attachment.url;
          img.alt = '';
          preview.appendChild(img);
        });
      }

      frame.open();
    });

    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        input.value = '0';
        preview.textContent = '';
      });
    }
  }

  function bindColors() {
    var pickers = document.querySelectorAll('[data-bog-color-for]');

    Array.prototype.forEach.call(pickers, function (picker) {
      var target = document.getElementById(picker.dataset.bogColorFor);

      if (!target) {
        return;
      }

      picker.addEventListener('input', function () {
        target.value = picker.value;
      });

      target.addEventListener('input', function () {
        if (/^#[0-9a-fA-F]{6}$/.test(target.value)) {
          picker.value = target.value;
        }
      });
    });
  }

  /** The manual font stacks only make sense for the "custom" pairing. */
  function bindFontPreset() {
    var select = document.querySelector('[data-bog-font-preset]');
    var advanced = document.querySelector('[data-bog-advanced]');
    var heading = document.querySelector('[data-bog-sample-heading]');
    var body = document.querySelector('[data-bog-sample-body]');

    if (!select) {
      return;
    }

    function sync() {
      var option = select.options[select.selectedIndex];

      if (advanced) {
        advanced.hidden = select.value !== 'custom';
      }

      if (heading) {
        heading.style.fontFamily = option.dataset.heading || '';
      }

      if (body) {
        body.style.fontFamily = option.dataset.body || '';
      }
    }

    select.addEventListener('change', sync);

    // The custom option previews whatever is typed in the advanced fields.
    ['bog-font-heading', 'bog-font-body'].forEach(function (id) {
      var input = document.getElementById(id);
      var custom = select.querySelector('option[value="custom"]');

      if (!input || !custom) {
        return;
      }

      input.addEventListener('input', function () {
        custom.dataset[id === 'bog-font-heading' ? 'heading' : 'body'] = input.value;

        if (select.value === 'custom') {
          sync();
        }
      });
    });

    sync();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var wrappers = document.querySelectorAll('[data-bog-media]');

    Array.prototype.forEach.call(wrappers, bindMedia);
    bindColors();
    bindFontPreset();
  });
})(window, document);
