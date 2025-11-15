# Grid Layout Quick Guide

**Quick reference for creating multi-column form layouts**

---

## Quick Start: 2-Column Layout

1. Add **2 fields** to your form
2. Select the **first field** → **Advanced** tab
3. Click **"Show Layouts"**
4. Click the **2-column layout** (two equal boxes)
5. Select the **second field** → **Advanced** tab
6. In **CSS Classes**, remove `fta-first` (keep only `fta-one-half`)
7. Click **Preview** → See fields side-by-side! ✅

---

## Quick Start: 3-Column Layout

1. Add **3 fields** to your form
2. **First field:** Select layout → keeps `fta-one-third fta-first`
3. **Second field:** CSS Classes = `fta-one-third` (remove `fta-first`)
4. **Third field:** CSS Classes = `fta-one-third` (remove `fta-first`)
5. Click **Preview** → See 3 columns! ✅

---

## Quick Start: 1/4 + 2/4 + 1/4 Layout (NEW!)

1. Add **3 fields** to your form
2. **First field:** Select the **1/4 + 2/4 + 1/4 layout** → keeps `fta-one-fourth fta-first`
3. **Second field:** CSS Classes = `fta-two-fourths`
4. **Third field:** CSS Classes = `fta-one-fourth`
5. Click **Preview** → See narrow-wide-narrow layout! ✅

---

## All Available Layouts

### Equal Width Layouts

| Layout | Field 1 | Field 2 | Field 3 | Field 4 |
|--------|---------|---------|---------|---------|
| **2 Columns** | `fta-one-half fta-first` | `fta-one-half` | - | - |
| **3 Columns** | `fta-one-third fta-first` | `fta-one-third` | `fta-one-third` | - |
| **4 Columns** | `fta-one-fourth fta-first` | `fta-one-fourth` | `fta-one-fourth` | `fta-one-fourth` |

### Asymmetric 2-Column Layouts

| Layout | Field 1 | Field 2 |
|--------|---------|---------|
| **1/3 + 2/3** | `fta-one-third fta-first` | `fta-two-thirds` |
| **2/3 + 1/3** | `fta-two-thirds fta-first` | `fta-one-third` |
| **1/4 + 3/4** | `fta-one-fourth fta-first` | `fta-three-fourths` |
| **3/4 + 1/4** | `fta-three-fourths fta-first` | `fta-one-fourth` |

### Asymmetric 3-Column Layout

| Layout | Field 1 | Field 2 | Field 3 |
|--------|---------|---------|---------|
| **1/4 + 2/4 + 1/4** | `fta-one-fourth fta-first` | `fta-two-fourths` | `fta-one-fourth` |

---

## Multiple Rows

To create multiple rows with different layouts:

```
Row 1 (2 columns):
  Field 1: fta-one-half fta-first
  Field 2: fta-one-half

Row 2 (3 columns):
  Field 3: fta-one-third fta-first  ← Note: fta-first starts new row
  Field 4: fta-one-third
  Field 5: fta-one-third

Row 3 (1 column):
  Field 6: (no classes - full width)
```

---

## Important Rules

### ✅ DO:
- Use `fta-first` on the **first field of each row**
- Remove `fta-first` from all other fields in the row
- Make sure row widths add up to 100% (e.g., 1/2 + 1/2 = 100%)
- Test in Preview before saving

### ❌ DON'T:
- Don't use `fta-first` on every field (creates single-column layout)
- Don't mix incompatible widths (e.g., 1/2 + 1/3 won't fit in one row)
- Don't forget to remove `fta-first` from subsequent fields

---

## Common Patterns

### Contact Form (Name + Email in one row)
```
First Name: fta-one-half fta-first
Last Name:  fta-one-half
Email:      (no classes - full width)
Message:    (no classes - full width)
```

### Registration Form (3 columns for address)
```
Full Name:  (no classes - full width)
Email:      (no classes - full width)
City:       fta-one-third fta-first
State:      fta-one-third
ZIP:        fta-one-third
```

### Survey Form (Emphasized middle field)
```
Question 1: (no classes - full width)
Rating:     fta-one-fourth fta-first
Comments:   fta-two-fourths
Date:       fta-one-fourth
```

---

## Troubleshooting

### Fields are stacking instead of side-by-side
- ✅ Check that only the first field has `fta-first`
- ✅ Verify all fields have width classes (e.g., `fta-one-half`)
- ✅ Make sure widths add up to 100%

### Preview shows columns but frontend doesn't
- ✅ Clear browser cache
- ✅ Make sure you saved the form
- ✅ Check that frontend.css is loaded

### Layout breaks on mobile
- ✅ This is expected! All fields stack on mobile (< 768px)
- ✅ Test on desktop or tablet to see column layout

---

## CSS Class Reference

| Class | Width | Use Case |
|-------|-------|----------|
| `fta-one-half` | 50% | 2-column equal layout |
| `fta-one-third` | 33.33% | 3-column equal layout |
| `fta-two-thirds` | 66.66% | Wide field in 2-column |
| `fta-one-fourth` | 25% | 4-column or narrow field |
| `fta-two-fourths` | 50% | Same as one-half |
| `fta-three-fourths` | 75% | Very wide field in 2-column |
| `fta-first` | - | Marks start of new row |

---

## Tips & Tricks

### Tip 1: Copy-Paste Classes
Instead of typing, copy the CSS class from one field and paste it to another.

### Tip 2: Use Preview Often
Click Preview after each change to see how it looks.

### Tip 3: Start Simple
Begin with 2-column layouts, then progress to more complex grids.

### Tip 4: Mobile-First Thinking
Remember that all layouts stack on mobile, so field order matters!

### Tip 5: Consistent Spacing
The system automatically handles spacing between columns - no need to add margins.

---

## Video Tutorial (Coming Soon)

Watch a video walkthrough of creating multi-column layouts:
- 2-column contact form
- 3-column address fields
- Mixed layout with multiple rows
- Responsive behavior demonstration

---

## Need Help?

- Check the full documentation: `CSS_LAYOUT_IMPLEMENTATION.md`
- Review example forms in the plugin
- Contact support for advanced layout assistance

---

**Happy Form Building!** 🎉
