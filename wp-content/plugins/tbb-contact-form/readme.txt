=== TBB Contact Form ===
Contributors: tbb
Tags: contact form, popup, ajax
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Popup contact form that emails the admin and stores submissions for viewing in wp-admin.

== Description ==

1. In wp-admin go to **TBB Contact** and create a form under the default “Forms” screen (add fields, button label, popup title).
2. Copy the shortcode shown for that form (for example `[tbb_contact_form id="1"]`).
3. Paste the shortcode into any page or post.

Optional: override the button label on that page only:

[tbb_contact_form id="1" button_label="Contact us now"]

The shortcode outputs a button; clicking it opens a popup (modal) without leaving the page. Submissions are emailed to the site admin, stored in the database, and listed under **TBB Contact → Messages**.

== Updates via GitHub ==

This plugin checks GitHub for releases whose tag starts with `tbb-contact-form/` (for example `tbb-contact-form/v1.1.1`). The default repo is `lehart1j/tbb-wordpress-plugins`; override with `define( 'TBB_CONTACT_FORM_GITHUB_REPO', 'owner/repo' );` in `wp-config.php`.

Each release must include a **manually uploaded** zip asset named `tbb-contact-form.zip`. The zip must unpack to a folder named `tbb-contact-form` containing the plugin files (do not use GitHub’s auto “Source code” archive — it is the whole repo and will not install correctly).

Optional: `define( 'TBB_CONTACT_FORM_GITHUB_TOKEN', '…' );` for higher API rate limits.

