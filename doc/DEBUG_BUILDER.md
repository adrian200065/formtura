# Form Builder Debugging Guide

## Issue: Blank page when accessing form builder

### Steps to Debug:

1. **Check if you're on the right page:**
   - URL should be: `wp-admin/admin.php?page=formtura-builder` or `wp-admin/admin.php?page=formtura-builder&form_id=X`

2. **Open Browser Console (F12):**
   - Look for JavaScript errors
   - Check if `window.formturaBuilder` object exists
   - Check if React is loading

3. **Check if assets are loaded:**
   - Open Network tab in browser dev tools
   - Look for `builder.js` and `builder.css`
   - Check if they return 200 status

4. **Check if React root element exists:**
   - In Console, type: `document.getElementById('formtura-builder-root')`
   - Should return an element, not null

5. **Common Issues:**
   - **404 on assets**: Assets not built or wrong path
   - **Blank #formtura-builder-root**: React not mounting
   - **JavaScript errors**: Syntax error in React code
   - **No errors but blank**: CSS hiding content or wrong page hook

### Quick Fixes:

**Rebuild assets:**
```bash
cd /mnt/Sites/wpsun/wp-content/plugins/formtura
npm run build
```

**Clear WordPress cache:**
- If using caching plugin, clear cache
- Hard refresh browser (Ctrl+Shift+R or Cmd+Shift+R)

**Check file permissions:**
```bash
ls -la assets/js/builder.js
ls -la assets/css/builder.css
```

### Direct Access Test:

Try accessing directly:
- `http://yoursite.com/wp-admin/admin.php?page=formtura-builder`

If you see the WordPress admin but blank content area, the page is loading but React isn't.

### Browser Console Commands:

```javascript
// Check if config exists
console.log(window.formturaBuilder);

// Check if React root exists
console.log(document.getElementById('formtura-builder-root'));

// Check if React loaded
console.log(window.React);
```
