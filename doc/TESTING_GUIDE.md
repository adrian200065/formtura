# Form Builder Testing Guide

Quick guide to verify all fixes are working correctly.

---

## Test 1: Field Persistence (Primary Fix)

### Steps:
1. Navigate to **Formtura > Add New** in WordPress admin
2. Drag a **Text Field** from the library to the canvas
3. Click on the field and edit its label to "Full Name"
4. Drag an **Email Field** to the canvas
5. Edit email field label to "Email Address"
6. Click **Save** button
7. Wait for "Form saved successfully!" message
8. Note: Page will redirect with new form ID in URL
9. **Refresh the page** (F5 or Ctrl+R)

### Expected Result:
✅ Both fields (Full Name and Email Address) are still visible in the canvas
✅ Field labels are preserved
✅ Field order is maintained

### If This Fails:
- Check browser console for JavaScript errors
- Check PHP error logs for backend errors
- Verify database table `wp_fta_forms` exists
- Verify user has `manage_options` capability

---

## Test 2: Multiple Fields Addition

### Steps:
1. Open an existing form or create a new one
2. Add the following fields one by one:
   - Text Field
   - Email Field
   - Textarea
   - Number Field
   - Dropdown
3. Verify all 5 fields appear in the canvas
4. Click **Save**
5. Refresh the page

### Expected Result:
✅ All 5 fields can be added without restriction
✅ All fields appear in the canvas simultaneously
✅ All fields persist after save and reload

---

## Test 3: Preview Functionality

### Steps:
1. Open a form with at least 3 fields
2. Add form title: "Contact Us"
3. Add form description: "Please fill out this form"
4. Change submit button text to "Send Message"
5. Click the **Preview** button (top right)

### Expected Result:
✅ Modal overlay appears with dark background
✅ Form preview shows in a white modal box
✅ Form title "Contact Us" is displayed
✅ Form description is displayed
✅ All fields are rendered with correct types
✅ Submit button shows "Send Message"
✅ Clicking X or outside modal closes preview

---

## Test 4: Field Settings Persistence

### Steps:
1. Add a Text Field
2. Click on the field to select it
3. In the right panel, configure:
   - Label: "Company Name"
   - Placeholder: "Enter your company name"
   - Description: "Optional field"
   - Check "Required" checkbox
4. Click **Save**
5. Refresh the page
6. Click on the field again

### Expected Result:
✅ Label shows "Company Name"
✅ Placeholder text is preserved
✅ Description is preserved
✅ Required checkbox is still checked

---

## Test 5: Form Settings Persistence

### Steps:
1. Click on the **Form** tab in the right panel
2. Set:
   - Form Title: "Customer Feedback"
   - Form Description: "We value your feedback"
   - Submit Button Text: "Submit Feedback"
   - Success Message: "Thank you for your feedback!"
3. Click **Save**
4. Refresh the page
5. Click on the **Form** tab again

### Expected Result:
✅ Form title is preserved
✅ Form description is preserved
✅ Submit button text is preserved
✅ Success message is preserved

---

## Test 6: Field Operations

### Steps:
1. Add 3 fields to the canvas
2. **Reorder:** Drag the middle field to the top
3. **Duplicate:** Click the copy icon on the first field
4. **Delete:** Click the trash icon on the last field
5. Click **Save**
6. Refresh the page

### Expected Result:
✅ Field order is preserved after reordering
✅ Duplicated field appears with "(Copy)" suffix
✅ Deleted field is gone
✅ All changes persist after reload

---

## Test 7: Choice Field Options

### Steps:
1. Add a **Dropdown** field
2. Click on the field to select it
3. In the right panel, edit options:
   - Change "Option 1" to "Small"
   - Change "Option 2" to "Medium"
   - Change "Option 3" to "Large"
   - Add a new option "Extra Large"
4. Click **Save**
5. Refresh the page
6. Click **Preview**

### Expected Result:
✅ All 4 options are saved
✅ Options appear in preview dropdown
✅ Option labels are correct

---

## Test 8: New Form Creation Flow

### Steps:
1. Go to **Formtura > Add New**
2. Add 2 fields
3. Set form title to "Test Form"
4. Click **Save**
5. Wait for redirect

### Expected Result:
✅ "Form saved successfully!" message appears
✅ Page automatically redirects to edit URL with form ID
✅ URL changes from `page=formtura-builder` to `page=formtura-builder&form_id=X`
✅ Fields are still visible after redirect

---

## Test 9: Edit Existing Form

### Steps:
1. Go to **Formtura > All Forms**
2. Click "Edit" on an existing form
3. Add a new field
4. Modify an existing field
5. Click **Save**
6. Refresh the page

### Expected Result:
✅ Form loads with all existing fields
✅ New field is added successfully
✅ Modified field shows changes
✅ All changes persist after reload

---

## Test 10: Preview with Different Field Types

### Steps:
1. Create a form with these fields:
   - Text
   - Email
   - Textarea
   - Number
   - Dropdown
   - Radio Buttons
   - Checkboxes
2. Click **Preview**

### Expected Result:
✅ Text field renders as single-line input
✅ Email field renders with email type
✅ Textarea renders as multi-line input
✅ Number field renders with number type
✅ Dropdown renders with all options
✅ Radio buttons render as radio group
✅ Checkboxes render as checkbox group

---

## Quick Verification Checklist

Run through this checklist for a quick smoke test:

- [ ] Can add fields to canvas
- [ ] Can add multiple fields (5+)
- [ ] Can save form
- [ ] Fields persist after page reload
- [ ] Can click Preview button
- [ ] Preview modal opens
- [ ] Preview shows all fields
- [ ] Can close preview modal
- [ ] Can edit field settings
- [ ] Field settings persist
- [ ] Can edit form settings
- [ ] Form settings persist
- [ ] Can reorder fields
- [ ] Can duplicate fields
- [ ] Can delete fields

---

## Troubleshooting

### Fields Not Saving
1. Open browser console (F12)
2. Click Save
3. Look for AJAX errors
4. Check Network tab for failed requests
5. Verify response shows `success: true`

### Preview Not Opening
1. Check browser console for JavaScript errors
2. Verify `FormPreview` component loaded
3. Check if modal CSS is loaded
4. Try clearing browser cache

### Database Issues
Run this SQL query to verify tables exist:
```sql
SHOW TABLES LIKE 'wp_fta_%';
```

Should return:
- `wp_fta_forms`
- `wp_fta_entries`
- `wp_fta_entry_meta`

---

## Browser Console Debugging

### Check if React App Loaded
```javascript
console.log(window.formturaBuilder);
```

Should output:
```javascript
{
  ajaxUrl: "http://yoursite.com/wp-admin/admin-ajax.php",
  nonce: "abc123...",
  formId: "123",
  editUrl: "http://yoursite.com/wp-admin/admin.php?page=formtura-builder",
  formsUrl: "http://yoursite.com/wp-admin/admin.php?page=formtura"
}
```

### Monitor AJAX Requests
```javascript
// In browser console, before clicking Save
console.log('Monitoring AJAX...');
```

Then click Save and check Network tab for:
- Request URL: `admin-ajax.php`
- Action: `fta_save_form`
- Response: `{success: true, data: {...}}`

---

**All tests passing?** ✅ Form builder is working correctly!
