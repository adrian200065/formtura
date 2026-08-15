# Formtura - Modern WordPress Form Builder

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![React](https://img.shields.io/badge/React-18.2-61dafb.svg)](https://reactjs.org/)
[![License](https://img.shields.io/badge/License-GPL--2.0-green.svg)](LICENSE)

A powerful, modern WordPress form builder plugin with a drag-and-drop interface, built with React and following WordPress coding standards.

![Formtura Form Builder](https://via.placeholder.com/800x400/4F46E5/FFFFFF?text=Formtura+Form+Builder)

## ✨ Features

### 🎨 Drag-and-Drop Form Builder
- **Intuitive Interface** - Build forms visually with drag-and-drop
- **Real-time Preview** - See exactly how your form will look
- **Modern UI** - Clean, professional interface built with React

### 📐 Advanced Grid Layouts
- **9 Layout Options** - From simple 2-column to complex 4-column layouts
- **Responsive Design** - Automatically stacks on mobile devices
- **Custom Widths** - 1/2, 1/3, 2/3, 1/4, 2/4, 3/4 column widths
- **Click-to-Select** - Click individual columns to apply layouts

### 📝 Field Types
- Text Input
- Email
- Textarea
- Number
- Tel
- Date
- Select Dropdown
- Radio Buttons
- Checkboxes
- Hidden Fields

### 🎯 Field Options
- **Labels & Descriptions** - Customize field text
- **Required Fields** - Mark fields as mandatory
- **Placeholders** - Add helpful hints
- **CSS Classes** - Apply custom styling
- **Field Sizes** - Small, medium, or large
- **Conditional Logic** - Show/hide fields based on conditions

### 📊 Form Management
- **Save & Edit** - Create and modify forms easily
- **Duplicate Forms** - Clone existing forms
- **Entry Management** - View and manage form submissions
- **Export Data** - Download entries as CSV

### 📧 Email Notifications
- **Custom Templates** - Design email notifications
- **Multiple Recipients** - Send to multiple email addresses
- **SMTP Support** - Configure custom SMTP settings
- **Merge Tags** - Include form data in emails

### 🔌 Integrations
- **Block Editor** - Gutenberg block for form insertion
- **Shortcodes** - `[formtura id="123"]`
- **PHP Functions** - `formtura_form(123)`
- **REST API** - Programmatic access

## 🚀 Installation

> **A source archive is not an installable plugin.** A ZIP produced by
> `git archive`, or by GitHub's "Download ZIP", contains no `vendor/` and no
> compiled builder assets, so activating it fails. Either follow the source
> checkout steps below, or build a real package with
> `scripts/build-release.sh` (see [Releases](#-releases)).

### From GitHub

1. **Clone the repository:**
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/yourusername/formtura.git
   cd formtura
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Install Node dependencies:**
   ```bash
   npm install
   ```

4. **Build assets:**
   ```bash
   npm run build
   ```

5. **Activate the plugin:**
   - Go to WordPress Admin → Plugins
   - Find "Formtura" and click "Activate"

### Development Setup

For development with hot reload:

```bash
npm run dev
```

This starts Vite dev server with HMR (Hot Module Replacement).

## 🔐 File storage

Uploaded files and signatures are stored **outside the WordPress document
root**, in a private vault. They are never reachable by URL, and the previous
location (`wp-content/uploads/formtura`, guarded only by an `.htaccess` file
that nginx and IIS ignore) is migrated automatically on upgrade.

By default the vault sits beside `ABSPATH`, named with a hash of the
WordPress path so that several installations under one parent directory
cannot collide. When that parent is not writable — common on managed hosts —
point it somewhere else in `wp-config.php`:

```php
define( 'FORMTURA_PRIVATE_UPLOAD_DIR', '/var/private/formtura' );
```

The directory must be **writable and outside the web root**. If the vault
cannot be written, file-producing submissions fail rather than falling back
to a public directory, and an administrator notice explains why.

Files are downloaded through an authenticated route that requires a
logged-in user with `manage_options`. Links in notification emails point at
that route, so forwarding a notification does not forward access to the file.
A field's **attach to email** option still attaches the file directly and
remains the one deliberate exception to link-only delivery.

## 📦 Releases

`scripts/build-release.sh` is the only supported way to produce an
installable package:

```bash
scripts/build-release.sh          # writes dist/formtura-<version>.zip
scripts/verify-release.sh <zip>   # asserts a package is complete
```

The build runs in an isolated temporary workspace, installs production
dependencies and compiles assets from committed manifests, then copies only
runtime files using `.distignore`. It refuses to emit a ZIP that is missing
`vendor/autoload.php` or the compiled builder assets, or that carries
development-only content. CI runs the same script on every pull request.

## 📖 Usage

### Creating Your First Form

1. **Navigate to Formtura** in WordPress admin
2. **Click "Add New"** to create a form
3. **Drag fields** from the left sidebar to the canvas
4. **Configure fields** by clicking on them
5. **Set up layouts** in the Advanced tab
6. **Click Preview** to see your form
7. **Click Save** to save your form

### Adding Grid Layouts

1. **Select a field** on the canvas
2. **Go to Advanced tab** in the left sidebar
3. **Click "Show Layouts"**
4. **Click a column** in the layout preview
5. **Repeat for other fields** in the same row

Example: 2-Column Layout
- Field 1: Click **left column** → Gets `fta-one-half fta-first`
- Field 2: Click **right column** → Gets `fta-one-half`

### Embedding Forms

#### Shortcode
```php
[formtura id="123"]
```

#### Gutenberg Block
1. Add "Formtura Form" block
2. Select your form from dropdown

#### PHP Function
```php
<?php
if (function_exists('formtura_form')) {
    formtura_form(123);
}
?>
```

## 🛠️ Development

### Project Structure

```
formtura/
├── assets/              # Compiled CSS and JS
│   ├── css/
│   └── js/
├── builder/             # React form builder source
│   ├── components/      # React components
│   ├── styles/          # CSS styles
│   └── utils/           # Helper functions
├── doc/                 # Documentation
├── languages/           # Translation files
├── src/                 # PHP source code
│   ├── Admin/           # Admin interface
│   ├── Blocks/          # Gutenberg blocks
│   ├── Database/        # Database operations
│   ├── Frontend/        # Frontend rendering
│   └── Integrations/    # Third-party integrations
├── templates/           # PHP templates
└── vendor/              # Composer dependencies
```

### Build Commands

```bash
# Development build with watch
npm run dev

# Production build
npm run build

# Watch mode (rebuild on changes)
npm run watch

# Lint JavaScript
npm run lint

# Format code
npm run format
```

### Coding Standards

- **PHP**: WordPress Coding Standards (PHPCS)
- **JavaScript**: ESLint with React rules
- **CSS**: Modern CSS with logical properties
- **Git**: Conventional Commits

### Running Tests

```bash
# PHP tests
composer test

# JavaScript tests
npm test

# Code quality checks
composer phpcs
npm run lint
```

## 📋 Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **Node.js**: 16.x or higher (for development)
- **Composer**: 2.x or higher (for development)

## 🎨 Grid Layout System

### Available Layouts

| Layout | Columns | CSS Classes |
|--------|---------|-------------|
| **2-Column Equal** | 1/2 + 1/2 | `fta-one-half` |
| **3-Column Equal** | 1/3 + 1/3 + 1/3 | `fta-one-third` |
| **4-Column Equal** | 1/4 + 1/4 + 1/4 + 1/4 | `fta-one-fourth` |
| **2-Column 1/3 + 2/3** | 1/3 + 2/3 | `fta-one-third` + `fta-two-thirds` |
| **2-Column 2/3 + 1/3** | 2/3 + 1/3 | `fta-two-thirds` + `fta-one-third` |
| **2-Column 1/4 + 3/4** | 1/4 + 3/4 | `fta-one-fourth` + `fta-three-fourths` |
| **2-Column 3/4 + 1/4** | 3/4 + 1/4 | `fta-three-fourths` + `fta-one-fourth` |
| **3-Column 1/4 + 2/4 + 1/4** | 1/4 + 2/4 + 1/4 | `fta-one-fourth` + `fta-two-fourths` + `fta-one-fourth` |

### Responsive Behavior

- **Desktop (> 768px)**: Fields display in configured columns
- **Mobile (≤ 768px)**: All fields stack vertically

## 🔧 Configuration

### SMTP Settings

Configure custom SMTP for email notifications:

1. Go to **Formtura → Settings → SMTP**
2. Enter your SMTP credentials
3. Test the connection
4. Save settings

### Form Settings

Each form has customizable settings:

- **Form Title** - Display title above form
- **Form Description** - Introductory text
- **Submit Button Text** - Customize button label
- **Success Message** - Message after submission
- **Redirect URL** - Redirect after submission
- **Email Notifications** - Configure recipients

## 📚 Documentation

Comprehensive documentation is available in the `/doc` directory:

- **[Quick Start Guide](doc/QUICKSTART.md)** - Get started quickly
- **[Layout Guide](doc/LAYOUT_QUICK_GUIDE.md)** - Grid layout reference
- **[Implementation Summary](doc/IMPLEMENTATION_SUMMARY.md)** - Technical details
- **[Bug Fixes](doc/)** - Changelog of fixes

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. **Fork the repository**
2. **Create a feature branch** (`git checkout -b feature/amazing-feature`)
3. **Commit your changes** (`git commit -m 'Add amazing feature'`)
4. **Push to the branch** (`git push origin feature/amazing-feature`)
5. **Open a Pull Request**

### Commit Message Format

Use [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add new field type
fix: resolve layout issue
docs: update README
style: format code
refactor: improve performance
test: add unit tests
```

## 🐛 Bug Reports

Found a bug? Please open an issue with:

- **Description** - Clear description of the issue
- **Steps to Reproduce** - How to recreate the bug
- **Expected Behavior** - What should happen
- **Actual Behavior** - What actually happens
- **Screenshots** - If applicable
- **Environment** - WordPress version, PHP version, etc.

## 📝 Changelog

### v1.0.5 (2025-11-15)
- ✅ Fixed grid layout isolation - only fields with grid classes are grouped
- ✅ Fixed layout selector - individual column selection now works
- ✅ Added 1/4 + 2/4 + 1/4 layout option
- ✅ Improved preview rendering accuracy

### v1.0.0 (2025-11-01)
- 🎉 Initial release
- ✨ Drag-and-drop form builder
- 📐 Grid layout system
- 📧 Email notifications
- 🔌 Block editor integration

## 📄 License

This project is licensed under the GPL-2.0 License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **WordPress** - For the amazing CMS platform
- **React** - For the powerful UI library
- **Vite** - For the blazing fast build tool
- **@dnd-kit** - For the drag-and-drop functionality
- **Lucide** - For the beautiful icons

## 💬 Support

Need help? Here's how to get support:

- **Documentation** - Check the `/doc` directory
- **Issues** - Open a GitHub issue
- **Discussions** - Start a discussion on GitHub
- **Email** - adrian200065@gmail.com

## 🌟 Show Your Support

If you find this plugin useful, please:

- ⭐ Star this repository
- 🐛 Report bugs
- 💡 Suggest features
- 🤝 Contribute code
- 📢 Share with others

---

**Made with ❤️ by [adrian200065](https://github.com/adrian200065)**

**[Website](#) • [Documentation](doc/) • [Issues](https://github.com/yourusername/formtura/issues) • [Changelog](#changelog)**
