<?php

namespace DNADesign\SilverStripeElementalDecisionTree\Model;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\SilverStripeElementalDecisionTree\Forms\DecisionTreeStepPreview;
use DNADesign\SilverStripeElementalDecisionTree\Forms\HasOneSelectOrCreateField;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\LiteralField;

/**
 * ElementDecisionTree represents a complete decision tree element.
 *
 * This is the main container for a decision tree that can be embedded on a page.
 * It manages:
 * - The entry point (FirstStep) of the tree
 * - Optional introduction text displayed before the tree
 * - Configuration for tree display and behavior
 *
 * The tree extends BaseElement from the Elemental module,
 * allowing it to be added as a block/element on any page.
 *
 * Typical structure:
 * ElementDecisionTree (this element)
 *   └─ FirstStep (DecisionTreeStep - the starting question)
 *      └─ Answers (DecisionTreeAnswer - options for the question)
 *         └─ ResultingStep (next DecisionTreeStep in the tree)
 *
 * @package DNADesign\SilverStripeElementalDecisionTree\Model
 */
class ElementDecisionTree extends BaseElement
{
    private static string $title = 'Decision Tree';

    private static string $class_description = 'Display a decision tree with questions and results';

    private static bool $enable_title_in_template = true;

    private static string $icon = 'font-icon-flow-tree';

    private static array $db = [
        'Introduction' => 'HTMLText',
    ];

    private static array $has_one = [
        'FirstStep' => DecisionTreeStep::class,
    ];

    private static string $table_name = 'ElementDecisionTree';

    private static bool $inline_editable = false;

    /**
     * Returns the human-readable type of this element.
     *
     * Used in the Elemental module's element type selector and management interface.
     *
     * @return string Element type identifier
     */
    public function getType()
    {
        return 'Decision Tree';
    }

    /**
     * Builds the CMS editing form for this decision tree element.
     *
     * Provides fields for:
     * - Introduction text (displayed before the tree)
     * - First step selection/creation (entry point of the tree)
     * - Tree preview (visual representation of the tree structure)
     *
     * The form dynamically changes based on whether the element has been saved:
     * - Unsaved: Shows instructional message to save first
     * - Saved: Shows step selector and tree preview
     *
     * @return FieldList CMS form fields
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Remove the raw FirstStepID field - we'll replace it with a better selector
        $fields->removeByName('FirstStepID');

        // Configure the introduction text field - allow multiple lines
        $introduction = $fields->dataFieldByName('Introduction');
        $introduction->setRows(4);

        // Only show tree configuration if the element has been saved
        if ($this->IsInDB()) {
            // Create a custom field for selecting/creating the first step
            // Includes dropdown for existing steps and button to create new
            $stepSelector = HasOneSelectOrCreateField::create(
                $this,
                'FirstStep',
                'First Step',
                DecisionTreeStep::get_initial_steps()->map(),
                $this->FirstStep(),
                $this
            );

            $fields->addFieldToTab('Root.Main', $stepSelector);

            // Add a preview panel showing the entire tree structure
            // This helps editors visualize the decision tree while editing
            $fields->addFieldToTab('Root.Tree', DecisionTreeStepPreview::create('Tree', $this->FirstStep()));
        } else {
            // For unsaved elements, show helpful message
            $info = LiteralField::create('info', sprintf(
                '<p class="message info notice">%s</p>',
                'Save this decision tree in order to add the first step.'
            ));

            $fields->addFieldToTab('Root.Main', $info);
        }

        return $fields;
    }

    /**
     * Generates a CMS edit link to the first step of this tree.
     *
     * Used in templates and admin interfaces to provide quick navigation
     * to editing the root question of the tree.
     *
     * Returns null if:
     * - The element hasn't been saved yet
     * - No first step has been assigned
     * - The element isn't attached to a page
     *
     * The link navigates through the CMS hierarchy:
     * CMS Page Edit -> ElementalArea Item -> FirstStep Item Edit
     *
     * @return ?string URL to edit the first step, or null if unavailable
     */
    public function CMSEditFirstStepLink(): ?string
    {
        $page = $this->getPage();
        $firstStep = $this->FirstStep();

        // Must have both a parent page and a first step to generate a link
        if (!$page || !$page->exists() || !$firstStep->exists()) {
            return null;
        }

        // Build the edit link through the CMS hierarchy
        return Controller::join_links(
            singleton(CMSPageEditController::class)->Link('EditForm'),
            $page->ID,
            'field/ElementalArea/item/',
            $this->ID,
            'ItemEditForm/field/FirstStep/item',
            $this->FirstStep()->ID
        );
    }

}
