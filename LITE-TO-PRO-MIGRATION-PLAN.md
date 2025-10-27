# 🔄 Lite to Pro Migration Plan

## Problem Statement

When users upgrade from **Custom Product Image Upload Lite** to **Custom Product Image Upload Pro**, WordPress treats them as separate plugins. This creates several challenges:

### Current Issues:
1. ❌ Deactivating Lite may trigger data deletion (if uninstall preference set)
2. ❌ Plugin configurations stored in DB might not carry over
3. ❌ Uploaded images might become orphaned
4. ❌ Users need to reconfigure everything from scratch
5. ❌ Poor user experience during upgrade

---

## 🎯 Solution Strategy

### Approach: **Seamless Data Migration System**

Both Lite and Pro versions will share the same database option names, ensuring automatic data compatibility.

---

## 📋 Implementation Plan

### **Phase 1: Database Compatibility** ✅ (Already Implemented)

Both versions use identical database structure:

```php
// Shared Database Options
'cpiu_multi_product_configs'  // Product configurations
'cpiu_default_settings'        // Default settings
'cpiu_settings'                // Legacy settings
'cpiu_keep_data_on_uninstall' // Uninstall preference
```

**Status:** ✅ **Complete** - Both versions already use the same option names.

---

### **Phase 2: Pre-Migration Safety Checks**

#### **2.1 Add Export Reminder (Lite Version)**

**Location:** `custom-product-image-upload-lite.php`

Add a prominent notice when Lite is active:

```php
/**
 * Show upgrade migration notice
 */
function cpiu_show_upgrade_migration_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Only show if Lite is active
    if (!cpiu_is_lite_version()) {
        return;
    }
    
    // Don't show if dismissed
    if (get_option('cpiu_migration_notice_dismissed')) {
        return;
    }
    
    ?>
    <div class="notice notice-info is-dismissible" id="cpiu-migration-notice">
        <h3><?php esc_html_e('⬆️ Planning to Upgrade to Pro?', 'custom-product-image-upload-lite'); ?></h3>
        <p>
            <?php esc_html_e('Great news! Your settings and configurations will automatically transfer to the Pro version.', 'custom-product-image-upload-lite'); ?>
        </p>
        <p>
            <strong><?php esc_html_e('Migration Steps:', 'custom-product-image-upload-lite'); ?></strong>
        </p>
        <ol>
            <li><?php esc_html_e('Optional: Export your settings as backup (Import/Export tab)', 'custom-product-image-upload-lite'); ?></li>
            <li><?php esc_html_e('Install Custom Product Image Upload Pro', 'custom-product-image-upload-lite'); ?></li>
            <li><?php esc_html_e('Activate Pro version - your data will be preserved!', 'custom-product-image-upload-lite'); ?></li>
            <li><?php esc_html_e('Deactivate and delete Lite version', 'custom-product-image-upload-lite'); ?></li>
        </ol>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=custom-image-upload-addon&tab=import-export')); ?>" class="button">
                <?php esc_html_e('📦 Export Settings Now', 'custom-product-image-upload-lite'); ?>
            </a>
            <a href="https://wpbay.com/product/custom-image-upload-addon-for-woocommerce/" class="button button-primary" target="_blank">
                <?php esc_html_e('🚀 Upgrade to Pro', 'custom-product-image-upload-lite'); ?>
            </a>
            <button type="button" class="button" data-cpiu-action="dismiss-migration-notice">
                <?php esc_html_e('Dismiss', 'custom-product-image-upload-lite'); ?>
            </button>
        </p>
    </div>
    <?php
}
add_action('admin_notices', 'cpiu_show_upgrade_migration_notice');
```

---

### **Phase 3: Pro Version Auto-Migration on Activation**

#### **3.1 Automatic Lite Deactivation + Export on Pro Activation**

**Location:** `custom-product-image-upload.php` (Pro version)

```php
/**
 * Auto-migrate from Lite version on Pro activation
 */
function cpiu_auto_migrate_from_lite() {
    // Check if this is first activation of Pro
    $pro_activated_before = get_option('cpiu_pro_activated', false);
    
    if (!$pro_activated_before) {
        // First time activating Pro
        update_option('cpiu_pro_activated', true);
        
        // Check if Lite is currently active
        $lite_plugin_file = 'woocommerce-image-upload-addon-lite/custom-product-image-upload-lite.php';
        $is_lite_active = is_plugin_active($lite_plugin_file);
        
        // Get Lite data before deactivation
        $lite_configs = get_option('cpiu_multi_product_configs', array());
        $lite_settings = get_option('cpiu_default_settings', array());
        $lite_legacy = get_option('cpiu_settings', array());
        
        $has_lite_data = !empty($lite_configs) || !empty($lite_settings) || !empty($lite_legacy);
        
        if ($has_lite_data) {
            // STEP 1: Export Lite settings to JSON file
            $export_data = array(
                'plugin_info' => array(
                    'name' => 'Custom Product Image Upload Lite',
                    'version' => get_option('cpiu_lite_version', '1.1-lite'),
                    'export_date' => current_time('mysql'),
                    'export_reason' => 'Auto-backup during Pro activation',
                    'site_url' => get_site_url()
                ),
                'multi_product_configs' => $lite_configs,
                'default_settings' => $lite_settings,
                'legacy_settings' => $lite_legacy
            );
            
            // Save export to uploads directory
            $upload_dir = wp_upload_dir();
            $backup_dir = $upload_dir['basedir'] . '/cpiu-backups/';
            
            // Create backup directory if it doesn't exist
            if (!file_exists($backup_dir)) {
                wp_mkdir_p($backup_dir);
                // Add .htaccess to prevent direct access
                file_put_contents($backup_dir . '.htaccess', 'deny from all');
            }
            
            // Generate filename with timestamp
            $filename = 'cpiu-lite-backup-' . date('Y-m-d-H-i-s') . '.json';
            $file_path = $backup_dir . $filename;
            
            // Write JSON file
            file_put_contents($file_path, json_encode($export_data, JSON_PRETTY_PRINT));
            
            // Store backup info for notice
            set_transient('cpiu_migration_backup_file', array(
                'filename' => $filename,
                'file_path' => $file_path,
                'file_url' => $upload_dir['baseurl'] . '/cpiu-backups/' . $filename,
                'config_count' => count($lite_configs),
                'export_date' => current_time('mysql')
            ), 86400); // 24 hours
            
            // STEP 2: Deactivate Lite version if active
            if ($is_lite_active) {
                deactivate_plugins($lite_plugin_file);
                set_transient('cpiu_lite_auto_deactivated', true, 300);
            }
            
            // STEP 3: Set migration success flag
            set_transient('cpiu_migration_from_lite', true, 300); // 5 minutes
        }
    }
}
register_activation_hook(__FILE__, 'cpiu_auto_migrate_from_lite');
```

---

### **Phase 4: Post-Migration Success Notice**

#### **4.1 Show Auto-Migration Success with Backup Info (Pro Version)**

**Location:** `custom-product-image-upload.php` (Pro version)

```php
/**
 * Show auto-migration success notice
 */
function cpiu_show_migration_success_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (!get_transient('cpiu_migration_from_lite')) {
        return;
    }
    
    // Get migration info
    $backup_info = get_transient('cpiu_migration_backup_file');
    $lite_deactivated = get_transient('cpiu_lite_auto_deactivated');
    $config_count = isset($backup_info['config_count']) ? $backup_info['config_count'] : 0;
    
    ?>
    <div class="notice notice-success is-dismissible" style="border-left-color: #46b450;">
        <h2 style="margin-top: 10px;"><?php esc_html_e('🎉 Welcome to Custom Product Image Upload Pro!', 'custom-product-image-upload'); ?></h2>
        
        <div style="background: #f0f9ff; border-left: 4px solid #46b450; padding: 15px; margin: 15px 0;">
            <h3 style="margin-top: 0;"><?php esc_html_e('✅ Auto-Migration Completed Successfully!', 'custom-product-image-upload'); ?></h3>
            
            <p><strong><?php esc_html_e('What we did for you:', 'custom-product-image-upload'); ?></strong></p>
            <ul style="list-style: none; padding-left: 0;">
                <?php if ($lite_deactivated): ?>
                <li>✅ <strong><?php esc_html_e('Automatically deactivated Lite version', 'custom-product-image-upload'); ?></strong></li>
                <?php endif; ?>
                <li>✅ <strong><?php 
                    printf(
                        esc_html__('Preserved %d product configuration(s)', 'custom-product-image-upload'),
                        $config_count
                    ); 
                ?></strong></li>
                <?php if ($backup_info): ?>
                <li>✅ <strong><?php esc_html_e('Created automatic backup of your settings', 'custom-product-image-upload'); ?></strong></li>
                <?php endif; ?>
                <li>✅ <strong><?php esc_html_e('All your data is intact and ready to use', 'custom-product-image-upload'); ?></strong></li>
            </ul>
        </div>
        
        <?php if ($backup_info): ?>
        <div style="background: #fffbcc; border-left: 4px solid #ffb900; padding: 15px; margin: 15px 0;">
            <h4 style="margin-top: 0;"><?php esc_html_e('📦 Backup Created', 'custom-product-image-upload'); ?></h4>
            <p>
                <?php esc_html_e('For your safety, we automatically exported your Lite settings:', 'custom-product-image-upload'); ?>
            </p>
            <p>
                <strong><?php esc_html_e('Backup File:', 'custom-product-image-upload'); ?></strong> 
                <code><?php echo esc_html($backup_info['filename']); ?></code><br>
                <strong><?php esc_html_e('Location:', 'custom-product-image-upload'); ?></strong> 
                <code>wp-content/uploads/cpiu-backups/</code><br>
                <strong><?php esc_html_e('Created:', 'custom-product-image-upload'); ?></strong> 
                <?php echo esc_html($backup_info['export_date']); ?>
            </p>
            <p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cpiu_download_backup&file=' . urlencode($backup_info['filename'])), 'cpiu_download_backup')); ?>" class="button">
                    <?php esc_html_e('📥 Download Backup', 'custom-product-image-upload'); ?>
                </a>
                <span style="margin-left: 10px; color: #666;">
                    <?php esc_html_e('(Keep this for your records)', 'custom-product-image-upload'); ?>
                </span>
            </p>
        </div>
        <?php endif; ?>
        
        <div style="background: #f0fff4; border-left: 4px solid #10b981; padding: 15px; margin: 15px 0;">
            <h4 style="margin-top: 0;"><?php esc_html_e('🚀 You Can Now:', 'custom-product-image-upload'); ?></h4>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><?php esc_html_e('Configure unlimited products (no more 1 product limit!)', 'custom-product-image-upload'); ?></li>
                <li><?php esc_html_e('Use bulk operations to configure multiple products at once', 'custom-product-image-upload'); ?></li>
                <li><?php esc_html_e('Edit existing configurations anytime', 'custom-product-image-upload'); ?></li>
                <li><?php esc_html_e('Import/Export settings between sites', 'custom-product-image-upload'); ?></li>
                <li><?php esc_html_e('Access security logs and CDN cache features', 'custom-product-image-upload'); ?></li>
                <li><?php esc_html_e('Get priority support', 'custom-product-image-upload'); ?></li>
            </ul>
        </div>
        
        <p style="margin-top: 20px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=custom-image-upload-addon')); ?>" class="button button-primary button-hero">
                <?php esc_html_e('📋 View Your Configurations', 'custom-product-image-upload'); ?>
            </a>
            <?php if ($lite_deactivated): ?>
            <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="button button-secondary" style="margin-left: 10px;">
                <?php esc_html_e('🗑️ Delete Lite Version (Recommended)', 'custom-product-image-upload'); ?>
            </a>
            <?php endif; ?>
        </p>
        
        <p style="color: #666; font-size: 12px; margin-top: 15px;">
            <?php esc_html_e('ℹ️ Your Lite version has been automatically deactivated but not deleted. You can safely delete it from the Plugins page.', 'custom-product-image-upload'); ?>
        </p>
    </div>
    <?php
    
    // Clear migration transient after showing
    delete_transient('cpiu_migration_from_lite');
    delete_transient('cpiu_lite_auto_deactivated');
    // Keep backup info for download
}
add_action('admin_notices', 'cpiu_show_migration_success_notice');

/**
 * Handle backup file download
 */
function cpiu_handle_backup_download() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    check_admin_referer('cpiu_download_backup');
    
    $filename = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
    
    if (empty($filename)) {
        wp_die('Invalid file');
    }
    
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . '/cpiu-backups/' . $filename;
    
    if (!file_exists($file_path)) {
        wp_die('Backup file not found');
    }
    
    // Force download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
}
add_action('admin_post_cpiu_download_backup', 'cpiu_handle_backup_download');
```

---

### **Phase 5: Safe Uninstall Protection**

#### **5.1 Prevent Data Loss During Uninstall (Lite)**

**Location:** `uninstall.php` (Lite version)

```php
// SECURITY: Prevent uninstall if Pro is installed
$pro_plugin_file = WP_PLUGIN_DIR . '/woocommerce-image-upload-addon/custom-product-image-upload.php';

if (file_exists($pro_plugin_file)) {
    // Pro version exists - preserve data for migration
    error_log('CPIU Lite: Pro version detected - preserving data for migration');
    exit('Data preserved for Pro version migration');
}

// Continue with normal uninstall only if Pro is NOT installed
```

**Location:** `custom-product-image-upload-lite.php`

Add notice when trying to delete Lite while data exists:

```php
/**
 * Warning before deleting Lite plugin
 */
function cpiu_lite_deletion_warning() {
    global $pagenow;
    
    if ($pagenow !== 'plugins.php') {
        return;
    }
    
    // Check if Pro is active
    if (is_plugin_active('woocommerce-image-upload-addon/custom-product-image-upload.php')) {
        // Pro is active - safe to delete Lite
        return;
    }
    
    // Check if Lite has data
    $configs = get_option('cpiu_multi_product_configs', array());
    
    if (!empty($configs)) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Intercept delete link for Lite plugin
            $('tr[data-plugin*="custom-product-image-upload-lite"]').on('click', '.delete a', function(e) {
                var proisActive = <?php echo is_plugin_active('woocommerce-image-upload-addon/custom-product-image-upload.php') ? 'true' : 'false'; ?>;
                
                if (!proisActive) {
                    var confirmed = confirm(
                        '⚠️ WARNING: You have <?php echo count($configs); ?> product configuration(s)!\n\n' +
                        'Deleting this plugin will erase all your settings.\n\n' +
                        'Recommended:\n' +
                        '1. Export your settings first (Import/Export tab)\n' +
                        '2. Install and activate Pro version\n' +
                        '3. Then delete Lite version\n\n' +
                        'Are you sure you want to delete WITHOUT upgrading?'
                    );
                    
                    if (!confirmed) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        });
        </script>
        <?php
    }
}
add_action('admin_footer', 'cpiu_lite_deletion_warning');
```

---

## 📊 Migration Flow Diagram

```
┌─────────────────────────────────────────┐
│  LITE VERSION ACTIVE                    │
│  • User has configurations              │
│  • Sees upgrade migration notice        │
└─────────────┬───────────────────────────┘
              │
              │ User clicks "Upgrade to Pro"
              ▼
┌─────────────────────────────────────────┐
│  PURCHASE & DOWNLOAD PRO                │
│  • Download Pro plugin ZIP              │
│  • Upload to WordPress                  │
└─────────────┬───────────────────────────┘
              │
              │ Upload Pro plugin
              ▼
┌─────────────────────────────────────────┐
│  INSTALL PRO                            │
│  • Pro plugin files installed           │
│  • Lite still active                    │
└─────────────┬───────────────────────────┘
              │
              │ Click "Activate" on Pro
              ▼
┌─────────────────────────────────────────┐
│  🤖 AUTO-MIGRATION STARTS               │
│  1. Export settings to JSON backup      │
│  2. Save to wp-content/uploads/         │
│  3. Auto-deactivate Lite version        │
│  4. Pro reads existing DB options       │
└─────────────┬───────────────────────────┘
              │
              │ Automatic (no user action)
              ▼
┌─────────────────────────────────────────┐
│  ✅ SHOW MIGRATION SUCCESS NOTICE      │
│  • "Lite auto-deactivated" ✓            │
│  • "X configs preserved" ✓              │
│  • "Backup created" ✓                   │
│  • Download backup button               │
│  • Link to delete Lite plugin           │
└─────────────┬───────────────────────────┘
              │
              │ User reviews notice
              ▼
┌─────────────────────────────────────────┐
│  USER ACTIONS (Optional)                │
│  • Download backup JSON (recommended)   │
│  • View configurations (working!)       │
│  • Delete Lite plugin (safe now)        │
└─────────────┬───────────────────────────┘
              │
              │ User clicks "Delete" on Lite
              ▼
┌─────────────────────────────────────────┐
│  DELETE LITE (Safe)                     │
│  • uninstall.php detects Pro is active  │
│  • Skips data deletion                  │
│  • Only removes Lite files              │
│  • Backup remains in uploads/           │
└─────────────┬───────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│  🎉 MIGRATION COMPLETE                  │
│  • Pro version active                   │
│  • All data preserved in DB             │
│  • Backup file saved                    │
│  • Full features unlocked               │
│  • Zero manual steps required!          │
└─────────────────────────────────────────┘
```

---

## 🛡️ Data Preservation Strategy

### **Automatic (No User Action Required):**

1. **Shared Database Schema** ✅
   - Both versions use identical option names
   - Data automatically available to Pro

2. **Pro Reads Existing Data** ✅
   - No import needed
   - Works immediately upon activation

### **Manual Backup (Recommended):**

1. **Export Before Upgrade**
   - Users can export settings as JSON
   - Provides insurance against any issues

2. **Import After Upgrade** (if needed)
   - Only needed if something goes wrong
   - Restores exact previous state

---

## 📝 User Documentation

### **Upgrade Guide (for customers)**

**Step-by-Step Migration (FULLY AUTOMATIC!):**

1. **Install Pro Version:**
   - 📥 Download Pro plugin from your purchase email
   - 📤 Upload via WordPress: Plugins → Add New → Upload Plugin
   - ✅ Install (don't worry about Lite - it will be handled automatically)

2. **Activate Pro (Magic Happens Here!):**
   - ✅ Click "Activate" on Custom Product Image Upload Pro
   - 🤖 **Automatic actions:**
     - ✅ Pro detects your Lite configurations
     - ✅ Creates JSON backup of all your settings
     - ✅ Saves backup to `wp-content/uploads/cpiu-backups/`
     - ✅ **Automatically deactivates Lite version**
     - ✅ Preserves all your data
   - 🎉 See detailed "Auto-Migration Successful" notice

3. **Review Migration Notice:**
   - ✅ See confirmation: "Lite auto-deactivated ✓"
   - ✅ See backup file name and location
   - 📥 **Download backup** (recommended - keep for your records)
   - ✅ Click "View Your Configurations" to verify everything

4. **Delete Lite (Optional but Recommended):**
   - 🗑️ Go to Plugins page
   - 🗑️ Click "Delete" on Custom Product Image Upload Lite
   - ✅ Safe to delete - Pro has all your data + backup created

5. **Enjoy Pro Features:**
   - 🚀 Unlimited product configurations (no more 1 product limit!)
   - 📦 Bulk operations
   - ✏️ Edit existing configs
   - 💾 Import/Export
   - 🔒 Security logs
   - ⚡ CDN caching
   - 🎯 Priority support

**Total User Actions Required:** Just 2 clicks (Install + Activate)  
**Everything else:** Fully automatic! 🤖

---

## 🧪 Testing Checklist

### **Pre-Release Testing:**

- [ ] Install Lite, create 3 product configs
- [ ] Install Pro (don't activate)
- [ ] Activate Pro - verify migration notice appears
- [ ] Check all 3 configs visible in Pro admin
- [ ] Deactivate Lite
- [ ] Delete Lite - verify data NOT deleted
- [ ] Pro still shows all 3 configs
- [ ] Create new config in Pro - works correctly

### **Edge Cases:**

- [ ] Fresh Pro install (no Lite data) - no migration notice
- [ ] Lite → Pro → Lite (downgrade) - data preserved
- [ ] Both plugins active simultaneously - no conflicts
- [ ] Uploaded images still accessible after migration

---

## 🔧 Technical Implementation

### **Files to Modify:**

1. **Lite Version:**
   - `custom-product-image-upload-lite.php` - Add migration notice
   - `uninstall.php` - Check for Pro before deleting data

2. **Pro Version:**
   - `custom-product-image-upload.php` - Detect Lite migration
   - Add migration success notice

3. **Shared:**
   - No changes needed (database already compatible)

---

## 🚨 Rollback Plan

If migration fails:

1. **Keep Lite Active**
   - Don't delete Lite until Pro verified working

2. **Export/Import**
   - User can export from Lite
   - Import into Pro manually

3. **Database Direct**
   - Data exists in WP options table
   - Can be accessed by either version

---

## ✅ Success Metrics

Migration is successful when:

- ✅ All product configurations visible in Pro
- ✅ Default settings preserved
- ✅ Uploaded images still accessible
- ✅ No user reconfiguration needed
- ✅ Zero data loss reported
- ✅ < 5 minute migration time

---

## 📞 Support Resources

### **For Customers:**

**Migration Issues?**
- Email: support@nowdigiverse.com
- Include: WordPress version, Pro version, migration step where stuck

**Common Issues:**

**Q: Will I lose my settings during upgrade?**
A: No! Pro automatically creates a backup AND preserves all data in the database.

**Q: Do I need to manually deactivate Lite?**
A: No! Pro automatically deactivates Lite when it's activated. You just need to delete it later.

**Q: Where is my backup file?**
A: `wp-content/uploads/cpiu-backups/cpiu-lite-backup-[timestamp].json` - download link shown in migration notice.

**Q: What if something goes wrong?**
A: Your backup JSON file has everything. Just import it using Pro's Import/Export tab.

**Q: Can I keep both plugins active?**
A: No - Pro automatically deactivates Lite for safety. Both can't run simultaneously.

**Q: Do uploaded images migrate?**
A: Yes! Images stored in `wp-content/uploads/custom_product_images/` - both versions use same folder.

**Q: I deleted Lite and lost my settings!**
A: Impossible! Settings are in database (Pro reads them) + backup file exists. Check `wp-content/uploads/cpiu-backups/`.

---

## 🎯 Timeline

- **Phase 1:** ✅ Complete (database compatibility exists)
- **Phase 2:** 1-2 hours (add pre-migration notices to Lite)
- **Phase 3:** 3-4 hours (Pro auto-deactivation + export logic)
- **Phase 4:** 2 hours (detailed success notice with backup info)
- **Phase 5:** 2 hours (safe uninstall protection + download handler)
- **Testing:** 3-4 hours (comprehensive testing of auto-migration)

**Total Estimated Time:** 11-14 hours

---

## 📋 Deployment Checklist

- [ ] Add migration notice to Lite
- [ ] Add Pro detection to Pro version
- [ ] Update uninstall.php in Lite
- [ ] Test full migration flow
- [ ] Create user guide documentation
- [ ] Update product page with migration info
- [ ] Prepare support team
- [ ] Deploy Lite update
- [ ] Deploy Pro update
- [ ] Monitor for issues

---

**Document Version:** 1.0  
**Last Updated:** 2025-10-27  
**Status:** Ready for Implementation
