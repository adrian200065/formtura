# Formtura Setup Guide

This guide will help you get Formtura up and running on your WordPress site.

## Quick Start

### 1. Install Dependencies

Navigate to the plugin directory and install PHP dependencies:

```bash
cd /mnt/Sites/wpsun/wp-content/plugins/formtura
composer install
```

### 2. Activate the Plugin

1. Go to WordPress Admin → Plugins
2. Find "Formtura" in the list
3. Click "Activate"

The plugin will automatically:
- Create custom database tables
- Set default options
- Register admin menus

### 3. Verify Installation

After activation, you should see:
- "Formtura" menu item in WordPress admin sidebar
- No error messages
- Access to all submenu items (Forms, Add New, Entries, Settings, SMTP)

## Next Steps

### Create Your First Form

1. Go to **Formtura → Add New**
2. Choose a template or start with a blank form
3. The form builder interface will load (note: React app needs to be built for full functionality)

### Configure SMTP (Optional but Recommended)

1. Go to **Formtura → SMTP**
2. Enable SMTP
3. Choose your mailer (SMTP, SendGrid, Mailgun, or Amazon SES)
4. Enter your credentials
5. Send a test email to verify configuration

### Configure Settings

1. Go to **Formtura → Settings**
2. Review and adjust:
   - General settings (CSS/JS loading)
   - reCAPTCHA keys (for spam protection)
   - Currency settings (for payment forms)
   - Uninstall behavior

## Building the Form Builder UI (Development)

The drag-and-drop form builder requires building the React application:

### Install Node Dependencies

```bash
npm install
```

### Development Build (with hot reload)

```bash
npm run dev
```

### Production Build

```bash
npm run build
```

This will compile the React application and place it in `/assets/js/builder.js`.

## Database Tables

The plugin creates three custom tables:

| Table | Purpose |
|-------|---------|
| `wp_fta_forms` | Stores form configurations (title, fields, settings) |
| `wp_fta_entries` | Stores form submission metadata |
| `wp_fta_entry_meta` | Stores individual field values for each entry |

## File Permissions

Ensure the following directories are writable:

- `/wp-content/plugins/formtura/vendor/` (for Composer)
- `/wp-content/plugins/formtura/node_modules/` (for npm)
- `/wp-content/uploads/` (for file uploads from forms)

## Troubleshooting

### "Composer dependencies required" notice

**Solution:** Run `composer install` in the plugin directory.

### Forms not displaying on frontend

**Check:**
1. Form status is "Active"
2. Correct form ID in shortcode: `[formtura id="123"]`
3. CSS/JS loading is enabled in Settings

### Email notifications not sending

**Check:**
1. SMTP is configured correctly
2. Test email works from SMTP settings page
3. Notification is enabled in form settings
4. Check server error logs

### Form builder not loading

**Check:**
1. JavaScript console for errors
2. Builder assets are compiled (`npm run build`)
3. Browser compatibility (modern browsers required)

## Default Credentials & Settings

After installation, default settings are:

- **Load CSS:** Enabled
- **Load JS:** Enabled
- **Debug Mode:** Disabled
- **Currency:** USD
- **Keep Data on Uninstall:** Disabled

## Security Considerations

1. **API Keys:** Store sensitive keys (SMTP, reCAPTCHA) securely
2. **File Uploads:** Configure allowed file types and size limits
3. **Spam Protection:** Enable reCAPTCHA for public forms
4. **Data Privacy:** Review GDPR compliance for your region

## Performance Optimization

1. **Caching:** Formtura is compatible with WordPress caching plugins
2. **Asset Loading:** Disable CSS/JS loading if using custom styles
3. **Database:** Regularly clean old entries to maintain performance

## Getting Help

- **Documentation:** [https://formtura.com/docs](https://formtura.com/docs)
- **Support:** [https://formtura.com/support](https://formtura.com/support)
- **GitHub Issues:** Report bugs and request features

## Uninstallation

To completely remove Formtura:

1. Go to **Formtura → Settings**
2. Set "Keep Data on Uninstall" to **No** (if you want to remove all data)
3. Deactivate the plugin
4. Delete the plugin

This will:
- Drop all custom database tables
- Remove all plugin options
- Clean up any cached data

---

**Version:** 1.0.0  
**Last Updated:** 2024
