<?php

namespace DNADesign\SilverStripeElementalDecisionTree\Extensions;

use DNADesign\SilverStripeElementalDecisionTree\Model\DecisionTreeAnswer;
use DNADesign\SilverStripeElementalDecisionTree\Model\DecisionTreeStep;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\Requirements;

/**
 * ElementDecisionTreeController Extension
 *
 * Extends any page controller that contains a DecisionTree element
 * with decision tree navigation logic and frontend handling.
 *
 * Responsibilities:
 * - Load frontend JavaScript and CSS requirements
 * - Handle AJAX requests for step navigation
 * - Track user pathway through the tree via URL parameters
 * - Generate next step HTML and URLs for dynamic navigation
 *
 * Configuration options:
 * - include_default_js: Include decision-tree.src.js (true by default)
 * - include_default_css: Load CSS (true by default, disable in tests)
 *
 * @package DNADesign\SilverStripeElementalDecisionTree\Extensions
 */
class ElementDecisionTreeController extends Extension
{
    use Configurable;

    /**
     * Whether or not to include the default CSS for the decision tree.
     *
     * @config
     */
    private static bool $include_default_css = true;

    /**
     * Whether or not to include the javascript included in the module.
     *
     * @config
     */
    private static bool $include_default_js = true;

    private static array $allowed_actions = [
        'getNextStepForAnswer',
    ];

    /**
     * Initialize frontend requirements for decision tree.
     *
     * Called on page init to load:
     * - Custom CSS for accessibility (focus states) - if enabled
     * - Decision tree JavaScript (if configured)
     *
     * Requirements loading can be individually controlled:
     * - include_default_css: Load accessibility CSS (enabled by default)
     * - include_default_js: Load decision tree JS (enabled by default)
     *
     * Note: jQuery is not required as the decision tree uses vanilla JavaScript.
     */
    public function onAfterInit(): void
    {
        // Load custom CSS for better accessibility (if enabled)
        // Ensures radio buttons have proper focus indicators
        if ($this->config()->get('include_default_css')) {
            Requirements::customCSS(
                <<<CSS
                    .decisiontree .step-options input[type="radio"]:focus + label,
                    .decisiontree .step-options input[type="radio"]:focus-visible + label {
                        outline: 2px solid currentColor;
                        outline-offset: 2px;
                    }
                CSS
            );
        }

        // Load the main decision tree JavaScript (vanilla JS, no jQuery required)
        if (self::config()->get('include_default_js')) {
            Requirements::javascript(
                'dnadesign/silverstripe-elemental-decisiontree:javascript/decision-tree.src.js',
                ['defer' => true]
            );
        }
    }

    /**
     * Handles AJAX requests for navigating to the next step in the decision tree.
     *
     * Expected POST parameter: 'stepanswerid' (ID of the selected answer)
     *
     * Process:
     * 1. Validates that an answer ID was submitted
     * 2. Loads the answer and its resulting step
     * 3. Renders the next step HTML
     * 4. Builds a new URL with the pathway to track the user's selections
     * 5. Returns JSON (for AJAX) or HTML (for fallback)
     *
     * Returns HTTP 404 with error message if:
     * - No answer ID provided
     * - Answer doesn't exist
     * - Resulting step doesn't exist
     *
     * Performance note:
     * - Renders full step HTML including all nested elements
     * - Could be optimized with partial rendering for large trees
     *
     * @return null|bool|string|DBHTMLText JSON string for AJAX or HTML for standard request
     */
    public function getNextStepForAnswer(): null|bool|string|DBHTMLText
    {
        // Get the selected answer ID from the submitted form
        $answerID = $this->owner->getRequest()->postVar('stepanswerid');

        // Validate that an answer was selected
        if (!$answerID) {
            return $this->owner->httpError(404, 'No answer ID found.');
        }

        // Load the answer from the database
        $answer = DecisionTreeAnswer::get()->byID($answerID);

        // Validate the answer exists
        if (!$answer || !$answer->exists()) {
            return $this->owner->httpError(
                404,
                $this->renderError('An error has occurred, please reload the page and try again!')
            );
        }

        // Get the step we should navigate to
        $nextStep = $answer->ResultingStep();

        // Validate the next step exists
        if (!$nextStep || !$nextStep->exists()) {
            return $this->owner->httpError(
                404,
                $this->renderError('An error has occurred, please reload the page and try again!')
            );
        }

        // Render the next step's HTML
        $html = $this->owner->customise(ArrayData::create([
            'Step' => $nextStep,
            'Controller' => $this->owner,
        ]))->renderWith('DNADesign\SilverStripeElementalDecisionTree\Model\DecisionTreeStep');

        // Get the pathway of answers that led to this step
        // Used to reconstruct the user's selections if they reload the page
        $pathway = $nextStep->getAnswerPathway();

        // Build the URL for the next step, including pathway parameter
        // Format: /page-url/?decisionpathway=answer1,answer2,answer3
        $nextURL = Controller::join_links(
            $this->owner->AbsoluteLink(),
            '?decisionpathway=' . implode(',', $pathway)
        );

        // Return JSON for AJAX requests or HTML for standard requests
        if ($this->owner->getRequest()->isAjax()) {
            $data = [
                'html' => $html->forTemplate(),
                'nexturl' => $nextURL,
            ];

            return json_encode($data);
        }

        return $html;
    }

    /**
     * Extracts and returns the decision pathway from the URL parameters.
     *
     * The pathway is a comma-separated list of answer IDs that represent
     * the user's selections through the tree.
     *
     * Format in URL: ?decisionpathway=1,5,12,8
     *
     * Used to:
     * - Pre-select answers when the page is reloaded
     * - Show the user's current position in the tree
     * - Resume navigation from saved URL
     *
     * @return ?array Array of answer IDs, or null if no pathway parameter
     */
    public function getInitialPathway(): ?array
    {
        // Get the pathway parameter from URL query string
        $ids = $this->owner->getRequest()->getVar('decisionpathway');

        // Parse comma-separated list into array
        if ($ids && is_string($ids)) {
            return explode(',', $ids);
        }

        return null;
    }

    /**
     * Checks if a specific answer should be pre-selected in the form.
     *
     * An answer is selected if:
     * - A pathway was provided in the URL
     * - The answer ID appears in that pathway
     *
     * This allows answers to be checked/selected when the page loads,
     * recreating the user's previous selections.
     *
     * @param mixed $answerID ID of the answer to check
     * @return bool True if this answer is in the current pathway
     */
    public function getIsAnswerSelected($answerID): bool
    {
        // Get the current pathway from URL
        if ($pathway = $this->getInitialPathway()) {
            return in_array($answerID, $pathway);
        }

        return false;
    }

    /**
     * Gets the next step that should be displayed based on previous selections.
     *
     * Process:
     * 1. Load the specified step
     * 2. Check all its answers
     * 3. Find which answer matches the current pathway
     * 4. Return the resulting step for that answer
     *
     * Used in templates to show the user's current position in the tree.
     * This allows resuming from a saved URL with the full tree expanded.
     *
     * @param mixed $stepID ID of the current question step
     * @return ?DecisionTreeStep The next step to display, or null if not found
     */
    public function getNextStepFromSelectedAnswer($stepID): ?DecisionTreeStep
    {
        // Load the current question step
        $step = DecisionTreeStep::get()->byID($stepID);

        if ($step->exists()) {
            // Check each answer to see if it was selected
            foreach ($step->Answers() as $answer) {
                // Is this answer in the user's pathway?
                if ($this->getIsAnswerSelected($answer->ID)) {
                    // Get the step this answer leads to
                    if ($nextStep = $answer->ResultingStep()) {
                        return $nextStep;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Renders an error message in the tree's HTML style.
     *
     * Used to show errors within the tree UI instead of breaking
     * the page layout or showing generic error pages.
     *
     * Returns HTML formatted as a step element with error styling.
     *
     * Performance note:
     * - Could be moved to a template file for better maintainability
     * - Currently hardcoded inline for simplicity
     *
     * @param string $message Error message to display
     * @return string HTML formatted error message
     */
    protected function renderError(string $message = ''): string
    {
        return sprintf(
            '<div class="step step--error">
                <hr class="partial_green_border">
                <div class="step-form">
                    <span class="step-title">Sorry!</span>
                    <span class="step-content"><p>%s</p></span>
                </div>
            </div>',
            $message
        );
    }
}
