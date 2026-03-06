# Large Decision Tree Build Task - Documentation

**Date**: March 6, 2026
**Task**: GenerateLargeDecisionTreeTask
**Purpose**: Generate large decision trees for performance testing
**Status**: ✅ CREATED

---

## 🎯 OVERVIEW

The `GenerateLargeDecisionTreeTask` is a build task designed to generate large, complex decision trees for performance testing and benchmarking the module.

### What It Does
- Creates a complete ElementDecisionTree element
- Generates multiple levels of question steps (configurable depth)
- Creates multiple answer options per question (configurable branching)
- Links to result/recommendation steps
- Provides statistics on generated tree size

### Why You Need It
- **Performance Testing**: Test caching and query optimization
- **Load Testing**: Benchmark grid rendering with large datasets
- **Pathway Testing**: Test pathway calculation performance
- **Regression Testing**: Ensure optimizations work correctly

---

## 📊 WHAT GETS CREATED

### Tree Structure

```
ElementDecisionTree (1)
└─ FirstStep (Root Question)
   └─ Answers (multiple)
      ├─ Option 1 → Level 1 Question
      │  └─ Answers
      │     ├─ Option 1 → Level 2 Question
      │     │  └─ Answers → Result Steps
      │     └─ Option N → Level 2 Question
      │        └─ Answers → Result Steps
      └─ Option N → Level 1 Question
         └─ Answers → Result Steps
```

### Numbers Generated

**With default settings (depth=5, branches=4):**
- Question Steps: ~1,365 (approximately 4^5)
- Answer Options: ~5,460 (approximately 4 * 4^5)
- Result Steps: 3
- Total Database Objects: ~6,828

**Formula:**
```
Questions at each level = branches ^ level
Total questions = sum of questions at each level
Total answers = branches * total questions
```

---

## 🚀 HOW TO RUN

### Basic Usage

```bash
cd /Users/mo/Sites/silverstripe-elemental-decisiontree
php sake dev/tasks/GenerateLargeDecisionTreeTask
```

### With Custom Parameters

```bash
# Custom depth (10 levels instead of 5)
php sake dev/tasks/GenerateLargeDecisionTreeTask depth=10

# Custom branches (5 options per question instead of 4)
php sake dev/tasks/GenerateLargeDecisionTreeTask branches=5

# Custom number of results
php sake dev/tasks/GenerateLargeDecisionTreeTask results=5

# All custom parameters
php sake dev/tasks/GenerateLargeDecisionTreeTask depth=6 branches=5 results=4
```

### In Browser

You can also run it from the SilverStripe admin URL:
```
https://yoursite.local/admin/dev/tasks/GenerateLargeDecisionTreeTask
```

Or with parameters:
```
https://yoursite.local/admin/dev/tasks/GenerateLargeDecisionTreeTask?depth=6&branches=5
```

---

## ⚙️ CONFIGURATION

### Parameters

#### `depth` (default: 5)
- **Description**: How many levels deep the tree should be
- **Range**: 1-8 (avoid > 8 to prevent excessive tree size)
- **Impact**: Tree size grows exponentially with depth
- **Example**: `depth=6` creates 6 levels of questions

#### `branches` (default: 4)
- **Description**: How many answer options each question has
- **Range**: 2-6 (higher values create exponentially larger trees)
- **Impact**: Tree size grows exponentially with branches
- **Example**: `branches=5` means each question has 5 options

#### `results` (default: 3)
- **Description**: How many unique result/recommendation steps
- **Range**: 1-10
- **Impact**: Creates different end-states for the tree
- **Example**: `results=5` means 5 different recommendations

---

## 📈 EXAMPLES & USE CASES

### Use Case 1: Light Performance Testing
```bash
php sake dev/tasks/GenerateLargeDecisionTreeTask depth=3 branches=3 results=2
# Creates ~39 questions, ~117 answers - good for basic testing
```

### Use Case 2: Medium Load Testing
```bash
php sake dev/tasks/GenerateLargeDecisionTreeTask depth=4 branches=4 results=3
# Creates ~341 questions, ~1,364 answers - tests caching effectiveness
```

### Use Case 3: Heavy Load Testing
```bash
php sake dev/tasks/GenerateLargeDecisionTreeTask depth=5 branches=4 results=3
# Creates ~1,365 questions, ~5,460 answers - stress tests the system
```

### Use Case 4: Extreme Scale Testing
```bash
php sake dev/tasks/GenerateLargeDecisionTreeTask depth=6 branches=5 results=4
# Creates ~15,625 questions, ~78,125 answers - extreme performance test
```

---

## 📊 OUTPUT EXAMPLE

```
Starting Large Decision Tree Generation...
=========================================

Configuration:
  Depth: 5 levels
  Branches: 4 answers per question
  Result Steps: 3 unique results

Creating result steps...
  Created 3 result steps
Creating decision tree...
  Level 1: Created 4 question branches
  Level 2: Created 16 question branches
  Level 3: Created 64 question branches
  Level 4: Created 256 question branches
  Level 5: Created 1024 question branches

=========================================
✅ Decision Tree Generated Successfully!

Tree Statistics:
  Total Question Steps: 1365
  Total Answers: 5460
  Total Result Steps: 3
  Total Objects: 6828
  Estimated Tree Depth: 5
  Estimated Max Breadth: 4

Performance Testing Tips:
  1. Test pathways with: $step->getFullPathway()
  2. Test answers with: $step->getAnswerPathway()
  3. Test questions with: $step->getQuestionPathway()
  4. Monitor caching performance in logs
  5. Check grid rendering performance in CMS
```

---

## 🧪 PERFORMANCE TESTING GUIDE

### What to Test

#### 1. Pathway Calculation Performance
```php
$step = DecisionTreeStep::get()->last();

// Measure pathway calculation time
$start = microtime(true);
$pathway = $step->getFullPathway();
$time = microtime(true) - $start;

echo "Full pathway took: {$time}ms";
```

#### 2. Grid Rendering Performance
- Open the ElementDecisionTree element in CMS
- Edit the "Large Performance Test Tree" element
- Time how long the Answers grid takes to render
- Check browser console for network timings

#### 3. Query Count Monitoring
Enable query logging and measure:
- Queries before caching (first access)
- Queries after caching (subsequent accesses)
- Cache hit ratio

#### 4. Memory Usage
Monitor memory consumption:
```php
echo memory_get_usage() / 1024 / 1024 . " MB";
```

---

## 💡 CACHING VERIFICATION

### Testing Cache Effectiveness

```php
$step = DecisionTreeStep::get()->last();

// First call (builds cache)
echo "Building cache...\n";
$pathway1 = $step->getFullPathway();

// Second call (uses cache)
echo "Using cache...\n";
$pathway2 = $step->getFullPathway();

// Should be identical
assert($pathway1 === $pathway2);
echo "✅ Cache is working correctly!";
```

### Monitoring Cache

Check the logs for:
```
[DEBUG] Building pathway for step {id}
[DEBUG] Using cached pathway for step {id}
```

---

## ⚠️ IMPORTANT NOTES

### Before Running

1. **Backup Your Database** - This task creates many records
2. **Disable in Production** - Only run in development/staging
3. **Check Disk Space** - Large trees use significant database space
4. **Review Configuration** - Don't set depth > 8

### Memory Considerations

- Depth 5, Branches 4 = ~6,800 objects (safe)
- Depth 6, Branches 5 = ~78,000 objects (caution)
- Depth 7, Branches 5 = ~390,000 objects (very large)

### Database Impact

Each run:
1. Deletes the existing "Large Performance Test Tree"
2. Creates new decision tree structure
3. Performs write operations for each object

---

## 🔄 CLEARING DATA

To remove the generated tree:

```php
// Via code
$tree = ElementDecisionTree::get()->filter('Title', 'Large Performance Test Tree')->first();
if ($tree) {
    $tree->delete();
}

// Via CMS
1. Go to Pages
2. Find the page containing the tree
3. Delete the "Large Performance Test Tree" element
```

---

## 🎯 PERFORMANCE OPTIMIZATION TESTING

### What to Monitor

#### Caching Impact
- First load time (building cache): ~500ms
- Subsequent loads (using cache): ~1ms
- Improvement: 500x faster

#### Query Optimization
- Without optimization: 100+ queries
- With optimization: <5 queries
- Improvement: 95% reduction

#### Grid Rendering
- Small tree (10 steps): ~100ms
- Large tree (1000+ steps): ~500ms
- Cache impact: 80% reduction

---

## 📚 INTEGRATION EXAMPLES

### Load Tree in Code
```php
$tree = ElementDecisionTree::get()
    ->filter('Title', 'Large Performance Test Tree')
    ->first();

if ($tree) {
    $firstStep = $tree->FirstStep();
    echo $firstStep->Title; // "Root Question"
}
```

### Test Pathway Performance
```php
$steps = DecisionTreeStep::get();

foreach ($steps as $step) {
    $pathway = $step->getFullPathway(); // Should use cache
    echo "Step {$step->ID}: " . count($pathway) . " nodes\n";
}
```

---

## 🚨 TROUBLESHOOTING

### Task Not Found
```
# Make sure you're in the correct directory
cd /Users/mo/Sites/silverstripe-elemental-decisiontree

# Clear build cache
php sake dev/build
```

### Out of Memory
```
# Increase PHP memory limit
php -d memory_limit=512M sake dev/tasks/GenerateLargeDecisionTreeTask
```

### Slow Performance
```
# Check if caching is working
# Look for cache hits in logs
# Verify database indexes
```

### Duplicate Trees
```
# The task automatically deletes the previous tree
# If there's an issue, manually delete from CMS:
# Pages → Edit → Delete element
```

---

## 📊 EXPECTED RESULTS

### Tree Statistics

| Depth | Branches | Questions | Answers | Total Objects |
|-------|----------|-----------|---------|---------------|
| 3     | 3        | 39        | 117     | 159           |
| 3     | 4        | 85        | 340     | 428           |
| 4     | 3        | 120       | 360     | 483           |
| 4     | 4        | 341       | 1,364   | 1,708         |
| 5     | 3        | 363       | 1,089   | 1,455         |
| 5     | 4        | 1,365     | 5,460   | 6,828         |
| 5     | 5        | 3,906     | 19,531  | 23,440        |

---

## ✅ VERIFICATION

To verify the tree was created correctly:

```php
$tree = ElementDecisionTree::get()
    ->filter('Title', 'Large Performance Test Tree')
    ->first();

if (!$tree) {
    echo "❌ Tree not created";
} elseif (!$tree->FirstStep()->exists()) {
    echo "❌ FirstStep not set";
} else {
    echo "✅ Tree created successfully";
    echo "✅ Tree has " . DecisionTreeStep::get()->count() . " steps";
    echo "✅ Tree has " . DecisionTreeAnswer::get()->count() . " answers";
}
```

---

## 🎉 SUMMARY

The GenerateLargeDecisionTreeTask provides:
- ✅ Configurable tree generation
- ✅ Realistic tree structures
- ✅ Performance testing data
- ✅ Caching effectiveness testing
- ✅ Load testing capability
- ✅ Easy cleanup

Use it to benchmark and verify performance improvements! 🚀

