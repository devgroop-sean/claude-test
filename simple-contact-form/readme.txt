=== Simple Contact Form ===
Tags: contact form, contact, form, email
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, secure contact form with AJAX submit, spam protection and saved submissions.

== Description ==

Add `[simple_contact_form]` to any page, post or widget.

* Fields: name, email, optional phone and subject, message, optional consent checkbox
* AJAX submission with a no-JavaScript fallback
* Spam protection: nonce, honeypot field, minimum fill time, per-IP rate limiting
* Submissions emailed (plain text, Reply-To set to the sender) and saved under **Contact Form → Submissions**
* Accessible markup: labels, aria-invalid, live region for status messages
* Settings under **Contact Form → Settings**

= Shortcode attributes =

`[simple_contact_form title="Get in touch" button="Send"]`

= Developer hooks =

* `scf_is_spam` (filter) — `( bool $is_spam, array $values )` plug in Akismet, reCAPTCHA, Turnstile, etc.
* `scf_validate` (filter) — `( array $errors, array $values )` add custom validation rules.
* `scf_email` (filter) — `( array $mail, array $data )` change to/subject/body/headers.
* `scf_submission` (action) — `( array $data, bool $sent )` runs after a valid submission.
* `scf_client_ip` (filter) — return the real client IP if behind a trusted proxy/CDN.

== Installation ==

1. Upload the `simple-contact-form` folder to `/wp-content/plugins/` (or zip it and use Plugins → Add New → Upload).
2. Activate the plugin.
3. Set the recipient under Contact Form → Settings.
4. Add `[simple_contact_form]` to a page.

== Frequently Asked Questions ==

= Emails aren't arriving =

The plugin uses `wp_mail()`. Many hosts block or spam-folder PHP mail; install an SMTP plugin. Submissions are still saved in the admin when "Save submissions" is on.

= "Your session expired" errors on a cached page =

The form uses a WordPress nonce. If full-page caching serves pages older than ~12 hours, exclude the contact page from the cache.

== Changelog ==

= 1.0.0 =
* Initial release.
