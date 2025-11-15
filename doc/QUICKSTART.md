# Formtura - Quick Start Guide

## 🚀 Getting Started

### Prerequisites
- WordPress 5.8 or higher
- PHP 7.4 or higher
- Composer installed
- Node.js & npm installed (for development)

### Installation

1. **Install PHP Dependencies**
   ```bash
   cd /path/to/wp-content/plugins/formtura
   composer install
   ```

2. **Install JavaScript Dependencies** (for development)
   ```bash
   npm install
   ```

3. **Build React App**
   ```bash
   npm run build
   ```

4. **Activate Plugin**
   - Go to WordPress Admin > Plugins
   - Find "Formtura" and click "Activate"

## 📝 Creating Your First Form

### Step 1: Access the Form Builder
1. In WordPress admin, go to **Formtura > Add New**
2. Or navigate to: `wp-admin/admin.php?page=formtura-builder`

### Step 2: Build Your Form
1. **Drag fields** from the left sidebar to the canvas
2. **Click a field** to edit its settings in the right panel
3. **Configure field properties**:
   - Label (required)
   - Placeholder text
   - Description
   - Required checkbox
   - Options (for select/radio/checkbox)

### Step 3: Configure Form Settings
1. Click the **"Form"** tab in the right panel
2. Set:
   - Form title
   - Form description
   - Submit button text
   - Success message

### Step 4: Save Your Form
1. Click **"Save Form"** in the top right
2. Your form will be saved and you'll get a form ID

### Step 5: Embed Your Form

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

## 🎨 Available Field Types

### Basic Fields
- **Text** - Single line text input
- **Email** - Email address with validation
- **Textarea** - Multi-line text input
- **Number** - Numeric input

### Choice Fields
- **Dropdown** - Select from options
- **Radio** - Single choice from options
- **Checkbox** - Multiple choices from options

### Advanced Fields
- **Name** - First and last name fields
- **Phone** - Phone number input
- **Date** - Date picker

## ⚙️ Field Settings

### General Settings (All Fields)
- **Label**: The field label displayed to users
- **Placeholder**: Hint text shown in empty fields
- **Description**: Help text shown below the field
- **Required**: Make the field mandatory

### Choice Field Options
- Add/remove/edit options
- Reorder options
- Minimum 1 option required

### Textarea Settings
- **Rows**: Number of visible text lines (2-20)

## 🔧 Form Settings

### Form Information
- **Form Title**: Internal name for your form
- **Form Description**: Optional description

### Submit Button
- **Button Text**: Customize the submit button text (default: "Submit")

### Success Message
- **Message**: Text shown after successful submission

## 💡 Tips & Tricks

### Reordering Fields
- Click and drag the **grip icon** (⋮⋮) on any field to reorder

### Duplicating Fields
- Click the **copy icon** on a field to create a duplicate

### Deleting Fields
- Click the **trash icon** on a field
- Confirm the deletion

### Keyboard Shortcuts
- Click a field to select it
- Use Tab to navigate between fields
- Press Delete to remove selected field (with confirmation)

## 🐛 Troubleshooting

### Builder Not Loading?
1. Check browser console for errors
2. Ensure `assets/js/builder.js` and `assets/css/builder.css` exist
3. Run `npm run build` to rebuild assets
4. Clear WordPress cache

### Can't Save Forms?
1. Check that you have `manage_options` capability
2. Verify AJAX URL is correct
3. Check PHP error logs
4. Ensure database tables were created

### Fields Not Dragging?
1. Ensure you're using a modern browser
2. Check for JavaScript errors in console
3. Try refreshing the page
4. Clear browser cache

## 📚 Development

### Development Mode
```bash
npm run dev
```
This starts Vite dev server with hot module replacement.

### Production Build
```bash
npm run build
```
Creates optimized production bundles.

### File Structure
```
formtura/
├── builder/              # React source code
│   ├── components/       # React components
│   ├── utils/           # Helper functions
│   └── styles/          # CSS styles
├── assets/              # Built assets
│   ├── js/             # JavaScript bundles
│   └── css/            # CSS bundles
├── src/                # PHP source code
│   ├── Admin/          # Admin functionality
│   ├── Frontend/       # Frontend functionality
│   └── Database/       # Database operations
└── templates/          # Template files
```

## 🔐 Security

- All AJAX requests use WordPress nonces
- Capability checks on all admin actions
- Input sanitization on all form data
- SQL injection prevention with prepared statements
- XSS protection with output escaping

## 📖 Next Steps

1. **Create your first form** using the builder
2. **Embed it** on a page or post
3. **Test submissions** and check entries
4. **Configure SMTP** for reliable email delivery
5. **Explore integrations** (Mailchimp, etc.)

## 🆘 Need Help?

- Check the [README.md](README.md) for detailed documentation
- Review [PHASE2_COMPLETE.md](PHASE2_COMPLETE.md) for technical details
- Check [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md) for architecture info

---

**Happy Form Building! 🎉**
