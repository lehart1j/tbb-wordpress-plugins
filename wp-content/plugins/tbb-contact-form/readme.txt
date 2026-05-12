=== TBB Contact Form ===
Contributors: tbb
Tags: contact form, popup, ajax
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Popup contact form that emails the admin and stores submissions for viewing in wp-admin.

== Description ==

Use the shortcode:

[tbb_contact_button label="Contact us"]

This renders a button; clicking it opens a popup form (modal) without leaving the page. Submissions:

- Email the site admin
- Are stored in the database
- Are viewable in wp-admin under “Contact Messages”

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

