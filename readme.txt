=== Formtura ===
Contributors: formturateam
Tags: form, form builder, contact form, drag and drop, forms
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modern, intuitive, and powerful form builder for WordPress with a beautiful drag-and-drop interface.

== Description ==

Formtura is the modern, intuitive, and powerful form builder for WordPress. Create beautiful forms with an effortless, visually-driven form-building experience without sacrificing advanced functionality.

= Key Features =

* **Beautiful Drag & Drop Builder** - Intuitive interface with real-time preview
* **Pre-built Templates** - Start quickly with professionally designed form templates
* **Seventeen Field Types** - From single line text to file uploads, star ratings and sliders
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

= 1.0.3 =
Fixes form submissions not being saved and notification emails not sending. This upgrade rewrites the choice fields in your saved forms, so please back up your database first.

= 1.0.0 =
Initial release of Formtura - the modern form builder for WordPress.
