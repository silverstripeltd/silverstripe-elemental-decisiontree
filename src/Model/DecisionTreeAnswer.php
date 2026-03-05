<?php

namespace DNADesign\SilverStripeElementalDecisionTree\Model;

use DNADesign\SilverStripeElementalDecisionTree\Forms\HasOneSelectOrCreateField;
use DNADesign\SilverStripeElementalDecisionTree\Services\DecisionTreePermissionService;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;

/**
 * DecisionTreeAnswer represents a single answer option within a question.
 *
 * An answer belongs to a question (DecisionTreeStep) and points to a resulting step,
 * creating the decision logic of the tree.
 *
 * When a user selects this answer in a question, the system navigates to the
 * ResultingStep, continuing the decision tree flow.
 *
 * Example structure:
 * Question: "Is your item broken?"
 *   ├─ Answer: "Yes" → ResultingStep: "How to repair"
 *   └─ Answer: "No" → ResultingStep: "Consider replacement?"
 *
 * Each answer can:
 * - Be reordered within its question via the Sort field
 * - Reference an existing step or trigger creation of new steps
 * - Be deleted only if it doesn't result in any dependent questions
 *
 * @package DNADesign\SilverStripeElementalDecisionTree\Model
 */
class DecisionTreeAnswer extends DataObject
{
    private static array $db = [
        'Title' => 'Varchar(255)',
        'Sort' => 'Int',
    ];

    private static array $has_one = [
        'Question' => DecisionTreeStep::class,
        'ResultingStep' => DecisionTreeStep::class,
    ];

    private static array $summary_fields = [
        'ID' => 'ID',
        'Title' => 'Title',
        'ResultingStep.Title' => 'Resulting Step',
    ];

    private static string $table_name = 'DecisionTreeAnswer';

    private static string $default_sort = 'Sort ASC';

    /**
     * Builds the CMS editing form for this answer.
     *
     * Provides fields for:
     * - Question selection (which question this answer belongs to)
     * - Answer text (what the user sees as an option)
     * - Resulting step selection (where this answer leads)
     *
     * The form adapts based on save status:
     * - Unsaved: Shows instructional message
     * - Saved: Shows full configuration with step selector
     *
     * Uses HasOneSelectOrCreateField to allow both selecting existing steps
     * and creating new steps on-the-fly.
     *
     * @return FieldList CMS form fields
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Remove fields that shouldn't be directly edited
        $fields->removeByName('ResultingStepID');
        $fields->removeByName('Sort');

        // Update the Question field label for better clarity
        $question = $fields->dataFieldByName('QuestionID');
        $question->setTitle('Answer for');
        $fields->insertBefore('Title', $question);

        // Only show advanced configuration if answer has been saved
        if ($this->IsInDB()) {
            // Get all available steps that can be selected as the result
            // Includes orphaned steps and currently selected step
            $availableStepsID = DecisionTreeStep::get_orphans()->column('ID');

            // Always include the currently selected step (if any) even if it's not orphaned
            if ($this->ResultingStep()->exists()) {
                array_push($availableStepsID, $this->ResultingStepID);
            }

            // Build dropdown options from available steps
            $steps = [];
            if ($availableStepsID) {
                $steps = DecisionTreeStep::get()->filter('ID', $availableStepsID)->map();
            }

            // Create the step selector - allows choosing existing or creating new
            $stepSelector = HasOneSelectOrCreateField::create(
                $this,
                'ResultingStep',
                'If selected, go to',
                $steps,
                $this->ResultingStep(),
                $this
            );

            $fields->addFieldToTab('Root.Main', $stepSelector);
        } else {
            // Unsaved answer - explain that it needs to be saved first
            $info = LiteralField::create('info', sprintf(
                '<p class="message info notice">%s</p>',
                'Save this answer in order to add a following step.'
            ));

            $fields->addFieldToTab('Root.Main', $info);
        }

        return $fields;
    }

    /**
     * Checks if the current user can create new answers.
     *
     * Delegates to the parent ElementDecisionTree element.
     * If they can manage the tree, they can create answers.
     *
     * @param Member|null $member User to check (current user if null)
     * @param array $context Additional context for permission checks
     * @return bool True if user can create
     */
    public function canCreate($member = null, $context = [])
    {
        return (new DecisionTreePermissionService())->canCreate($member, $context);
    }

    /**
     * Checks if the current user can view this answer.
     *
     * Delegates to the parent ElementDecisionTree element.
     * If they can edit the tree, they can view answers.
     *
     * @param Member|null $member User to check (current user if null)
     * @return bool True if user can view
     */
    public function canView($member = null)
    {
        return (new DecisionTreePermissionService())->canView($member);
    }

    /**
     * Checks if the current user can edit this answer.
     *
     * Delegates to the parent ElementDecisionTree element.
     * If they can edit the tree, they can edit answers.
     *
     * @param Member|null $member User to check (current user if null)
     * @return bool True if user can edit
     */
    public function canEdit($member = null)
    {
        return (new DecisionTreePermissionService())->canEdit($member);
    }

    /**
     * Checks if the current user can delete this answer.
     *
     * Deletion is only allowed if:
     * - User has permission to delete the tree AND
     * - This answer doesn't have a ResultingStep with dependent questions
     *
     * This prevents breaking the tree structure by orphaning questions.
     *
     * @param Member|null $member User to check (current user if null)
     * @return bool True if user can delete
     */
    public function canDelete($member = null)
    {
        $canDelete = (new DecisionTreePermissionService())->canDelete($member);

        // Only allow deletion if the resulting step has no dependent questions
        return $canDelete && !$this->ResultingStep()->exists();
    }

    /**
     * Generates a breadcrumb-style title showing context.
     *
     * Format: "Question Title > Answer Title"
     *
     * Used in displays where the answer needs to be shown with its question context.
     * For root-level answers (no question), just returns the answer title.
     *
     * @return ?string Formatted title with question context
     */
    public function TitleWithQuestion(): ?string
    {
        $title = $this->Title;

        // Add question context if this answer belongs to a question
        if ($this->Question()->exists()) {
            $title = sprintf('%s > %s', $this->Question()->Title, $title);
        }

        return $title;
    }

    /**
     * Generates a CMS edit link for this answer.
     *
     * Constructs the deep edit URL by:
     * 1. Finding the tree origin (root question)
     * 2. Building path from origin through all nested answers to this answer
     * 3. Creating valid CMS edit URL
     *
     * Returns null if the answer isn't part of a proper tree structure.
     *
     * @return ?string CMS edit URL or null if unavailable
     */
    public function getCMSEditLink(): ?string
    {
        // Must have a question to find the path back to the tree root
        if ($this->Question()->exists()) {
            $origin = $this->Question()->getTreeOrigin();

            if ($origin) {
                $root = $origin->ParentElement();

                if ($root) {
                    // Build the deep path: root link + question path + answer path
                    return Controller::join_links(
                        $root->CMSEditFirstStepLink(),
                        $this->Question()->getRecursiveEditPath(),
                        $this->getRecursiveEditPathForSelf()
                    );
                }
            }
        }

        // Fallback to default CMS edit link if structure is incomplete
        return parent::getCMSEditLink();
    }

    /**
     * Generates a CMS link to create a new resulting step for this answer.
     *
     * Used in templates to provide a quick "Create new step" button.
     * The link opens the editor for a new step related to this answer.
     *
     * @return string URL for creating a new step
     */
    public function CMSAddStepLink(): string
    {
        return Controller::join_links(
            $this->getCMSEditLink(),
            'itemEditForm/field/ResultingStep/item/new'
        );
    }

    /**
     * Builds the URL path segment for editing this specific answer.
     *
     * Returns the answer's portion of the edit path:
     * ItemEditForm/field/Answers/item/{id}/
     *
     * Used internally to construct nested edit URLs through the tree structure.
     *
     * @return string URL path segment for this answer
     */
    public function getRecursiveEditPath(): string
    {
        $path = sprintf('ItemEditForm/field/Answers/item/%s/', $this->ID);

        // If this answer belongs to a question, continue the path upward
        if ($this->Question()->exists()) {
            $path = Controller::join_links(
                $path,
                $this->Question()->getRecursiveEditPath()
            );
        }

        return $path;
    }

    /**
     * Returns only this answer's URL segment (without parent paths).
     *
     * Used when building complete paths to this answer.
     * Just returns: ItemEditForm/field/Answers/item/{id}/
     *
     * @return string URL path segment for this answer only
     */
    public function getRecursiveEditPathForSelf(): string
    {
        return sprintf('ItemEditForm/field/Answers/item/%s/', $this->ID);
    }
}
