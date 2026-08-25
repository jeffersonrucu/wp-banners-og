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

  api.helpers = { esc: esc, image: image, corners: corners };

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
      kind: kind,
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
    return '' +
      '<div class="bog-canvas bog-canvas--cover">' +
        ctx.corners() +
        '<div class="bog-content">' +
          '<div class="bog-eyebrow">' + ctx.esc(f.eyebrow) + '</div>' +
          ctx.image(ctx.images.mark, 'bog-mark') +
          '<div class="bog-title">' + ctx.esc(f.title) + '</div>' +
          '<div class="bog-divider"></div>' +
          '<div class="bog-sub">' + ctx.esc(f.sub) + '</div>' +
        '</div>' +
        '<div class="bog-footer">' +
          '<div class="bog-brand">' + ctx.esc(ctx.brand) + '</div>' +
          '<div class="bog-dot"></div>' +
          '<div class="bog-foot">' + ctx.esc(f.foot) + '</div>' +
        '</div>' +
      '</div>';
  });

  api.registerTemplate('feature', function (f, ctx) {
    return '' +
      '<div class="bog-canvas bog-canvas--feature">' +
        ctx.image(ctx.images.mark, 'bog-mark-bg') +
        '<div class="bog-brand">' + ctx.esc(ctx.brand) + '</div>' +
        '<div class="bog-content">' +
          '<div class="bog-eyebrow">' + ctx.esc(f.eyebrow) + '</div>' +
          '<div class="bog-title">' + ctx.esc(f.title) + '</div>' +
          '<div class="bog-sub">' + ctx.esc(f.sub) + '</div>' +
        '</div>' +
        '<div class="bog-footer">' +
          '<div class="bog-footline"></div>' +
          '<div class="bog-foot">' + ctx.esc(f.foot) + '</div>' +
        '</div>' +
      '</div>';
  });

  api.registerTemplate('article', function (f, ctx) {
    var mark = ctx.image(ctx.images.mark, 'bog-mark');

    return '' +
      '<div class="bog-canvas bog-canvas--article">' +
        '<div class="bog-side">' +
          (mark ? '<div class="bog-mark-plate">' + mark + '</div>' : '') +
          '<div class="bog-brand">' + ctx.esc(ctx.brand) + '</div>' +
        '</div>' +
        '<div class="bog-content">' +
          '<div class="bog-eyebrow">' + ctx.esc(f.eyebrow) + '</div>' +
          '<div class="bog-title">' + ctx.esc(f.title) + '</div>' +
          '<div class="bog-divider"></div>' +
          '<div class="bog-sub">' + ctx.esc(f.sub) + '</div>' +
          '<div class="bog-foot">' + ctx.esc(f.foot) + '</div>' +
        '</div>' +
      '</div>';
  });

  api.registerTemplate('profile', function (f, ctx) {
    return '' +
      '<div class="bog-canvas bog-canvas--profile">' +
        ctx.corners() +
        '<div class="bog-content">' +
          '<div class="bog-eyebrow">' + ctx.esc(f.eyebrow) + '</div>' +
          (ctx.images.logo
            ? ctx.image(ctx.images.logo, 'bog-logo')
            : ctx.image(ctx.images.mark, 'bog-mark')) +
          '<div class="bog-title">' + ctx.esc(f.title) + '</div>' +
          '<div class="bog-divider"></div>' +
          '<div class="bog-sub">' + ctx.esc(f.sub) + '</div>' +
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

  /** Effective value of a field: what was typed, or its placeholder. */
  Editor.prototype.value = function (name) {
    var input = this.field(name);

    if (!input) {
      return '';
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
      var input = self.field(key);

      body.append('fields[' + key + ']', input ? input.value : '');
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

  Editor.prototype.bind = function () {
    var self = this;

    function onChange(event) {
      if (!event.target.classList.contains('bog-field')) {
        return;
      }

      if (event.target.dataset.field === 'kind') {
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
