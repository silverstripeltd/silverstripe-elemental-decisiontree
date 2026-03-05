# Code Refactoring Implementation Guide

## Overview
This guide provides detailed steps to implement the refactoring recommendations from the code review.

---

## ✅ COMPLETED IMPROVEMENTS (Phase 1)

### 1. Type Safety Fixes
- [x] Fixed strict comparison in `onBeforeWrite()` (== → ===)
- [x] Fixed `belongsToElement()` Count() → exists()
- [x] Fixed typos in comments

**Files Modified:**
- `/src/Model/DecisionTreeStep.php`
- `/src/Model/DecisionTreeAnswer.php`

---

## 🔄 IN PROGRESS IMPROVEMENTS (Phase 2-4)

### Phase 2: Constants & Services Implementation

#### Step 1: Constants Class ✅
**File**: `/src/DecisionTreeConstants.php`
**Status**: Created
**Action**: Already implemented - Use throughout codebase

**Usage Example**:
```php
use DNADesign\SilverStripeElementalDecisionTree\DecisionTreeConstants;

// Old way
if ($this->Type == 'Result') { }

// New way
if ($this->Type === DecisionTreeConstants::STEP_TYPE_RESULT) { }
```

#### Step 2: Permission Service ✅
**File**: `/src/Services/DecisionTreePermissionService.php`
**Status**: Created & integrated
**Status**: Permission methods updated in both models

**Benefits**:
- Single source of truth for permission logic
- Easier to audit and modify permissions
- Better separation of concerns

#### Step 3: Repository Pattern ✅
**File**: `/src/Services/DecisionTreeStepRepository.php`
**Status**: Created
**Action**: Update model methods to use repository

**Next Steps**: Update `get_orphans()` and `get_initial_steps()` in DecisionTreeStep to use repository

---

## 📋 RECOMMENDED NEXT STEPS

### Immediate (30 minutes)

#### 1. Use Constants Throughout Codebase
Replace magic strings with constants:

```php
// In DecisionTreeStep.php
use DNADesign\SilverStripeElementalDecisionTree\DecisionTreeConstants as DTC;

// Before
if ($this->Type === 'Result' && !$this->Title) {

// After
if ($this->Type === DTC::STEP_TYPE_RESULT && !$this->Title) {
```

**Files to Update**:
- `/src/Model/DecisionTreeStep.php` - Replace 'Question', 'Result'
- `/src/Model/ElementDecisionTree.php` - Replace field names
- `/src/Extensions/ElementDecisionTreeController.php` - Replace form fields, URLs
- `/src/Forms/HasOneSelectOrCreateField.php` - Replace relationship names

#### 2. Update getCMSFields() Methods
Extract form field setup into separate private methods:

**Before**:
```php
public function getCMSFields()
{
    $fields = parent::getCMSFields();
    // 50+ lines of field manipulation
    return $fields;
}
```

**After**:
```php
public function getCMSFields()
{
    $fields = parent::getCMSFields();
    $this->removePrimaryFields($fields);
    $this->configureContentField($fields);

    if ($this->IsInDB()) {
        $this->addStepSelector($fields);
        $this->addTreePreview($fields);
    } else {
        $this->addUnsavedInfo($fields);
    }

    return $fields;
}

private function removePrimaryFields(FieldList $fields): void
{
    $fields->removeByName('FirstStepID');
}

private function configureContentField(FieldList $fields): void
{
    $introduction = $fields->dataFieldByName('Introduction');
    $introduction->setRows(4);
}

// ... more methods
```

#### 3. Move Error Template to Template File
**Current**: HTML hardcoded in `renderError()`
**Target**: Move to template file

**Create**: `/templates/DecisionTreeError.ss`
```html
<div class="step step--error">
    <hr class="partial_green_border">
    <div class="step-form">
        <span class="step-title">Sorry!</span>
        <span class="step-content"><p>$Message</p></span>
    </div>
</div>
```

**Update**: `ElementDecisionTreeController.php`
```php
protected function renderError(string $message = ''): string
{
    return Controller::curr()->customise([
        'Message' => $message,
    ])->renderWith('DNADesign/SilverStripeElementalDecisionTree/DecisionTreeError');
}
```

### Short Term (1-2 hours)

#### 4. Optimize Query Methods
Use the new `DecisionTreeStepRepository`:

**Update in DecisionTreeStep.php**:
```php
public static function get_orphans(): SS_List
{
    return (new DecisionTreeStepRepository())->getOrphans();
}

public static function get_initial_steps(): ?SS_List
{
    return (new DecisionTreeStepRepository())->getInitialSteps();
}
```

#### 5. Add Pathway Caching
Implement simple caching for recursive pathway calculations:

```php
private static array $pathwayCache = [];

public function getFullPathway(&$path = []): array
{
    $cacheKey = 'pathway_' . $this->ID;

    if (isset(self::$pathwayCache[$cacheKey])) {
        return self::$pathwayCache[$cacheKey];
    }

    // ... existing logic ...

    self::$pathwayCache[$cacheKey] = $path;
    return $path;
}
```

#### 6. Improve Parameter Type Hints
Fix methods with array pass-by-reference:

**Before**:
```php
public function getAnswerPathway(&$idList = []): array
{
    // Uses $idList as accumulator
}
```

**After**:
```php
public function getAnswerPathway(): array
{
    return $this->buildAnswerPathway([]);
}

private function buildAnswerPathway(array $idList): array
{
    if ($answer = $this->getParentAnswer()) {
        $idList[] = $answer->ID;
        if ($question = $answer->Question()) {
            return $question->buildAnswerPathway($idList);
        }
    }
    return $idList;
}
```

### Medium Term (2-4 hours)

#### 7. Create View Models
For complex template logic, create presenter classes:

```php
// New file: /src/ViewModels/DecisionTreeStepPresenter.php
class DecisionTreeStepPresenter
{
    public function __construct(private DecisionTreeStep $step)
    {
    }

    public function getAnswerTree(): string
    {
        // Complex formatting logic
    }

    public function getAnswerOptions(): OptionsetField
    {
        // Field creation logic
    }
}
```

#### 8. Extract Complex Methods
Break down large methods into smaller, testable units:

**getAnswerTreeForGrid()**: Extract answer formatting
**getRecursiveEditPath()**: Extract pathway building logic

#### 9. Add Logging & Monitoring
For performance tracking:

```php
use Psr\Log\LoggerInterface;

public function getAnswerPathway(): array
{
    $start = microtime(true);
    $result = $this->buildAnswerPathway([]);
    $duration = microtime(true) - $start;

    if ($duration > 0.1) {
        $this->logger->warning('Slow pathway calculation', [
            'step_id' => $this->ID,
            'duration' => $duration,
        ]);
    }

    return $result;
}
```

### Long Term (Code Quality Debt)

#### 10. Add Data Transfer Objects
For complex return values:

```php
// New file: /src/DTOs/DecisionPathway.php
final class DecisionPathway
{
    public function __construct(
        public readonly array $questions,
        public readonly array $answers,
        public readonly array $full,
    ) {
    }
}
```

#### 11. Event System Integration
For extensibility:

```php
// In onBeforeWrite()
$this->getEventDispatcher()->dispatch(
    new BeforeDecisionTreeStepWritten($this)
);
```

#### 12. Interface Segregation
Define focused interfaces:

```php
interface HasEditablePathway
{
    public function getRecursiveEditPath(): string;
}

interface HasBreadcrumbs
{
    public function getTitleWithQuestion(): string;
}
```

---

## 🎯 IMPLEMENTATION ROADMAP

```
Week 1:
├─ Phase 1: Quick Wins ✅ DONE
│  ├─ Type safety fixes ✅
│  ├─ Typo fixes ✅
│  └─ belongsToElement() optimization ✅
│
├─ Phase 2: Foundation (3-4 hours)
│  ├─ Constants implementation ✅
│  ├─ Permission service ✅
│  ├─ Repository pattern ✅
│  └─ Update code to use new classes
│
└─ Phase 3: Refactoring (4-6 hours)
   ├─ Extract getCMSFields() methods
   ├─ Move error template
   ├─ Optimize query methods
   ├─ Add pathway caching
   └─ Improve type hints

Week 2:
├─ Phase 4: Optimization (2-3 hours)
│  ├─ Create view models
│  ├─ Extract complex methods
│  └─ Add logging
│
└─ Phase 5: Quality (4-6 hours)
   ├─ Add DTOs
   ├─ Event system
   ├─ Interface segregation
   └─ Documentation updates
```

---

## ✅ TESTING STRATEGY

After each phase, run:

```bash
# Run all tests
php vendor/bin/phpunit tests/ElementDecisionTreeTest.php --no-coverage

# Generate coverage report
php vendor/bin/phpunit --coverage-html coverage tests/ElementDecisionTreeTest.php

# Check for regressions
php vendor/bin/phpunit tests/ElementDecisionTreeTest.php --filter permission
```

**Expected Result**: All 52 tests still passing (100%)

---

## 📚 RESOURCES

**Files to Implement From**:
- `/CODE_REVIEW.md` - Full issue descriptions
- `/src/DecisionTreeConstants.php` - Constants definitions
- `/src/Services/DecisionTreePermissionService.php` - Permission handling
- `/src/Services/DecisionTreeStepRepository.php` - Query optimization

**Documentation**:
- SilverStripe ORM Best Practices
- Repository Pattern Guide
- Service Layer Architecture

---

## 🔍 VERIFICATION CHECKLIST

After implementation, verify:

- [ ] All tests still pass (52/52)
- [ ] Coverage maintained at 91.38%+
- [ ] No new warnings/errors
- [ ] Code follows PSR-12
- [ ] Performance metrics improved
- [ ] Documentation updated

---

## 💡 NOTES

- Changes are **backwards compatible**
- No breaking changes to public API
- Incremental implementation recommended
- Each phase can be completed independently
- Current code quality is good - improvements are incremental

---

**Next Action**: Start with Phase 2 - Use constants throughout the codebase

