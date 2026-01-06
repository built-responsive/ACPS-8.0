# AlleyCat PhotoStation - Modal System Upgrade

**Date:** January 5, 2026  
**Version:** 3.4.0  
**System:** VIBox Modal Integration

---

## 🎯 Overview

Complete replacement of outdated Shadowbox and native dialog modals with modern VIBox modal system.

## ✅ Changes Implemented

### 1. **jQuery Upgrade**
- **From:** jQuery 1.11.1 (2014)
- **To:** jQuery 3.2.1 (2017)
- **File:** `/public/assets/js/jquery-3.2.1.min.js`
- **Reason:** VIBox requires jQuery 1.9.1+ and the old version had security vulnerabilities

### 2. **New Modal System Files**

#### JavaScript
- **`/public/assets/js/acps_modal.js`** (NEW)
  - Central modal management system
  - Functions: `openCartModal()`, `closeCartModal()`, `openCheckoutModal()`, `closeCheckoutModal()`
  - Maintains `editCart()` for backward compatibility
  - Auto-reloads cart sidebar on modal close
  - Comprehensive logging for debugging

#### CSS
- **`/public/assets/css/acps.css`** (UPDATED)
  - Added complete VIBox styling section
  - Custom close button (red circle, 40px, top-right)
  - Backdrop blur effect
  - Border-radius on iframe (15px) for clean corners
  - Fade-in animation
  - Mobile-responsive

### 3. **Updated Files**

#### gallery.php
**Removed:**
- Shadowbox initialization and references
- Old native `<dialog>` element
- jQuery 1.11.1 reference

**Added:**
- jQuery 3.2.1
- VIBox.js and acps_modal.js
- Click handler on large images to open cart modal
- Clean, commented JavaScript functions
- `loadLarge()` function updated with modal integration

#### cart_add.php
**Changed:**
- Cancel button: `closeNativeDialog()` → `closeCartModal()`
- Form submission: `Shadowbox.close()` → `closeCartModal()`
- Auto-closes modal after adding item to cart (400ms delay)

#### cart.php
**Added:**
- jQuery 3.2.1, vibox.js, acps_modal.js scripts
- Poppins font throughout

**Changed:**
- Checkout button: Direct link → `openCheckoutModal()` call
- Opens pay.php in modal instead of full page navigation

### 4. **Modal Configurations**

```javascript
Cart Modal:
- Width: 1100px
- Height: 750px
- Border: 1px red, 15px radius
- Responsive: Yes (max 95vw × 90vh)
- Background: Black with red accent

Checkout Modal:
- Width: 800px
- Height: 900px
- Border: 1px red, 15px radius
- Responsive: Yes
- Background: Black with red accent
```

---

## 🔧 How It Works

### Cart Item Workflow
1. User clicks thumbnail → Loads large image
2. User clicks large image OR "Add to Cart" button
3. `openCartModal(url)` called with cart_add.php URL
4. VIBox creates overlay + iframe
5. User adds item, submits form
6. Modal auto-closes after 400ms
7. Cart sidebar auto-reloads to show new items

### Checkout Workflow
1. User clicks "Checkout" in cart sidebar
2. `openCheckoutModal(amount)` called
3. Opens pay.php in modal with total amount
4. User completes payment
5. Modal closes, cart refreshes

### Edit Cart Item Workflow
1. User clicks thumbnail in cart sidebar
2. `editCart(url)` called (legacy compatibility)
3. Routes to `openCartModal(url)`
4. Same workflow as adding new item

---

## 🎨 Features

### ✅ Clean Visual Design
- No more scrollbars (perfect iframe sizing)
- Rounded corners on all four sides
- Smooth fade-in animations
- Backdrop blur effect
- Red circle close button (top-right)

### ✅ Responsive
- Adapts to screen size
- Max-width/height constraints
- Touch-friendly on tablets

### ✅ Developer-Friendly
- All JS in central files
- Comprehensive comments
- Console logging for debugging
- Easy to modify dimensions

### ✅ User Experience
- Click anywhere on backdrop to close
- Click large image to add to cart
- Auto-refresh cart after changes
- Smooth transitions

---

## 📝 Function Reference

### Global Functions (window scope)

```javascript
openCartModal(url)
// Opens cart add/edit modal
// @param url - Path to cart_add.php with photo parameter
// Example: openCartModal('cart_add.php?p=photos/2026/01/05/numbered/10001.jpg')

closeCartModal()
// Closes the currently open cart modal
// Auto-called after form submission

openCheckoutModal(amount)
// Opens checkout modal (pay.php)
// @param amount - Total dollar amount
// Example: openCheckoutModal(45.50)

closeCheckoutModal()
// Closes the currently open checkout modal

editCart(url)
// Legacy function, routes to openCartModal()
// Maintains compatibility with existing cart.php onclick handlers
```

### jQuery Event Handlers

```javascript
$(document).on('click', '.gallery-image-clickable', function(e) {...})
// Handles clicks on large gallery images
// Opens cart modal with data-cart-url attribute
```

---

## 🗑️ Removed

### Files No Longer Used
- `/public/assets/shadowbox/` (entire folder)
- `/public/assets/css/vibox.css` (moved to acps.css)

### Code Removed
- All Shadowbox initialization (`Shadowbox.init`, `Shadowbox.open`, `Shadowbox.close`)
- Native `<dialog>` element
- `openNativeDialog()` function
- `closeNativeDialog()` function
- jQuery 1.11.1 references

---

## 🧪 Testing Checklist

- [x] Gallery loads with jQuery 3.2.1
- [x] Thumbnail click loads large image
- [x] Large image clickable (opens cart modal)
- [x] "Add to Cart" button opens modal
- [x] Cart modal displays correctly (1100×750, rounded corners)
- [x] No scrollbars on modal
- [x] Close button works (red circle, top-right)
- [x] Backdrop click closes modal
- [x] Form submission closes modal
- [x] Cart sidebar reloads after modal close
- [x] Cart thumbnail click reopens modal
- [x] Checkout button opens pay.php in modal
- [x] No console errors
- [x] Mobile responsive

---

## 🐛 Troubleshooting

### Modal doesn't open
- Check console for errors
- Verify vibox.js and acps_modal.js are loading
- Confirm jQuery 3.2.1 is loaded (not 1.11.1)

### Scrollbars appear
- Check iframe dimensions in CSS
- Verify `box-sizing: border-box` on .vibox-content
- Ensure modal dimensions match config

### Close button missing
- Check .vibox-close CSS in acps.css
- Verify z-index: 10
- Confirm position: absolute with top/right values

### Cart doesn't refresh
- Check `onClosed` callback in acps_modal.js
- Verify `window.parent.frames['cart']` exists
- Test cart.php loading independently

---

## 📚 References

- **VIBox Documentation:** https://github.com/
vinaydxt/vibox
- **jQuery 3.2.1 Docs:** https://api.jquery.com/
- **CSS Backdrop Filter:** https://developer.mozilla.org/en-US/docs/Web/CSS/backdrop-filter

---

## 🚀 Future Enhancements

1. Add loading spinner during iframe load
2. Implement modal size presets (small, medium, large)
3. Add keyboard shortcuts (Esc to close)
4. Animate modal entrance (scale + fade)
5. Add sound effects on open/close (optional)

---

**Maintained by:** AlleyCat Photo Development Team  
**Last Updated:** January 5, 2026
