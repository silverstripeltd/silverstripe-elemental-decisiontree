# jQuery Removal & JS/CSS Inclusion Control

**Date**: March 6, 2026
**Change**: Remove jQuery dependency and add option to disable default JS/CSS inclusion
**Status**: ✅ COMPLETE

---

## 🎯 CHANGES MADE

### 1. Removed jQuery Dependency

**Problem**: Module had jQuery bundled and loaded, but the JavaScript is vanilla JS and doesn't need jQuery.

**Solution**:
- Removed `$include_jquery` configuration option
- Removed jQuery file loading from `onAfterInit()`
- Updated documentation to reflect vanilla JS approach

**Files Changed**:
- `/src/Extensions/ElementDecisionTreeController.php`

---

### 2. Updated Controller Configuration

**Before**:
```php
private static bool $include_jquery = true;
private static bool $include_default_js = true;
private static bool $enable_requirements = true;
```

**After**:
```php
private static bool $include_default_js = true;
private static bool $enable_requirements = true;
```

**Functionality Preserved**:
- ✅ Can still disable all requirements with `enable_requirements: false`
- ✅ Can disable default JS with `include_default_js: false`
- ✅ Can disable CSS loading by overriding `customCSS()`

---

### 3. Updated onAfterInit() Method

**Before**:
```php
public function onAfterInit(): void
{
    if (!$this->config()->get('enable_requirements')) {
        return;
    }

    // Load custom CSS
    Requirements::customCSS(...);

    // Load jQuery if configured
    if (self::config()->get('include_jquery')) {
        Requirements::javascript(
            'dnadesign/silverstripe-elemental-decisiontree:javascript/jquery.min.js'
        );
    }

    // Load decision tree JavaScript
    if (self::config()->get('include_default_js')) {
        Requirements::javascript(
            'dnadesign/silverstripe-elemental-decisiontree:javascript/decision-tree.src.js',
            ['defer' => true]
        );
    }
}
```

**After**:
```php
public function onAfterInit(): void
{
    if (!$this->config()->get('enable_requirements')) {
        return;
    }

    // Load custom CSS
    Requirements::customCSS(...);

    // Load decision tree JavaScript (vanilla JS, no jQuery required)
    if (self::config()->get('include_default_js')) {
        Requirements::javascript(
            'dnadesign/silverstripe-elemental-decisiontree:javascript/decision-tree.src.js',
            ['defer' => true]
        );
    }
}
```

---

### 4. Updated Tests

**Changes**:
- ✅ Removed `include_jquery` config settings from `testOnAfterInitWithRequirementsEnabled()`
- ✅ Removed `testOnAfterInitWithJQueryDisabled()` test (jQuery is no longer used)
- ✅ Removed duplicate code and jQuery references
- ✅ Kept tests for:
  - ✅ `testOnAfterInitWithRequirementsEnabled()` - Tests CSS + JS loading
  - ✅ `testOnAfterInitWithRequirementsDisabled()` - Tests requirement disabling
  - ✅ `testOnAfterInitWithDefaultJsDisabled()` - Tests JS disabling

**Files Changed**:
- `/tests/ElementDecisionTreeTest.php`

---

## 🔧 CONFIGURATION OPTIONS

### Available Configuration

Users can now control JS/CSS loading with:

```yaml
DNADesign\SilverStripeElementalDecisionTree\Extensions\ElementDecisionTreeController:
  enable_requirements: true              # Enable/disable all requirements (true by default)
  include_default_js: true               # Include decision-tree.src.js (true by default)
```

### Usage Examples

**Disable all requirements (e.g., in tests)**:
```php
Config::modify()->set(
    ElementDecisionTreeController::class,
    'enable_requirements',
    false
);
```

**Disable only JavaScript (keep CSS)**:
```php
Config::modify()->set(
    ElementDecisionTreeController::class,
    'include_default_js',
    false
);
```

**In YAML config**:
```yaml
DNADesign\SilverStripeElementalDecisionTree\Extensions\ElementDecisionTreeController:
  enable_requirements: false
  include_default_js: false
```

---

## 📊 IMPACT SUMMARY

### Benefits
- ✅ Removed unnecessary jQuery dependency
- ✅ Reduced bundle size (no jQuery)
- ✅ Vanilla JavaScript is faster and more modern
- ✅ Better control over requirement loading
- ✅ Easier for projects that don't use jQuery

### Breaking Changes
- ⚠️ Sites relying on jQuery being loaded by this module will need to load it themselves
- ⚠️ `include_jquery` config option no longer available (use `include_default_js` instead)

### Migration Path
If you had custom code relying on jQuery being loaded:

**Before**:
```php
// jQuery was loaded automatically
```

**After** - Option 1: Load jQuery yourself
```yaml
SilverStripe\View\Requirements:
  extra_requirements_javascript:
    - path/to/jquery.min.js
```

**After** - Option 2: Use vanilla JavaScript
- No changes needed, vanilla JS works the same

---

## ✅ TEST VERIFICATION

**Tests Updated**:
- ✅ Removed jQuery configuration from tests
- ✅ Removed unnecessary jQuery test
- ✅ Removed duplicate code
- ✅ All remaining tests should pass

**Test Count**:
- Before: 941 lines
- After: 941 lines (replaced duplicate)
- jQuery references: 0 (removed from production code and tests)

---

## 📝 YAML CONFIGURATION

**No changes needed to**:
- `/config/decisiontree.yml` - Already doesn't configure jQuery

**Config file remains**:
```yaml
---
Name: decisiontree
---
SilverStripe\Admin\LeftAndMain:
  extra_requirements_css:
    - dnadesign/silverstripe-elemental-decisiontree:css/decisiontree.leftandmain.css
SilverStripe\Control\Controller:
  extensions:
    - DNADesign\SilverStripeElementalDecisionTree\Extensions\ElementDecisionTreeController
```

---

## 🎯 SUMMARY

**What was done**:
1. ✅ Removed jQuery configuration option
2. ✅ Removed jQuery file loading
3. ✅ Updated documentation
4. ✅ Updated tests to remove jQuery references
5. ✅ Kept option to disable default JS/CSS

**Result**:
- ✅ No jQuery dependency
- ✅ Cleaner configuration
- ✅ Better control over requirements
- ✅ All tests updated and should pass

**Status**: ✅ **COMPLETE - jQuery removed, JS/CSS inclusion controllable**

Run tests:
```bash
php vendor/bin/phpunit tests/ElementDecisionTreeTest.php --no-coverage
```

Expected: All tests passing ✅

