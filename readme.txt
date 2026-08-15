=== Formtura ===
Contributors: formturateam
Tags: form, form builder, contact form, drag and drop, forms
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modern, intuitive, and powerful form builder for WordPress with a beautiful drag-and-drop interface.

== Description ==

Formtura is the modern, intuitive, and powerful form builder for WordPress. Create beautiful forms with an effortless, visually-driven form-building experience without sacrificing advanced functionality.

= Key Features =

* **Beautiful Drag & Drop Builder** - Intuitive interface with real-time preview
* **Pre-built Templates** - Start quickly with professionally designed form templates
* **Twenty-Nine Field Types** - From single line text to file uploads, signatures and priced payment items
* **File Uploads** - Validated, safely stored, and optionally attached to your notification emails
* **Built-in SMTP** - Ensure reliable email delivery with integrated SMTP configuration
* **Entry Management** - View, search, and export form submissions
* **Conditional Logic** - Show or hide fields based on user input
* **Email Notifications** - Customizable email templates for form submissions
* **Mobile Responsive** - Forms look great on all devices
* **Translation Ready** - Fully internationalized and ready for translation

= Standard Fields =

* Single Line Text
* Paragraph Text
* Name
* Email
* Dropdown
* Multiple Choice
* Checkboxes
* Numbers
* Phone
* Website / URL
* HTML
* Hidden Field

= Advanced Fields =

* Date / Time
* Password
* File Upload
* Star Rating
* Slider
* Address
* Camera
* Signature

= Presentation Fields =

* Content
* Section Divider
* Rich Text

= Payment Fields =

* Single Item
* Checkbox Items
* Multiple Items
* Dropdown Items
* Coupon
* Total

Payment fields record the amount with the form entry so you can see what
was ordered. They do not collect payment - Formtura has no payment
gateway integration yet, so no card is charged and no money moves.

== Installation ==

1. Upload the `formtura` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Formtura in your WordPress admin menu to create your first form
4. Use the shortcode `[formtura id="123"]` or the Gutenberg block to embed forms

== Frequently Asked Questions ==

= How do I create my first form? =

After activating Formtura, go to Formtura > Add New in your WordPress admin. You can start with a blank form or choose from our pre-built templates.

= How do I embed a form on my site? =

You can embed forms using the shortcode `[formtura id="123"]` (replace 123 with your form ID) or use the Formtura Gutenberg block in the block editor.

= Does Formtura work with my theme? =

Yes! Formtura is designed to work with any properly coded WordPress theme.

= Can I export form entries? =

Yes, you can export entries to CSV format from the Entries page.

== Screenshots ==

1. Beautiful drag-and-drop form builder interface
2. Pre-built form templates
3. Entry management dashboard
4. SMTP configuration for reliable email delivery
5. Advanced field options and conditional logic

== Changelog ==

= 1.0.5 =
* Uploaded files and signatures are now stored outside the public web root, and are no longer reachable by URL. Previously they lived in wp-content/uploads/formtura, protected only by an .htaccess file - which nginx and IIS ignore, leaving every uploaded file readable by anyone who knew or guessed its address
* Existing files are moved into the new private location automatically when the plugin updates. If the move cannot complete, the plugin says so and retries on the next update rather than reporting success
* File links in notification emails now point at an administrator-only download, so forwarding a notification no longer forwards access to the file. Files explicitly set to "attach to email" still attach as before
* Fixed the plugin deleting all of your forms, entries and settings on uninstall regardless of the "Delete Data on Uninstall" setting. That checkbox never saved, so the setting was always read as off and every uninstall was destructive. Data is now kept unless you explicitly opt in
* Fixed uploaded files being left behind when one file in a multi-file upload field was rejected. Files accepted before the rejection stayed on the server with no entry referencing them
* Deleting an entry or a form now deletes the files it owned, instead of leaving them on the server forever
* Fixed a form being deleted even when its entries could not be, which left those entries stranded
* Release packages are now built with a script that includes dependencies and compiled assets. A ZIP downloaded from the source repository was never installable and would fatal on activation

= 1.0.4 =
* Added twelve field types to the frontend: Content, Section Divider, Rich Text, Address, Camera, Signature, Single Item, Checkbox Items, Multiple Items, Dropdown Items, Coupon and Total. Twenty-nine of the builder's field types now render on your site
* Added payment fields that record what was ordered with the entry, including per-item prices, a running total, an optional order summary and coupon codes. No payment is collected - Formtura has no gateway integration yet, so no card is charged
* Fixed reCAPTCHA rejecting every submission. No token was ever generated, so saving a secret key silently broke every form on the site. v2 and v3 both work now, and v3 checks the score and action
* Added a reCAPTCHA version selector to Settings. Without it the version was reset on every save, so v3 keys could never be used
* Fixed the form builder silently discarding field settings when you saved. Payment prices, coupon codes, the address format and every file upload setting - size limits, allowed file types, attach-to-email - were thrown away, so those panels had no effect on the published form. File upload settings had been affected since 1.0.3
* Fixed the Content and HTML blocks rendering nothing. The builder saved your text under one key while the frontend read another
* Fixed a Total field marked Required blocking every submission, with an error the visitor had no way to clear
* Fixed uploaded files being left behind on the server when a submission was rejected
* Fixed the Camera field being impossible to open when its label was hidden
* Fixed the signature pad keeping a drawing on screen after a successful submission, and continuing to draw after an interrupted stroke
* Payment amounts are now calculated on the server from the form itself, so a tampered page cannot change what is recorded
* Signatures are stored as image files in the same protected location as file uploads, and are recorded with the entry
* The Rich Text field ships as a plain text area, and the builder no longer shows formatting buttons it does not provide
* The payment gateway fields, along with Layout, Repeater, Page Break and Entry Preview, can no longer be dropped onto a form, where they previously rendered nothing. They now explain that they are not available yet

= 1.0.3 =
* Added frontend rendering for 14 more field types, including Dropdown, Multiple Choice, Checkboxes, Name, Date, Star Rating and Slider
* Added server-side file upload handling: size and file type validation, protected storage, and the option to attach uploads to notification emails
* Fixed form submissions not being saved. Submitted values are now stored against the correct field
* Fixed email notifications failing to send
* Renamed the choice fields so they match what they do: "Multiple Choice" accepts one answer, "Checkboxes" accepts several. Existing forms are updated automatically on upgrade
* Fields with no frontend template no longer disappear from the page without warning
* The documented field list now covers only the types that render on your site. Types that were listed but never worked have been removed until they ship

= 1.0.0 =
* Initial release
* Drag-and-drop form builder
* Pre-built templates
* Entry management
* SMTP integration
* Standard field types
* Email notifications
* CAPTCHA support

== Upgrade Notice ==

= 1.0.5 =
This upgrade moves every uploaded file and signature out of your public
uploads directory into a private directory outside the web root, because
their old location was readable by anyone who knew the URL on nginx and IIS.
Back up wp-content/uploads/formtura first. Any direct links you have shared
to those files will stop working; file links in notifications now require a
logged-in administrator. If your host cannot write beside the WordPress
directory, define FORMTURA_PRIVATE_UPLOAD_DIR in wp-config.php to a writable
directory outside the web root before updating - the plugin will tell you if
the move did not complete. Uninstall also no longer deletes your data unless
you have ticked "Delete Data on Uninstall", which previously never saved.

= 1.0.4 =
Two fixes change how existing forms behave. Any form using reCAPTCHA was
rejecting every submission and will start working again. File upload
settings, which were previously discarded when you saved, now take effect -
so check the size limits and allowed file types on your upload fields match
what you intended. If you saved reCAPTCHA v3 keys, choose v3 in Settings.

= 1.0.3 =
Fixes form submissions not being saved and notification emails not sending. This upgrade rewrites the choice fields in your saved forms, so please back up your database first.

= 1.0.0 =
Initial release of Formtura - the modern form builder for WordPress.
