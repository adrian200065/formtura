# Formtura - Modern WordPress Form Builder

A modern, intuitive, and powerful form builder for WordPress with a beautiful drag-and-drop interface.

## Features

- **Beautiful Drag & Drop Builder** - Intuitive interface with real-time preview
- **Pre-built Templates** - Start quickly with professionally designed form templates
- **Advanced Field Types** - From basic text fields to complex payment integrations
- **Built-in SMTP** - Ensure reliable email delivery with integrated SMTP configuration
- **Entry Management** - View, search, and export form submissions
- **Conditional Logic** - Show or hide fields based on user input
- **Email Notifications** - Customizable email templates for form submissions
- **Spam Protection** - Built-in CAPTCHA support
- **Mobile Responsive** - Forms look great on all devices
- **Translation Ready** - Fully internationalized and ready for translation

## Installation

### Via WordPress Admin

1. Download the plugin ZIP file
2. Go to WordPress Admin > Plugins > Add New
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now" and then "Activate"

### Manual Installation

1. Upload the `formtura` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress

### Post-Installation Setup

1. Run `composer install` in the plugin directory to install PHP dependencies
2. Run `npm install` to install JavaScript dependencies (optional, for development)
3. Navigate to Formtura in your WordPress admin menu to create your first form

## Usage

### Creating a Form

1. Go to **Formtura > Add New** in your WordPress admin
2. Choose a template or start with a blank form
3. Drag and drop fields onto the canvas
4. Configure field settings in the right panel
5. Set up notifications and form settings
6. Click "Save" to save your form

### Embedding a Form

**Using Shortcode:**
```
[formtura id="123"]
```

**Using Gutenberg Block:**
1. Add a new block in the editor
2. Search for "Formtura"
3. Select your form from the dropdown

**Using PHP:**
```php
<?php echo fta_render_form( 123 ); ?>
```

## Development

### Requirements

- PHP 7.4 or higher
- WordPress 5.8 or higher
- Composer (for PHP dependencies)
- Node.js & npm (for JavaScript development)

### Setup Development Environment

```bash
# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install

# Build assets for development
npm run dev

# Build assets for production
npm run build
```

### Coding Standards

This plugin follows WordPress Coding Standards with modern enhancements:

- **PHP**: PSR-4 autoloading, shorthand array syntax `[]`
- **CSS**: rem/em units, logical properties, HSL colors, CSS custom properties
- **JavaScript**: ES6+ syntax with modern best practices

### File Structure

```
formtura/
├── assets/              # Compiled CSS, JS, images, fonts
├── src/                 # PHP source code
│   ├── Admin/          # Admin functionality
│   ├── Frontend/       # Frontend functionality
│   ├── Database/       # Database operations
│   ├── Integrations/   # Third-party integrations
│   ├── Blocks/         # Gutenberg blocks
│   └── Utils/          # Utility classes
├── templates/          # Overridable template files
├── languages/          # Translation files
├── formtura.php        # Main plugin file
└── composer.json       # PHP dependencies
```

## Hooks & Filters

### Actions

- `fta_after_form_submission` - Fires after a form is successfully submitted
- `fta_after_notification_sent` - Fires after an email notification is sent

### Filters

- `fta_field_types` - Filter available field types
- `fta_form_templates` - Filter available form templates
- `fta_integrations` - Filter available integrations
- `fta_parse_smart_tags` - Filter smart tag parsing

## Database Tables

The plugin creates three custom tables:

- `wp_fta_forms` - Stores form configurations
- `wp_fta_entries` - Stores form submissions
- `wp_fta_entry_meta` - Stores entry field data

## Support

For support, please visit [https://formtura.com/support](https://formtura.com/support)

## License

GPL v2 or later

## Credits

Developed by the Formtura Team
