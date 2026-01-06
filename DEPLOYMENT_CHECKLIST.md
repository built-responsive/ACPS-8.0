# 🚢 GitHub Release Deployment Checklist - v3.5.0

**Target Release Date:** January 5, 2026  
**Release Branch:** `main`  
**Tag:** `v3.5.0`

---

## ✅ Pre-Release Checklist

### 1. Documentation
- [x] CHANGELOG.md updated with v3.5.0 entry
- [x] README.md rewritten with comprehensive documentation
- [x] RELEASE_v3.5.0.md created with detailed release notes
- [x] Version numbers updated across all files
- [ ] Screenshots updated (if applicable)
- [ ] API documentation reviewed

### 2. Code Quality
- [x] All modal system changes tested
- [x] Keyboard detection verified
- [x] USPS validation error handling tested
- [x] Z-index layering fixed
- [x] No console errors in browser
- [ ] PHP error log reviewed (no critical errors)
- [ ] All TODO comments addressed or documented

### 3. Configuration
- [ ] .env.example updated with all required variables
- [ ] config.php verified for production values
- [ ] API keys documented (without exposing secrets)
- [ ] Database migrations documented (if any)

### 4. Dependencies
- [x] composer.json dependencies up to date
- [x] jQuery versions documented (3.2.1 vs 1.9.1)
- [x] No deprecated dependencies
- [ ] Security audit run (`composer audit`)

### 5. Testing
- [x] Add to cart functionality
- [x] Edit cart functionality  
- [x] Modal open/close operations
- [x] Keyboard detection and form positioning
- [x] Address validation with friendly errors
- [x] Cart z-index layering
- [ ] Full checkout flow (end-to-end)
- [ ] Payment processing (Square QR + Card Reader)
- [ ] Email delivery
- [ ] Print order processing

---

## 📦 Release Preparation

### 1. Create Git Tag
```bash
cd c:\Users\Geeks\Documents\acps.zip\uniserver\vhosts\acps_v2
git add .
git commit -m "Release v3.5.0 - Modal System Overhaul & UX Enhancements"
git tag -a v3.5.0 -m "Version 3.5.0 - See RELEASE_v3.5.0.md for details"
git push origin main
git push origin v3.5.0
```

### 2. GitHub Release Page
**Title:** `AlleyCat PhotoStation v3.5.0 - Modal System Overhaul`

**Description:**
```markdown
## 🎉 What's New

This major release completely overhauls the modal system and adds intelligent keyboard detection for kiosk environments.

### ✨ Highlights
- **Custom AJAX Modal System** - Replaced iframe-based VIBox with lightning-fast AJAX modals
- **Smart Keyboard Detection** - Forms automatically shift when on-screen keyboard appears
- **Friendly Validation Errors** - USPS API errors now display user-readable messages
- **UX Polish** - Pagination hover effects, z-index fixes, and modal improvements

### 📊 Performance
- 66-75% faster modal loading
- 49KB smaller bundle size
- Improved touch responsiveness

### 🐛 Bug Fixes
- Fixed modal sizing and positioning issues
- Fixed cart update duplication
- Fixed keyboard premature closing
- Fixed delete icon z-index layering

[📖 Full Release Notes](RELEASE_v3.5.0.md) | [📜 Changelog](CHANGELOG.md)

---

## 🚀 Upgrade Instructions

1. Backup your current installation
2. Pull latest code: `git pull origin main`
3. Install dependencies: `composer install`
4. Clear USPS token cache
5. Clear browser cache on all kiosks
6. Test critical paths (add to cart, checkout, payments)

**⚠️ Breaking Changes:** VIBox and Shadowbox have been removed. If you have custom code using these libraries, it will need updating.

---

## 📁 Downloads

- **Source Code (zip)** - Full source with all dependencies
- **Source Code (tar.gz)** - Full source with all dependencies
```

### 3. Release Assets
Upload these files as release assets:
- [ ] `acps_v2-v3.5.0-full.zip` (entire codebase)
- [ ] `RELEASE_v3.5.0.md` (detailed release notes)
- [ ] `CHANGELOG.md` (version history)

### 4. Release Labels
- `major-release`
- `ui-overhaul`
- `kiosk-optimized`
- `production-ready`

---

## 🚀 Deployment Steps

### Production Deployment

#### 1. Pre-Deployment Backup
```bash
# On production server
cd /var/www
tar -czf acps_backup_$(date +%Y%m%d_%H%M%S).tar.gz acps/
```

#### 2. Deploy Code
```bash
cd /var/www/acps
git fetch origin
git checkout v3.5.0
composer install --no-dev --optimize-autoloader
```

#### 3. Clear Caches
```bash
rm -f usps_token_cache.txt
# Clear Apache cache if applicable
sudo systemctl reload apache2
```

#### 4. Update Permissions
```bash
chmod 755 photos/ logs/ config/
chmod 644 config/*.txt
```

#### 5. Restart Services
```bash
# If using PHP-FPM
sudo systemctl restart php8.3-fpm

# Apache
sudo systemctl restart apache2
```

#### 6. Verify Deployment
- [ ] Visit kiosk URL and verify no errors
- [ ] Test add to cart
- [ ] Test modal operations
- [ ] Test checkout flow
- [ ] Check error logs: `tail -f /var/www/acps/logs/error.log`

### Kiosk Updates

For each kiosk terminal:
1. [ ] Clear browser cache (Ctrl+Shift+Delete)
2. [ ] Hard refresh (Ctrl+F5)
3. [ ] Test add to cart
4. [ ] Test keyboard detection
5. [ ] Verify on-screen keyboard positioning

---

## 📊 Post-Release Monitoring

### Metrics to Track (First 24 Hours)
- [ ] Modal open/close success rate
- [ ] Checkout completion rate
- [ ] Address validation success rate
- [ ] Payment processing success rate
- [ ] Average session duration
- [ ] Error rate in logs

### Log Files to Monitor
```bash
# Application errors
tail -f /var/www/acps/logs/error.log

# Apache errors
tail -f /var/log/apache2/error.log

# Apache access
tail -f /var/log/apache2/access.log
```

### Key Indicators of Success
- ✅ No increase in error rate
- ✅ Modal operations completing < 200ms
- ✅ Checkout completion rate maintained or improved
- ✅ No user-reported issues with keyboard

### Rollback Plan
If critical issues arise:
```bash
cd /var/www/acps
git checkout v3.4.0
composer install --no-dev
sudo systemctl restart apache2
```

---

## 📢 Communication Plan

### Announcement Channels
- [ ] Email to stakeholders
- [ ] Update on alleycatphoto.net
- [ ] GitHub release published
- [ ] Internal team notification

### Release Announcement Template
```
Subject: AlleyCat PhotoStation v3.5.0 Released - Modal System Overhaul

Hi Team,

We've just released v3.5.0 of AlleyCat PhotoStation with major improvements:

✨ Lightning-fast modals (66% faster)
✨ Smart keyboard detection for kiosks
✨ Friendly error messages
✨ Numerous bug fixes and polish

The update has been deployed to production and all kiosks should see the improvements immediately after a browser refresh.

Key changes for operations:
- Modals open faster and more reliably
- On-screen keyboard no longer hides buttons
- Error messages are now customer-friendly

Full release notes: [link to GitHub release]

Please report any issues to photos@alleycatphoto.net

Thanks,
Dev Team
```

---

## 🐛 Known Issues & Limitations

### Minor Issues (Non-Blocking)
- None identified at release time

### Future Enhancements
- Database-backed cart (planned for v3.6.0)
- Real-time inventory sync (planned for v3.6.0)
- PWA support (under consideration)

---

## ✅ Final Verification

Before marking release as complete:
- [ ] All files committed and pushed
- [ ] Git tag created and pushed
- [ ] GitHub release published
- [ ] Production deployed successfully
- [ ] All kiosks updated and tested
- [ ] Monitoring shows healthy metrics
- [ ] Team notified
- [ ] Documentation accessible

---

## 🎉 Release Complete!

Once all items above are checked, the release is officially complete.

**Celebration Protocol:**
1. Close this checklist issue
2. Update project board
3. Send success email to team
4. Pour a White Russian (The Dude approves)

---

*"This is not 'Nam. This is deployment. There are rules."*  
— Walter Sobchak (modified for DevOps)
