# Technical Implementation Plan — Custom Product Image Upload Lite

This implementation plan translates the free-version strategy into actionable phases with **upgrade-compatible data architecture**. The free version uses the same database schema, option names, and file structures as the pro version to ensure seamless data preservation during upgrades.

## Upgrade Compatibility Strategy

**Core Principle**: The lite version is built as a feature-limited version of the pro version, not as a separate product. This ensures:

- **Zero Data Loss**: All user data, uploaded files, and settings are preserved during upgrade
- **Configuration Preservation**: All user configurations (product selections, image limits, upload preferences) are maintained
- **No Reconfiguration**: Users don't need to reconfigure their settings after upgrading
- **Seamless Transition**: The upgrade process is invisible to end users
- **File Continuity**: All uploaded images remain accessible and linked to orders
- **Database Continuity**: Same table structures, option names, and meta keys as pro version

**Implementation Strategy**:
1. **Start with Pro Codebase**: use existing pro version as foundation for lite version
2. **Remove Features, Not Infrastructure**: Keep all database schemas, file paths, and data structures intact
3. **Disable Premium Features**: Use feature flags and capability checks to disable advanced functionality
4. **Preserve Core Architecture**: Maintain same classes, hooks, and data handling as pro version
5. **Add Upgrade Prompts**: Replace premium UI with upgrade messages while keeping underlying structure
6. **Natural Compatibility**: Since lite IS the pro version with features disabled, upgrade is simply re-enabling features

---

## Detailed Tasks & Subtasks

### Phase 1 — Codebase editing & Initial Setup (Week 1)
- **use and edit Pro Codebase**
  - use and edit entire pro plugin directory as foundation for lite version.
  - Rename main plugin file to `custom-product-image-upload-lite.php`.
  - Update plugin header (name, description, version) to reflect lite version.
  - Keep ALL existing classes, functions, and file structure intact.
  - **CRITICAL**: Do not rename or restructure any core classes - maintain exact same architecture.
- **Update Existing Data Class** 
  - Keep existing `class-cpiu-data.php` (don't rename to `class-cpiu-lite-data.php`).
  - Add `is_lite_version()` method that returns `true` for lite version.
  - Add lite-specific validation in existing methods to restrict multi-product configuration.
  - **Preserve All Pro Data Structure**: Keep exact same settings structure as pro version.
  - Add version flag to differentiate: `"version": "lite"` vs `"version": "pro"`.
  - **No Schema Changes**: All existing getters/setters, validation, and sanitization remain identical.
- **Update Existing Admin Class**
  - Keep existing `class-cpiu-admin.php` - don't rename.
  - Change admin menu title to `WooCommerce > Image Upload Lite`.
  - Add lite version restrictions: disable multi-product configuration UI.
  - Add upgrade prompts in place of premium feature sections.
  - **Keep All Pro Functionality**: Same nonce handling, capability checks, settings save logic.
- **Update Existing Frontend Class**
  - Keep existing `class-cpiu-frontend.php` - don't rename.
  - Add `is_lite_version()` checks to disable modal, cropper, drag-drop features.
  - Replace premium feature UI with upgrade prompts (but keep underlying hooks intact).
  - **Keep Core Upload Logic**: Same product matching, asset enqueuing, cart integration.
- **Update Existing Upload Class**
  - Keep existing `class-cpiu-upload.php` - don't rename.
  - **No Changes to Core Logic**: All file handling, validation, storage remains identical to pro.
  - Add lite restrictions: enforce single product limit through data validation.
  - **Preserve All Infrastructure**: Same file paths, meta keys, database operations, error handling.
- **Update Existing Assets**
  - Keep existing CSS/JS files - don't rename to "lite" versions.
  - Add lite version checks in JavaScript to disable premium functionality.
  - Use CSS classes to hide/disable premium UI elements while preserving structure.
  - **Preserve Asset Infrastructure**: Same enqueue logic, dependencies, and file organization.
- Acceptance criteria
  - Plugin activates; admin page loads and saves one product config.
  - Inline upload shows on the selected product; files save successfully.
  - No modal, no cropping, no drag & drop.

### Phase 2 — Feature Disabling & Access Control (Week 1-2)
- **Add Feature Flags & Version Detection**
  - Add `is_lite_version()` function that returns true for lite version.
  - Create feature flag system: `is_feature_enabled('cropping')`, `is_feature_enabled('multi_product')`, etc.
  - Update all premium feature code to check feature flags before execution.
- **Disable Premium Code Paths**
  - Add conditional loading: don't initialize cropper, modal, drag-drop classes in lite version.
  - Gate multi-product admin functionality behind `is_feature_enabled('multi_product')`.
  - Conditionally enqueue assets: skip premium JS/CSS in lite version.
- **Preserve Code Structure**
  - **Don't delete or remove code** - use conditional execution instead.
  - Keep all classes and functions intact for easy upgrade transition.
  - Use early returns or feature flags rather than code removal.
- Acceptance criteria
  - Premium features disabled but code structure preserved.
  - Feature flags correctly control access to pro functionality.
  - Lite version runs only core features while maintaining full codebase.

### Phase 2a — UI Replacement & Safe Deactivation (Week 2)
- **Replace Premium UI with Upgrade Prompts**
  - Keep original UI structure but replace content with upgrade messages.
  - Use feature flag checks to render upgrade prompts instead of premium controls.
  - Example: `if (!is_feature_enabled('cropping')) { render_upgrade_prompt('cropping'); return; }`
- **Conditional Asset Loading**
  - Use feature flags to conditionally enqueue premium assets.
  - Skip cropper.js, modal CSS, and drag-drop scripts in lite version.
  - **Keep Asset Files**: Don't delete files, just conditionally load them.
- **Conditional Hook Registration**  
  - Wrap AJAX/REST endpoint registration in feature flag checks.
  - Register only lite-compatible hooks and endpoints.
  - **Preserve Hook Structure**: Keep all hook definitions but gate their registration.
- **Template & Shortcode Gating**
  - Add feature checks in templates before rendering premium functionality.
  - Replace premium shortcode content with upgrade prompts when features disabled.
  - **Keep All Code**: Use conditional rendering rather than code removal.
- Acceptance criteria
  - Premium UI replaced with upgrade prompts, not removed entirely.
  - Feature flags correctly control what gets loaded and executed.
  - All original code preserved for seamless upgrade path.

### Phase 3 — Limitations & Upgrade Prompts (Week 3)
- Single-product enforcement
  - Store one configuration only; block additional entries with an info notice.
  - Display current configured Product ID with upgrade CTA.
- Authentication gate
  - Add login check around upload UI; show login link + upgrade CTA for guests.
- Upgrade messaging
  - Admin banner, frontend link, and settings page upgrade section.
- Acceptance criteria
  - Attempts to configure multiple products are guided to upgrade.
  - Guests see login prompt; logged-in users can upload.

### Phase 4 — Metadata & Branding (Week 4)
- Plugin header and identifiers
  - Populate header fields; confirm text domain and `load_plugin_textdomain`.
- WordPress.org `readme.txt`
  - Free features and premium upgrade list aligned with plan scope.
  - Installation, FAQ, changelog sections prepared.
- Acceptance criteria
  - Header validates; readme passes WP checks; scope messaging is consistent.

### Phase 5 — Upgrade Mechanisms (Week 4–5)
- Admin upgrade banner
  - Implement `cpiu_lite_admin_upgrade_banner()` and include on settings page.
- Frontend upgrade link
  - Append CTA after upload form when appropriate.
- Settings upgrade section
  - Show comparison table and upgrade link; no premium code included.
- Acceptance criteria
  - Clear CTAs in admin and frontend; no functional regressions.

### Phase 6 — Upgrade Transition & Feature Re-enabling (Week 3)
- **Simplified Upgrade Process**
  - Since lite IS the pro version with disabled features, upgrade = feature re-enabling.
  - No data migration needed - all data already in pro-compatible format.
  - No file moving required - already using pro file paths and naming.
  - No database changes needed - same schemas and structures already in use.
- **Version Flag Update**
  - Change version flag from `"lite"` to `"pro"` in settings.
  - Update license validation to enable premium features.
  - Re-enable feature flags: `cropping: true`, `multi_product: true`, etc.
- **Feature Activation**
  - Remove or bypass lite version checks (`is_lite_version()` returns false).
  - Enable premium asset loading (cropper.js, modal CSS, drag-drop scripts).
  - Register premium AJAX/REST endpoints that were conditionally disabled.
  - Replace upgrade prompts with actual premium functionality.
- **Automatic Configuration Recognition**
  - Pro version immediately recognizes existing lite data structure.
  - All existing configurations remain intact and functional.
  - Previously disabled features become available without setup.
  - **Zero User Intervention Required**: Everything works immediately after upgrade.
- **Testing & Validation**
  - Test feature re-enabling process with existing lite installations.
  - Verify all premium features activate correctly with existing data.
  - Confirm existing uploads, settings, and configurations work seamlessly.
- **Acceptance criteria**
  - **Instant Upgrade**: Pro features immediately available after license activation.
  - **Zero Data Loss**: All existing data, files, and configurations preserved.
  - **Zero Reconfiguration**: Users don't need to setup anything after upgrade.
  - **Seamless Transition**: Upgrade process is invisible to users - they just get more features.

---

## Dependencies & Sequencing
- Build order: Data → Admin → Frontend → Upload → Assets.
- Enqueue and hooks depend on data availability; admin save precedes frontend use.
- Upgrade prompts depend on single-product enforcement and auth gate.

## Validation Checklist
### Core Functionality
- [ ] Single product configuration only; attempts to add more are blocked.
- [ ] JPG/PNG uploads only; MIME/extension/size validated.
- [ ] Inline upload UI renders on configured product; no modal/cropper/drag-drop.
- [ ] Logged-in requirement enforced; guests see login + upgrade prompt.
- [ ] Admin settings save and reflect current Product ID.
- [ ] No premium classes/assets enqueued in lite.
- [ ] Upgrade CTAs present in admin and frontend.
- [ ] Readme and plugin header aligned with scope.

### Upgrade Compatibility
- [ ] **Database**: Uses same option names, meta keys, and table structures as pro version.
- [ ] **File Storage**: Uses same directory paths (`cpiu-uploads/`) and naming conventions as pro.
- [ ] **Settings Structure**: Pro-compatible JSON structure with version flags and extensible fields.
- [ ] **Configuration Preservation**: All user configurations (product settings, image limits, display preferences) preserved intact.
- [ ] **Custom Settings**: User-defined fields, preferences, and custom configurations transfer without loss.
- [ ] **Integration Settings**: WooCommerce hooks, cart preferences, and order processing settings maintained.
- [ ] **Data Migration**: Lite-to-pro upgrade preserves all existing data without loss.
- [ ] **File Links**: All uploaded images remain accessible and linked to orders after upgrade.
- [ ] **Rollback Safety**: Backup and rollback mechanisms work correctly.
- [ ] **Version Detection**: System correctly identifies and handles upgrade scenarios.
- [ ] **Configuration Validation**: Pro version validates and accepts all lite configuration formats.

## Work Ownership & Estimates (T-shirt sizes)
- Phase 1: S (codebase use and edit and initial version detection setup)
- Phase 2: S (feature flags and conditional execution)
- Phase 2a: S (UI replacement and asset gating)
- Phase 3: XS (limitations and upgrade prompts)
- Phase 4: S (metadata, readme)
- Phase 5: XS (CTAs)
- Phase 6: XS (upgrade testing - much simpler since no migration needed)

**Total Effort Reduction**: ~60% less work compared to building from scratch since we're modifying existing, tested codebase rather than rebuilding.

---

## Notes
- **Codebase Duplication Strategy**: Starting with pro version codebase ensures 100% compatibility - lite IS pro with features disabled.
- **Feature Flags Over Code Removal**: Using conditional execution rather than deleting code ensures seamless upgrade path.
- **Natural Upgrade Process**: Since lite uses same infrastructure as pro, upgrade simply re-enables features - no migration required.
- **Configuration Continuity**: Every user setting, preference, and configuration option is preserved and immediately usable after upgrade.
- Scope matches your edited free-version plan: single product only, JPG/PNG only, no cropping/modals/drag-drop, login required.
- **Instant Upgrade Experience**: Users get immediate access to pro features upon license activation - zero setup required.
- **Reduced Development Time**: ~60% less work since we're modifying existing codebase rather than building from scratch.
- **Risk Mitigation**: Using proven pro codebase reduces bugs and compatibility issues compared to building separate lite version.

## Hardening & Audit Checklist
### Security & Feature Restrictions
- [ ] **Conditionally disable** `wp_ajax_*` hooks for premium features (don't remove - use feature flags).
- [ ] **Conditionally skip** `register_rest_route` calls for premium features (keep code, gate registration).
- [ ] Verify `wp_enqueue_script/style` conditionally loads only lite assets based on feature flags.
- [ ] **Feature flag all premium functionality** - don't delete code, use `is_feature_enabled()` checks.
- [ ] Confirm admin interface shows upgrade prompts instead of premium settings (but keeps underlying logic).
- [ ] Ensure upgrade prompts are static HTML with no premium functionality accessible.
- [ ] Validate feature flags correctly control premium code execution.
- [ ] Run negative tests: clicking upgrade prompts shows upgrade page, doesn't execute premium features.
- [ ] Confirm premium classes exist but are conditionally loaded based on version.
- [ ] Review logs: no notices/errors when feature flags disable premium functionality.

### Upgrade Compatibility Audit
- [ ] **Version Flag Testing**: Test changing `version: "lite"` to `version: "pro"` enables all features correctly.
- [ ] **Feature Flag Testing**: Verify `is_feature_enabled()` correctly responds to version changes.
- [ ] **Asset Loading Testing**: Confirm premium assets (cropper.js, modal CSS) load after version change.
- [ ] **Hook Registration Testing**: Verify premium AJAX/REST endpoints register after feature re-enabling.
- [ ] **UI Transition Testing**: Confirm upgrade prompts are replaced with actual premium functionality.
- [ ] **Data Continuity Testing**: Since using same data structures, verify existing data works seamlessly with newly enabled features.
- [ ] **Configuration Preservation Testing**: All existing configurations remain intact and functional with new features.
- [ ] **File Access Testing**: Uploaded files immediately work with newly enabled premium features (cropping, etc.).
- [ ] **No Migration Testing**: Verify upgrade process requires zero data migration or file movement.
- [ ] **Instant Activation Testing**: Premium features should be immediately available after license validation.
- [ ] **Rollback Testing**: Test reverting from pro back to lite maintains all data and configurations.
- [ ] **Zero Setup Testing**: Confirm users don't need to reconfigure anything after upgrade.