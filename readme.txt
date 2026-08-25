=== Banners OG ===
Contributors: jeffersonrucu
Tags: open graph, og image, twitter card, social sharing, share image
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Builds 1200x630 Open Graph banners in the WordPress admin and publishes them as og:image. No external service, no GD or Imagick.

== Description ==

Banners OG renders your sharing images inside the WordPress admin. The banner is
laid out in HTML and CSS, captured in the browser with html2canvas and posted back
as a JPEG. PHP only validates the file and stores it, so the plugin needs neither
an image extension on the server nor a third-party rendering service.

**What you get**

* A default banner per layout, edited on the *Banners OG* screen.
* A per-content banner for every supported post type, rebuilt automatically when
  you save the post.
* `og:*` and `twitter:*` meta tags on the front end, skipped automatically when
  Yoast SEO, Rank Math, All in One SEO or SEOPress is active.
* An appearance screen for the palette, the font stacks and the brand images, so
  the banners follow the identity of the site instead of a hardcoded brand.

**Four layouts, or your own**

The plugin ships with Cover, Feature, Article and Profile. Developers can register
extra layouts, add fields to them and enqueue their own CSS, without touching the
plugin. The extension points are documented in README.md.

**Where the files live**

Banners are stored in `wp-content/uploads/banners-og/` instead of the media
library, so they do not clutter it and cannot be deleted by accident. Each rebuild
replaces the previous file and uses a timestamped name to bust the cache of the
social networks.

**Third-party code**

The capture step uses html2canvas 1.4.1 (MIT), bundled in
`assets/vendor/html2canvas/`. Source: https://github.com/niklasvh/html2canvas

== Installation ==

1. Upload the plugin to `wp-content/plugins/banners-og` and activate it.
2. Open **Banners OG > Appearance** and set the palette, the font stacks and the
   brand images.
3. Open **Banners OG**, review the copy of each layout and click *Generate and
   save banner*.
4. Posts and pages get their own banner automatically when you save them.

== Frequently Asked Questions ==

= Does it need GD or Imagick? =

No. The image is rendered by the browser of the logged-in editor and the server
only validates and stores the resulting JPEG.

= Why is a banner missing after a deploy? =

The banners are plain files under `wp-content/uploads/banners-og/`. If that folder
is not carried over between environments, open the screen again and generate them.

= Can I use it with an SEO plugin? =

Yes. The meta tags are skipped when a known SEO plugin is active, so it does not
duplicate them. Use the `banners_og_output_tags` filter to force either behaviour.

= Can I add my own layout? =

Yes. Register it with the `banners_og_kinds` filter, add its renderer with
`window.BannersOG.registerTemplate()` and enqueue its CSS on the
`banners_og_enqueue_assets` action. See README.md.

== Changelog ==

= 2.0.0 =

* Reusable release: no brand-specific copy, colours, images or post types left in
  the code.
* New appearance screen for the palette, the font stacks and the brand images.
* Layouts, fields and default copy are now filterable, and custom layouts can be
  registered from JavaScript.
* The banner is posted as a regular multipart upload and stored through
  `wp_handle_upload()`.
* No external font request by default.

= 1.1.0 =

* Banners are stored as files under `wp-content/uploads/` instead of media library
  attachments.

= 1.0.0 =

* First release.

== Upgrade Notice ==

= 2.0.0 =

Option keys, meta keys and the uploads folder were renamed, and the layout names
changed. Existing banners and settings are not migrated: regenerate them after
upgrading.
