=== TBB Contact Form ===
Contributors: tbb
Tags: contact form, popup, ajax
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.1.0
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

== Updates via GitHub (optional) ==

If you keep this plugin in a GitHub repo, you can enable WordPress update checks from GitHub Releases.

Add the following constants somewhere that loads on every request (commonly `wp-config.php`):

define('TBB_CONTACT_FORM_GITHUB_REPO', 'OWNER/REPO');
// For a monorepo (many plugins in one repo), this plugin expects tags like:
// tbb-contact-form/v1.0.1
// For private repos, also add a GitHub token that can read releases:
// define('TBB_CONTACT_FORM_GITHUB_TOKEN', 'YOUR_TOKEN_HERE');

Then publish a GitHub Release and attach a zip asset named:

tbb-contact-form.zip

