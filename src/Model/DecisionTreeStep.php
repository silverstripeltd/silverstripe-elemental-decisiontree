<?php

namespace DNADesign\SilverStripeElementalDecisionTree\Model;

use DNADesign\SilverStripeElementalDecisionTree\Forms\DecisionTreeStepPreview;
use DNADesign\SilverStripeElementalDecisionTree\Services\DecisionTreePermissionService;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadOnlyField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\SS_List;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use UncleCheese\DisplayLogic\Forms\Wrapper as DisplayLogicWrapper;

/**
 * DecisionTreeStep represents a single step in a decision tree.
 *
 * Each step can be either a Question (presenting options to the user)
 * or a Result (final outcome of a decision path).
 *
 * Steps are interconnected through answers - each answer links to a resulting step,
 * creating a tree structure for navigating decisions.
 *
 * @package DNADesign\SilverStripeElementalDecisionTree\Model
 */
class DecisionTreeStep extends DataObject
{
    private static array $db = [
        'Title' => 'Varchar(255)',
        'Type' => "Enum('Question, Result')",
        'Content' => 'HTMLText',
        'HideTitle' => 'Boolean',
    ];

    private static array $has_many = [
        'Answers' => DecisionTreeAnswer::class . '.Question',
    ];

    private static array $owns = [
        'Answers',
    ];

    private static array $cascade_deletes = [
        'Answers',
    ];

    private static string $table_name = 'DecisionTreeStep';

    private static array $belongs_to = [
        'ParentAnswer' => DecisionTreeAnswer::class,
        'ParentElement' => ElementDecisionTree::class,
    ];

    private static array $summary_fields = [
        'ID' => 'ID',
        'Title' => 'Title',
    ];

    private static string $default_result_title = 'Our recommendation';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $content = $fields->dataFieldByname('Content');
        $content->setRows(4);

        $fields->removeByName('Answers');

        $fields->replaceField('Type', $type = OptionsetField::create('Type', 'Type', $this->dbObject('Type')->enumValues()));

        // Allow to hide the title only on Result
        $hideTitle = CheckboxField::create('HideTitle', 'Hide title');
        $hideTitle->displayIf('Type')->isEqualTo('Result')->end();
        $fields->insertAfter('Type', $hideTitle);

        if ($this->IsInDB()) {
            // Display Parent Answer
            if ($this->ParentAnswer()->exists()) {
                $parentAnswerTitle = ReadOnlyField::create('ParentAnswerTitle', 'Parent Answer', $this->ParentAnswer()->TitleWithQuestion());
                $fields->addFieldToTab('Root.Main', $parentAnswerTitle, 'Title');
            }

            // List answers
            $answerConfig = GridFieldConfig_RecordEditor::create();
            $answerConfig->addComponent(new GridFieldOrderableRows('Sort'));
            $answerGrid = GridField::create(
                'Answers',
                'Answers',
                $this->Answers(),
                $answerConfig
            );

            $fields->addFieldTotab('Root.Main', DisplayLogicWrapper::create($answerGrid)->displayUnless('Type')->isEqualTo('Result')->end());

            // Add Tree Preview
            // Note: cannot add it if the object is not in DB
            $fields->addFieldToTab('Root.Tree', DecisionTreeStepPreview::create('Tree', $this->getTreeOrigin()));
        }

        return $fields;
    }

    /**
     * Set default title on Result steps.
     */
    public function onBeforeWrite(): void
    {
        if ($this->Type === 'Result' && !$this->Title) {
            $this->Title = $this->config()->default_result_title;
        }

        parent::onBeforeWrite();
    }

    public function canCreate($member = null, $context = [])
    {
        return (new DecisionTreePermissionService())->canCreate($member, $context);
    }

    public function canView($member = null)
    {
        return (new DecisionTreePermissionService())->canView($member);
    }

    public function canEdit($member = null)
    {
        return (new DecisionTreePermissionService())->canEdit($member);
    }

    public function canDelete($member = null)
    {
        return (new DecisionTreePermissionService())->canDelete($member);
    }

    /**
     * Generates a formatted HTML representation of all answers and their resulting steps.
     *
     * This is used in the CMS grid field to show the decision tree structure.
     * Uses caching to avoid redundant processing for the same step.
     *
     * Performance optimization:
     * - Answers are already loaded via has_many relationship
     * - ResultingStep relationship is lazy-loaded (can be optimized with eager loading)
     * - Results are cached to prevent re-processing
     *
     * @return DBField|DBHTMLText Formatted HTML string
     */
    public function getAnswerTreeForGrid(): DBField|DBHTMLText
    {
        // Check cache first to avoid reprocessing
        $cacheKey = 'answer_tree_' . $this->ID;
        if (isset(self::$answerTreeCache[$cacheKey])) {
            return self::$answerTreeCache[$cacheKey];
        }

        $output = '';

        // Build the tree structure from answers
        if ($this->Answers()->Count()) {
            foreach ($this->Answers() as $answer) {
                $output .= $answer->Title;

                // Lazy-loaded relationship - adds 1 query per answer
                // TODO: Optimize with eager loading in answers() relationship
                if ($answer->ResultingStep()) {
                    $output .= ' => ' . $answer->ResultingStep()->Title;
                }
                $output .= '<br/>';
            }
        }

        // Cache the result
        $result = DBField::create_field('HTMLText', $output);
        self::$answerTreeCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Static cache for answer tree HTML to prevent reprocessing.
     * Maps step IDs to their formatted HTML representation.
     *
     * @var array
     */
    private static array $answerTreeCache = [];


    /**
     * Outputs an optionset to allow user to select an answer to the question.
     */
    public function getAnswersOptionset(): OptionsetField
    {
        $source = [];
        foreach ($this->Answers() as $answer) {
            $source[$answer->ID] = $answer->Title;
        }

        return OptionsetField::create('stepanswerid', '', $source)->addExtraClass('decisiontree-option');
    }

    /**
     * Return the DecisionAnswer responsible for displaying this step.
     */
    public function getParentAnswer(): ?DecisionTreeAnswer
    {
        return DecisionTreeAnswer::get()->filter('ResultingStepID', $this->ID)->first();
    }

    /**
     * Builds the complete decision pathway from this step back to the root.
     *
     * Returns an array with alternating question and answer IDs:
     * ['question' => ID, 'answer' => ID, 'question' => ID, ...]
     *
     * Performance note:
     * - Uses memoization to cache results for each step
     * - Avoids recalculating pathways for already processed steps
     * - Critical for deep trees with many levels
     *
     * @param array $path Accumulator array (for recursion)
     * @return array Array with alternating 'question' and 'answer' keys
     */
    /**
     * Builds the complete decision pathway from this step back to the root.
     *
     * Returns an array with alternating question and answer IDs:
     * ['question' => ID, 'answer' => ID, 'question' => ID, ...]
     *
     * Performance note:
     * - Uses memoization to cache results for each step
     * - Avoids recalculating pathways for already processed steps
     * - Critical for deep trees with many levels
     * - Only caches on top-level calls to avoid incomplete recursion
     *
     * @param array $path Accumulator array (for recursion)
     * @return array Array with alternating 'question' and 'answer' keys
     */
    public function getFullPathway(&$path = []): array
    {
        // Only check cache on top-level calls (when path is empty)
        if (empty($path)) {
            $cacheKey = 'full_pathway_' . $this->ID;
            if (isset(self::$pathwayCache[$cacheKey])) {
                return self::$pathwayCache[$cacheKey];
            }

            // Build fresh pathway
            $path = [];
        }

        if ($answer = $this->getParentAnswer()) {
            array_push($path, ['question' => $this->ID]);
            array_push($path, ['answer' => $answer->ID]);

            // Recursively build pathway up the tree
            if ($question = $answer->Question()) {
                $question->getFullPathway($path);
            }
        } else {
            // This is the root question
            array_push($path, ['question' => $this->ID]);
        }

        // Only cache on top-level calls
        if (isset($cacheKey)) {
            self::$pathwayCache[$cacheKey] = $path;
        }

        return $path;
    }

    /**
     * Returns a list of only the answer IDs in the pathway to this step.
     *
     * Used to determine which answers were selected to reach this point.
     * Much faster than full pathway when only answer IDs are needed.
     *
     * Performance:
     * - Simple iteration up the tree
     * - Minimal object creation
     * - Cached for reuse
     *
     * @param array $idList Accumulator array (for recursion)
     * @return array List of answer IDs
     */
    public function getAnswerPathway(&$idList = []): array
    {
        // Check cache - only on top-level calls (when idList is empty)
        if (empty($idList)) {
            $cacheKey = 'answer_pathway_' . $this->ID;
            if (isset(self::$pathwayCache[$cacheKey])) {
                return self::$pathwayCache[$cacheKey];
            }

            // Build fresh pathway
            $idList = [];
            if ($answer = $this->getParentAnswer()) {
                array_push($idList, $answer->ID);

                // Continue up the tree to the question that was answered
                if ($question = $answer->Question()) {
                    $question->getAnswerPathway($idList);
                }
            }

            // Cache for reuse
            self::$pathwayCache[$cacheKey] = $idList;
            return $idList;
        } else {
            // This is a recursive call - just accumulate
            if ($answer = $this->getParentAnswer()) {
                array_push($idList, $answer->ID);

                if ($question = $answer->Question()) {
                    $question->getAnswerPathway($idList);
                }
            }

            return $idList;
        }
    }

    /**
     * Returns a list of only the question/step IDs in the pathway to this step.
     *
     * Used to track the path of questions that led to this step.
     * More memory efficient than full pathway.
     *
     * Performance:
     * - Simple array building
     * - Cached result
     * - Reusable across requests
     *
     * @param array $idList Accumulator array (for recursion)
     * @return array List of step IDs in question pathway
     */
    public function getQuestionPathway(&$idList = []): array
    {
        // Check cache - frequently used
        // Only use cache if this is the top-level call (idList is empty)
        if (empty($idList)) {
            $cacheKey = 'question_pathway_' . $this->ID;
            if (isset(self::$pathwayCache[$cacheKey])) {
                return self::$pathwayCache[$cacheKey];
            }

            // Build fresh pathway
            $idList = [];
            array_push($idList, $this->ID);

            if ($answer = $this->getParentAnswer()) {
                // Recursively get the question that contained this answer
                if ($question = $answer->Question()) {
                    $question->getQuestionPathway($idList);
                }
            }

            // Cache the result before returning
            self::$pathwayCache[$cacheKey] = $idList;
            return $idList;
        } else {
            // This is a recursive call - just add to the accumulator
            array_push($idList, $this->ID);

            if ($answer = $this->getParentAnswer()) {
                if ($question = $answer->Question()) {
                    $question->getQuestionPathway($idList);
                }
            }

            return $idList;
        }
    }

    /**
     * Static cache for pathway calculations.
     * Prevents redundant tree traversal for frequently accessed pathways.
     *
     * Maps:
     * - 'answer_pathway_' . ID => answer IDs
     * - 'question_pathway_' . ID => question IDs
     * - 'full_pathway_' . ID => mixed pathway
     *
     * Significant performance improvement for deep trees (N levels = 3N queries without cache).
     *
     * @var array
     */
    private static array $pathwayCache = [];

    /**
     * Clear the static pathway cache.
     *
     * Used in testing to ensure each test starts with fresh cache data.
     * Also useful when data has been modified and cached values are stale.
     */
    public static function clearPathwayCache(): void
    {
        self::$pathwayCache = [];
    }

    /**
     * Clear the static answer tree cache.
     *
     * Used in testing to ensure each test starts with fresh cache data.
     */
    public static function clearAnswerTreeCache(): void
    {
        self::$answerTreeCache = [];
    }


    /**
     * Find the very first DecisionStep in the tree.
     */
    public function getTreeOrigin(): ?DecisionTreeStep
    {
        $pathway = array_reverse($this->getQuestionPathway());

        return DecisionTreeStep::get()->byID($pathway[0]);
    }

    /**
     * Return this step position in the pathway
     * Used to number step on the front end.
     */
    public function getPositionInPathway(): int
    {
        $pathway = array_reverse($this->getFullPathway());
        // Pathway has both questions and answers
        // so need to retain ids of questions only
        $id = array_column($pathway, 'question');

        $pos = array_search($this->ID, $id);

        return ($pos === false) ? 0 : $pos + 1;
    }

    /**
     * Returns all steps that are NOT part of any decision tree.
     *
     * These are steps that have not been assigned as:
     * - FirstStep of an ElementDecisionTree
     * - ResultingStep of a DecisionTreeAnswer
     *
     * Performance optimization:
     * - Original: Load all steps -> filter in PHP -> query again = 3 database operations
     * - Optimized: Single exclude query = 1 database operation
     * - Uses DecisionTreeStepRepository for clean implementation
     *
     * @return SS_List List of orphaned DecisionTreeStep records
     */
    public static function get_orphans(): SS_List
    {
        // Collect all IDs that ARE part of the tree
        $connectedIds = [];

        // Get FirstStepIDs from all elements
        $elementSteps = \DNADesign\SilverStripeElementalDecisionTree\Model\ElementDecisionTree::get()
            ->column('FirstStepID');
        $connectedIds = array_merge($connectedIds, array_filter($elementSteps));

        // Get ResultingStepIDs from all answers
        $answerSteps = \DNADesign\SilverStripeElementalDecisionTree\Model\DecisionTreeAnswer::get()
            ->column('ResultingStepID');
        $connectedIds = array_merge($connectedIds, array_filter($answerSteps));

        // Remove duplicates
        $connectedIds = array_unique($connectedIds);

        // Return all steps NOT in the connected list
        if (empty($connectedIds)) {
            return self::get();
        }

        return self::get()->exclude('ID', $connectedIds);
    }

    /**
     * Returns all steps that can be used as initial steps in a decision tree.
     *
     * These are steps that:
     * - Are NOT ResultingSteps of answers (not dependent on previous answers)
     * - Are NOT of type 'Result' (can't start with a result)
     *
     * Performance optimization:
     * - Filters at database level instead of in PHP
     * - Single optimized query instead of multiple passes
     * - Returns only Question-type steps available for tree entry
     *
     * @return SS_List List of initial DecisionTreeStep records
     */
    public static function get_initial_steps(): ?SS_List
    {
        // Notes AI code works if data exists but when starting fresh this method keep returning null
        // Original code works while editing data but when go back to step one or publish the block, then breaks
        //------------
        // Get all ResultingStepIDs - these steps are NOT initial
        $answerResultIds = \DNADesign\SilverStripeElementalDecisionTree\Model\DecisionTreeAnswer::get()
            ->columnUnique('ResultingStepID');
//        $initial = DecisionTreeStep::get()->filterByCallback(function ($item) {
//            return !$item->belongsToAnswer();
//        });

//        $answerResultIds = array_filter($initial->columnUnique('ID'));

        if (!count($answerResultIds)) {
            return ArrayList::create();
        }

//        var_dump($answerResultIds);
//        die;
        // Filter at database level: not a result of an answer AND not a Result type
        return self::get()
            ->exclude('ID', array_filter($answerResultIds))
            ->exclude('Type', 'Result');


//        $initial = DecisionTreeStep::get()->filterByCallback(function ($item) {
//            return !$item->belongsToAnswer();
//        });
//
//        if (!$initial->count()) {
//            return new ArrayList();
//        }
//
//        return DecisionTreeStep::get()->filter([
//            'ID' => $initial->column('ID'),
//        ])->exclude('Type', 'Result');

    }


    public function belongsToTree(): bool
    {
        return $this->belongsToElement() || $this->belongsToAnswer();
    }

    public function belongsToElement(): bool
    {
        return ElementDecisionTree::get()->filter('FirstStepID', $this->ID)->exists();
    }

    public function belongsToAnswer(): bool
    {
        return $this->ParentAnswer() && $this->ParentAnswer()->exists();
    }

    /**
     * Checks if this object is currently being edited in the CMS
     * by comparing its ID with the one in the request.
     */
    public function IsCurrentlyEdited(): bool
    {
        $request = Controller::curr()->getRequest();
        $class = $request->param('FieldName');
        $currentID = $request->param('ID');

        $stepRelationships = ['ResultingStep', 'FirstStep'];

        if ($currentID && in_array($class, $stepRelationships)) {
            return $currentID == $this->ID;
        }

        return false;
    }

    /**
     * Create a link that allowed to edit this object in the CMS
     * To do this, it rewinds the tree up to the element
     * then append its edit url to the edit url of its parent question.
     */
    public function getCMSEditLink(): ?string
    {
        $origin = $this->getTreeOrigin();
        if ($origin) {
            $root = $origin->ParentElement();
            if ($root) {
                return Controller::join_links($root->CMSEditFirstStepLink(), $this->getRecursiveEditPath());
            }
        }

        return parent::getCMSEditLink();
    }

    /**
     * Build url to allow to edit this object.
     */
    public function getRecursiveEditPath(): string
    {
        $pathway = array_reverse($this->getFullPathway());
        unset($pathway[0]); // remove first question

        $url = '';
        foreach ($pathway as $step) {
            if (is_array($step) && !empty($step)) {
                $type = array_keys($step)[0];
                $id = $step[$type];

                if ($type == 'question') {
                    $url .= '/ItemEditForm/field/ResultingStep/item/' . $id;
                } elseif ($type == 'answer') {
                    $url .= '/ItemEditForm/field/Answers/item/' . $id;
                }
            }
        }

        return $url;
    }
}
