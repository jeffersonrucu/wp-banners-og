/**
 * Banners OG — live preview, html2canvas capture and upload.
 *
 * Public API (window.BannersOG):
 *   registerTemplate(kind, renderer)  register or replace a layout
 *   templates                         the layout map
 *   helpers                           { esc, image }
 *   boot()                            re-scan the page for editors
 *
 * A renderer receives (fields, ctx) and returns the HTML of one 1200x630
 * canvas. `fields` is keyed by field key; `ctx` carries esc/image helpers,
 * the brand name, the brand image URLs and the current kind.
 */
(function (window, document) {
  'use strict';

  var data = window.BannersOGData || {};
  var api = window.BannersOG || {};

  window.BannersOG = api;
  api.templates = api.templates || {};

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /** Renders an <img> only when there is an image to render. */
  function image(url, className) {
    if (!url) {
      return '';
    }

    return '<img class="' + esc(className) + '" src="' + esc(url) + '" alt="">';
  }

  /** Generic corner brackets: geometric detail that needs no brand asset. */
  function corners() {
    return ['tl', 'tr', 'bl', 'br'].map(function (position) {
      return '<div class="bog-corner bog-corner--' + position + '"></div>';
    }).join('');
  }

  /**
   * Cuts on a word boundary and marks the cut, so a long title crops instead
   * of pushing the rest of the banner out of the canvas.
   */
  function clamp(value, max) {
    var text = String(value == null ? '' : value).trim();

    if (text.length <= max) {
      return text;
    }

    var cut = text.slice(0, max);
    var space = cut.lastIndexOf(' ');

    if (space > max * 0.6) {
      cut = cut.slice(0, space);
    }

    return cut.replace(/[\s,.;:—–-]+$/, '') + '…';
  }

  /**
   * Modifier of the title for the length of the text: a long title steps down
   * a size instead of eating the space of everything below it.
   */
  function titleSize(text, mid, small) {
    var length = String(text == null ? '' : text).trim().length;

    if (length > small) {
      return ' bog-title--xs';
    }

    return length > mid ? ' bog-title--sm' : '';
  }

  api.helpers = { esc: esc, image: image, corners: corners, clamp: clamp, titleSize: titleSize };

  api.registerTemplate = function (kind, renderer) {
    if (typeof kind === 'string' && typeof renderer === 'function') {
      api.templates[kind] = renderer;
    }
  };

  function context(kind) {
    return {
      esc: esc,
      image: image,
      corners: corners,
      clamp: clamp,
      titleSize: titleSize,
      kind: kind,
      // Fallback of the brand field: what the Appearance screen says.
      brand: data.brand || '',
      images: data.images || {},
      width: data.width || 1200,
      height: data.height || 630
    };
  }

  /* ---------------------------------------------------------------------
   * Built-in layouts
   * ------------------------------------------------------------------- */

  api.registerTemplate('cover', function (f, ctx) {
    var title = ctx.clamp(f.title, 90);

    return '' +
      '<div class="bog-canvas bog-canvas--cover">' +
        ctx.corners() +
        '<div class="bog-content">' +
          '<div class="bog-eyebrow">' + ctx.esc(ctx.clamp(f.eyebrow, 44)) + '</div>' +
          ctx.image(ctx.images.mark, 'bog-mark') +
          '<div class="bog-title' + ctx.titleSize(title, 24, 44) + '">' + ctx.esc(title) + '</div>' +
          '<div class="bog-divider"></div>' +
          '<div class="bog-sub">' + ctx.esc(ctx.clamp(f.sub, 150)) + '</div>' +
        '</div>' +
        '<div class="bog-footer">' +
          '<div class="bog-brand">' + ctx.esc(f.brand || ctx.brand) + '</div>' +
          '<div class="bog-dot"></div>' +
          '<div class="bog-foot">' + ctx.esc(f.foot) + '</div>' +
        '</div>' +
      '</div>';
  });

  api.registerTemplate('feature', function (f, ctx) {
    var title = ctx.clamp(f.title, 95);

    return '' +
      '<div class="bog-canvas bog-canvas--feature">' +
        ctx.image(ctx.images.mark, 'bog-mark-bg') +
        '<div class="bog-brand">' + ctx.esc(f.brand || ctx.brand) + '</div>' +
        '<div class="bog-content">' +
          '<div class="bog-eyebrow">' + ctx.esc(ctx.clamp(f.eyebrow, 44)) + '</div>' +
          '<div class="bog-title' + ctx.titleSize(title, 26, 48) + '">' + ctx.esc(title) + '</div>' +
          '<div class="bog-sub">' + ctx.esc(ctx.clamp(f.sub, 160)) + '</div>' +
        '</div>' +
        '<div class="bog-footer">' +
          '<div class="bog-footline"></div>' +
          '<div class="bog-foot">' + ctx.esc(f.foot) + '</div>' +
        '</div>' +
      '</div>';
  });

  api.registerTemplate('article', function (f, ctx) {
    var mark = ctx.image(ctx.images.mark, 'bog-mark');
    var title = ctx.clamp(f.title, 110);

    return '' +
      '<div class="bog-canvas bog-canvas--article">' +
        '<div class="bog-side">' +
          (mark ? '<div class="bog-mark-plate">' + mark + '</div>' : '') +
          '<div class="bog-brand">' + ctx.esc(f.brand || ctx.brand) + '</div>' +
        '</div>' +
        '<div class="bog-content">' +
          '<div class="bog-eyebrow">' + ctx.esc(ctx.clamp(f.eyebrow, 44)) + '</div>' +
          '<div class="bog-title' + ctx.titleSize(title, 32, 58) + '">' + ctx.esc(title) + '</div>' +
          '<div class="bog-divider"></div>' +
          '<div class="bog-sub">' + ctx.esc(ctx.clamp(f.sub, 170)) + '</div>' +
          '<div class="bog-foot">' + ctx.esc(f.foot) + '</div>' +
        '</div>' +
      '</div>';
  });

  api.registerTemplate('profile', function (f, ctx) {
    var title = ctx.clamp(f.title, 90);

    return '' +
      '<div class="bog-canvas bog-canvas--profile">' +
        ctx.corners() +
        '<div class="bog-content">' +
          '<div class="bog-eyebrow">' + ctx.esc(ctx.clamp(f.eyebrow, 44)) + '</div>' +
          (ctx.images.logo
            ? ctx.image(ctx.images.logo, 'bog-logo')
            : ctx.image(ctx.images.mark, 'bog-mark')) +
          '<div class="bog-title' + ctx.titleSize(title, 24, 44) + '">' + ctx.esc(title) + '</div>' +
          '<div class="bog-divider"></div>' +
          '<div class="bog-sub">' + ctx.esc(ctx.clamp(f.sub, 150)) + '</div>' +
        '</div>' +
        '<div class="bog-foot">' + ctx.esc(f.foot) + '</div>' +
      '</div>';
  });

  /* ---------------------------------------------------------------------
   * Editor
   * ------------------------------------------------------------------- */

  function Editor(root) {
    this.root = root;
    this.context = root.dataset.context; // 'default' | 'post'
    this.postId = parseInt(root.dataset.postId || '0', 10);
    this.defaults = root.dataset.defaults ? JSON.parse(root.dataset.defaults) : null;
    this.postTitle = root.dataset.postTitle || '';
    this.footPlaceholder = root.dataset.footPlaceholder || '';

    this.stage = root.querySelector('.bog-stage');
    this.stageWrap = root.querySelector('.bog-stage-wrap');
    this.status = root.querySelector('.bog-status');
    this.generateBtn = root.querySelector('.bog-generate');
    this.enabledInput = root.querySelector('.bog-enabled');
    this.customWrap = root.querySelector('.bog-custom');
    this.currentInfo = root.querySelector('.bog-current');

    this.uploading = false;

    this.bind();
    this.syncFields();
    this.syncPlaceholders();
    this.render();
    this.observeResize();
  }

  Editor.prototype.field = function (name) {
    return this.root.querySelector('.bog-field[data-field="' + name + '"]');
  };

  Editor.prototype.fieldKeys = function () {
    return (data.fields || []).map(function (field) {
      return field.key;
    });
  };

  Editor.prototype.kind = function () {
    if (this.context === 'default') {
      return this.root.dataset.kind;
    }

    // Without customization the post uses the default layout of its type.
    if (!this.customized()) {
      return this.root.dataset.autoKind || this.root.dataset.kind || '';
    }

    var select = this.field('kind');

    return select ? select.value : this.root.dataset.kind || '';
  };

  Editor.prototype.customized = function () {
    return !!(this.enabledInput && this.enabledInput.checked);
  };

  /** Post title as it is right now (block editor, classic editor or fallback). */
  Editor.prototype.liveTitle = function () {
    var wp = window.wp;

    if (wp && wp.data && wp.data.select && wp.data.select('core/editor')) {
      var edited = wp.data.select('core/editor').getEditedPostAttribute('title');

      if (edited) {
        return edited;
      }
    }

    var classic = document.getElementById('title');

    if (classic && classic.value.trim()) {
      return classic.value.trim();
    }

    return this.postTitle;
  };

  /** Stored value of a field, exactly as it will be saved. */
  Editor.prototype.raw = function (name) {
    var input = this.field(name);

    if (!input) {
      return '';
    }

    if (input.type === 'checkbox') {
      return input.checked ? '1' : '';
    }

    return input.value;
  };

  /** Effective value of a field: what was typed, or its placeholder. */
  Editor.prototype.value = function (name) {
    var input = this.field(name);

    if (!input) {
      return '';
    }

    if (input.type === 'checkbox') {
      return this.raw(name);
    }

    return input.value.trim() || input.getAttribute('placeholder') || '';
  };

  Editor.prototype.data = function () {
    var self = this;
    var out = {};
    var keys = this.fieldKeys();

    // Automatic mode: ignores the hidden inputs and builds the banner from the
    // layout defaults plus the post title.
    if (this.context === 'post' && !this.customized()) {
      var defaults = (this.defaults && this.defaults[this.kind()]) || {};

      keys.forEach(function (key) {
        out[key] = defaults[key] || '';
      });

      out.title = this.liveTitle() || defaults.title || '';
      out.foot = this.footPlaceholder || defaults.foot || '';

      return out;
    }

    keys.forEach(function (key) {
      out[key] = self.value(key);
    });

    return out;
  };

  /** Only the fields the selected layout uses stay on screen. */
  Editor.prototype.syncFields = function () {
    var kind = this.kind();
    var labels = this.root.querySelectorAll('.bog-fields label[data-kinds]');

    Array.prototype.forEach.call(labels, function (label) {
      var kinds = label.dataset.kinds.split(' ');

      label.hidden = kinds.indexOf(kind) === -1;
    });
  };

  /** In the metabox the placeholders follow the selected layout. */
  Editor.prototype.syncPlaceholders = function () {
    if (this.context !== 'post' || !this.defaults) {
      return;
    }

    var self = this;
    var defaults = this.defaults[this.kind()] || {};

    this.fieldKeys().forEach(function (key) {
      var input = self.field(key);

      if (!input) {
        return;
      }

      var placeholder = defaults[key] || '';

      if (key === 'title') {
        placeholder = self.liveTitle() || placeholder;
      } else if (key === 'foot') {
        placeholder = self.footPlaceholder || placeholder;
      }

      input.setAttribute('placeholder', placeholder);
    });
  };

  Editor.prototype.markup = function () {
    var kind = this.kind();
    var renderer = api.templates[kind];

    if (typeof renderer !== 'function') {
      return '<div class="bog-canvas"></div>';
    }

    return renderer(this.data(), context(kind));
  };

  Editor.prototype.render = function () {
    this.stage.innerHTML = this.markup();
  };

  Editor.prototype.observeResize = function () {
    var self = this;

    function rescale() {
      if (!self.stageWrap) {
        return;
      }

      var width = self.stageWrap.clientWidth;

      if (width > 0) {
        self.stage.style.transform = 'scale(' + (width / (data.width || 1200)) + ')';
      }
    }

    if (window.ResizeObserver) {
      new window.ResizeObserver(rescale).observe(this.stageWrap);
    } else {
      window.addEventListener('resize', rescale);
    }

    rescale();
  };

  Editor.prototype.setStatus = function (message, isError) {
    if (!this.status) {
      return;
    }

    this.status.textContent = message || '';
    this.status.classList.toggle('is-error', !!isError);
  };

  /** Renders the banner off-screen at full size and captures it as a JPEG blob. */
  Editor.prototype.capture = function () {
    var holder = document.createElement('div');

    holder.className = 'bog-capture-holder';
    holder.innerHTML = this.markup();
    document.body.appendChild(holder);

    var node = holder.firstElementChild;
    var fontsReady = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();

    return fontsReady
      .then(function () {
        var images = Array.prototype.slice.call(node.querySelectorAll('img'));

        return Promise.all(images.map(function (img) {
          return img.complete ? Promise.resolve() : new Promise(function (resolve) {
            img.onload = resolve;
            img.onerror = resolve;
          });
        }));
      })
      .then(function () {
        return window.html2canvas(node, {
          scale: 1,
          useCORS: true,
          backgroundColor: '#ffffff',
          width: data.width || 1200,
          height: data.height || 630,
          windowWidth: data.width || 1200,
          windowHeight: data.height || 630
        });
      })
      .then(function (canvas) {
        return new Promise(function (resolve, reject) {
          canvas.toBlob(function (blob) {
            if (blob) {
              resolve(blob);
            } else {
              reject(new Error('canvas.toBlob() returned nothing'));
            }
          }, 'image/jpeg', 0.92);
        });
      })
      .then(function (blob) {
        holder.remove();

        return blob;
      })
      .catch(function (error) {
        holder.remove();

        throw error;
      });
  };

  Editor.prototype.upload = function (blob) {
    var self = this;
    var body = new FormData();

    body.append('nonce', data.nonce);
    body.append('banner', blob, 'banner.jpg');
    body.append('kind', this.kind());

    this.fieldKeys().forEach(function (key) {
      body.append('fields[' + key + ']', self.raw(key));
    });

    if (this.context === 'default') {
      body.append('action', 'banners_og_save_default');
    } else {
      body.append('action', 'banners_og_save_post');
      body.append('post_id', String(this.postId));
      body.append('enabled', this.customized() ? '1' : '');
    }

    return window.fetch(data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function (response) {
        return response.json();
      })
      .then(function (json) {
        if (!json || !json.success) {
          throw new Error((json && json.data && json.data.message) || data.i18n.error);
        }

        return json.data;
      });
  };

  Editor.prototype.generateAndSave = function () {
    var self = this;

    if (this.uploading || typeof window.html2canvas !== 'function') {
      return Promise.resolve();
    }

    this.uploading = true;
    this.setStatus(data.i18n.generating, false);

    if (this.generateBtn) {
      this.generateBtn.disabled = true;
    }

    return this.capture()
      .then(function (blob) {
        self.setStatus(data.i18n.saving, false);

        return self.upload(blob);
      })
      .then(function (payload) {
        self.setStatus(payload.message || data.i18n.saved, false);
        self.showCurrent(payload.imageUrl);

        window.setTimeout(function () {
          self.setStatus('', false);
        }, 4000);
      })
      .catch(function (error) {
        if (window.console) {
          window.console.error(error);
        }

        self.setStatus((error && error.message) || data.i18n.error, true);
      })
      .then(function () {
        self.uploading = false;

        if (self.generateBtn) {
          self.generateBtn.disabled = false;
        }
      });
  };

  Editor.prototype.showCurrent = function (url) {
    if (!this.currentInfo || !url) {
      return;
    }

    var link = document.createElement('a');

    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = url.split('/').pop();

    this.currentInfo.classList.remove('is-empty');
    this.currentInfo.textContent = data.i18n.current + ' ';
    this.currentInfo.appendChild(link);
  };

  /** Media picker of the image fields, wired to the hidden input. */
  Editor.prototype.pickImage = function (wrap) {
    var self = this;
    var input = wrap.querySelector('.bog-field');
    var preview = wrap.querySelector('[data-bog-image-preview]');
    var clear = wrap.querySelector('[data-bog-image-clear]');
    var wp = window.wp;

    if (!input || !wp || !wp.media) {
      return;
    }

    var frame = wp.media({
      title: data.i18n.selectImage,
      button: { text: data.i18n.useImage },
      library: { type: 'image' },
      multiple: false
    });

    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      var size = attachment.sizes && attachment.sizes.large ? attachment.sizes.large : attachment;

      input.value = size.url || attachment.url || '';
      self.showImage(preview, input.value);

      if (clear) {
        clear.hidden = false;
      }

      self.render();
    });

    frame.open();
  };

  Editor.prototype.showImage = function (preview, url) {
    if (!preview) {
      return;
    }

    preview.innerHTML = '';

    if (!url) {
      return;
    }

    var img = document.createElement('img');

    img.src = url;
    img.alt = '';
    preview.appendChild(img);
  };

  Editor.prototype.bind = function () {
    var self = this;

    this.root.addEventListener('click', function (event) {
      var wrap = event.target.closest ? event.target.closest('[data-bog-image]') : null;

      if (!wrap) {
        return;
      }

      if (event.target.hasAttribute('data-bog-image-select')) {
        event.preventDefault();
        self.pickImage(wrap);

        return;
      }

      if (event.target.hasAttribute('data-bog-image-clear')) {
        event.preventDefault();

        var input = wrap.querySelector('.bog-field');

        input.value = '';
        self.showImage(wrap.querySelector('[data-bog-image-preview]'), input.getAttribute('placeholder') || '');
        event.target.hidden = true;
        self.render();
      }
    });

    function onChange(event) {
      if (!event.target.classList.contains('bog-field')) {
        return;
      }

      if (event.target.dataset.field === 'kind') {
        self.syncFields();
        self.syncPlaceholders();
      }

      self.render();
    }

    this.root.addEventListener('input', onChange);
    this.root.addEventListener('change', onChange);

    if (this.generateBtn) {
      this.generateBtn.addEventListener('click', function () {
        self.generateAndSave();
      });
    }

    if (this.enabledInput && this.customWrap) {
      this.enabledInput.addEventListener('change', function () {
        self.customWrap.hidden = !self.enabledInput.checked;
        self.syncFields();
        self.syncPlaceholders();
        self.render();
      });
    }

    if (this.context !== 'post') {
      return;
    }

    // The preview follows the title being typed in the editor.
    var wp = window.wp;

    if (wp && wp.data && wp.data.subscribe && wp.data.select && wp.data.select('core/editor')) {
      var lastTitle = this.liveTitle();

      wp.data.subscribe(function () {
        var title = self.liveTitle();

        if (title !== lastTitle) {
          lastTitle = title;
          self.syncPlaceholders();
          self.render();
        }
      });

      return;
    }

    var classicTitle = document.getElementById('title');

    if (classicTitle) {
      classicTitle.addEventListener('input', function () {
        self.syncPlaceholders();
        self.render();
      });
    }
  };

  /* ---------------------------------------------------------------------
   * Rebuild the banner when the post is saved
   * ------------------------------------------------------------------- */

  function hookPostSave(editor) {
    var wp = window.wp;

    // Block editor: as soon as the save finishes, rebuild this banner.
    if (wp && wp.data && wp.data.select && wp.data.select('core/editor')) {
      var wasSaving = false;

      wp.data.subscribe(function () {
        var store = wp.data.select('core/editor');
        var saving = store.isSavingPost() && !store.isAutosavingPost();

        if (wasSaving && !saving && !editor.uploading) {
          editor.syncPlaceholders();
          editor.render();
          editor.generateAndSave();
        }

        wasSaving = saving;
      });

      return;
    }

    // Classic editor: hold the submit, build the banner, then submit.
    var form = document.getElementById('post');

    if (!form) {
      return;
    }

    var bypass = false;

    form.addEventListener('submit', function (event) {
      if (bypass || editor.uploading) {
        return;
      }

      event.preventDefault();
      editor.syncPlaceholders();
      editor.render();

      editor.generateAndSave().then(function () {
        bypass = true;

        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
      });
    });
  }

  function boot() {
    var editors = document.querySelectorAll('.bog-editor');

    Array.prototype.forEach.call(editors, function (root) {
      if (root.dataset.bogReady === '1') {
        return;
      }

      root.dataset.bogReady = '1';

      var editor = new Editor(root);

      if (editor.context === 'post') {
        hookPostSave(editor);
      }
    });
  }

  api.boot = boot;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    // Gives late-registered layouts a chance to land before the first render.
    window.setTimeout(boot, 0);
  }
})(window, document);
