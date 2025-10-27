# Quick Start Guide - Custom Product Image Upload Lite

## For Developers

### Activating Lite Version

To activate the lite version instead of pro:

1. **Deactivate Pro** (if active):
   - Go to WordPress Admin > Plugins
   - Deactivate "Custom Product Image Upload"

2. **Activate Lite**:
   - Activate "Custom Product Image Upload Lite"

The main difference is the file loaded:
- **Pro**: `custom-product-image-upload.php` (CPIU_IS_LITE undefined)
- **Lite**: `custom-product-image-upload-lite.php` (CPIU_IS_LITE = true)

### Key Code Locations

#### Version Detection
```php
// Check if lite version
if (defined('CPIU_IS_LITE') && CPIU_IS_LITE === true) {
    // Lite-specific code
}

// Or use helper function
if (cpiu_is_feature_enabled('multi_product')) {
    // Feature is available
}
```

#### Data Manager
- **File**: `includes/class-cpiu-data-manager.php`
- **Key Methods**:
  - `is_lite_version()` - Returns true if lite
  - `can_add_product()` - Returns false if lite and 1 product exists
  - `save_product_configuration()` - Blocks 2nd product in lite

#### Admin Interface  
- **File**: `includes/class-cpiu-admin-interface.php`
- **Key Methods**:
  - `render_upgrade_prompt($feature)` - Shows upgrade banner
  - Localized JS includes `is_lite`, `can_add_product` flags

---

## For End Users

### Installing Lite Version

1. **Download** the plugin from GITHUB or from Wordpress Repository
2. **Upload** to WordPress:
   - Plugins > Add New > Upload Plugin
   - Choose the .zip file
3. **Activate** the plugin
4. **Ensure WooCommerce** is installed and active

### Configuring Your Product

1. Go to **WooCommerce > Image Upload Lite**
2. Click **Add Configuration** tab
3. Search and select your product
4. Configure settings:
   - Required images count
   - Max file size
   - Button text & color
   - Allowed file types
5. Click **Save Configuration**

### Testing Upload

1. Visit your configured product page
2. Click the upload button
3. Select images
4. Crop if desired
5. Click "Add to Cart"
6. Images appear in cart

### Upgrading to Pro

When you're ready for unlimited products:

1. **Purchase Pro** from our website or wpbay.com
2. **Download** Pro version
3. **Deactivate Lite** (Settings preserved!)
4. **Upload & Activate Pro**
5. All your settings work immediately!

**What's Preserved**:
✅ Your product configuration
✅ All uploaded customer images
✅ All settings and preferences
✅ Custom colors and text

---

## Feature Comparison

| Feature | Lite | Pro |
|---------|------|-----|
| **Product Configurations** | Unlimited | Unlimited |
| **Bulk Configuration** | ✗ | ✓ |
| **Image Upload** | ✓ | ✓ |
| **Image Cropping** | ✓ | ✓ |
| **Drag & Drop** | ✓ | ✓ |
| **Modal Interface** | ✓ | ✓ |
| **Guest Upload** | ✓ | ✓ |
| **Custom Colors** | ✓ | ✓ |
| **File Type Control** | ✓ | ✓ |
| **File Size Limits** | ✓ | ✓ |
| **Resolution Validation** | ✓ | ✓ |
| **Import/Export** | ✗ | ✓ |
| **Security Logs** | ✗ | ✓ |
| **CDN Caching** | ✗ | ✓ |
| **Priority Support** | ✗ | ✓ |

---

## Troubleshooting

### "Can't add multiple products at once"
**This is expected!** Lite version supports only single product configuration. 

**Solution**: Upgrade to Pro for unlimited products.

### "Bulk operations disabled"
**This is expected!** Bulk operations are a Pro feature.

**Solution**: Upgrade to Pro or configure products individually.

### Upgrade prompts not showing
1. Check if `CPIU_IS_LITE` is defined in the main plugin file
2. Clear browser cache
3. Deactivate and reactivate plugin

### Settings not saving
1. Check WooCommerce is active
2. Check file permissions
3. Check for JavaScript errors in browser console
4. Verify nonce is not expired (reload admin page)

---

## Support

### Lite Version
- Email: hello@nowdigiverse.com
- Response time: 48-72 hours
- Community forum support

### Pro Version
- Priority email support
- Response time: 12-24 hours
- Live chat (business hours)
- Phone support available

---

## FAQs

### Q: Will I lose data when upgrading?
**A:** No! All data is preserved. Upgrade is seamless.

### Q: Can I downgrade from Pro to Lite?
**A:** Yes, but you'll be limited to single product configuration at once.

### Q: Do customers need accounts to upload?
**A:** No! Guest upload is supported in both versions.

### Q: What file types are supported?
**A:** JPG, JPEG, PNG, GIF, and WebP by default.

### Q: Is there a file size limit?
**A:** Yes, configurable per product (default 5MB).

### Q: Can I customize the upload button?
**A:** Yes! Both text and color are customizable.

### Q: Does it work on mobile?
**A:** Yes! Fully responsive on all devices.

### Q: Is it secure?
**A:** Yes! Includes rate limiting, file validation, and security scanning.

---

## Resources

- **Website**: https://nowdigiverse.com
- **Upgrade**: https://wpbay.com/product/custom-image-upload-addon-for-woocommerce/

---

## Version History

### 1.1.0 (Current)
- Initial Lite version release
- Single product configuration
- Full upload functionality
- Guest upload support
- Image cropping

---

**Happy uploading!** 🎨📸
