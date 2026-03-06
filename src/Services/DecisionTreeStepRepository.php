<?php

namespace DNADesign\SilverStripeElementalDecisionTree\Services;

use DNADesign\SilverStripeElementalDecisionTree\Model\DecisionTreeStep;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\SS_List;

/**
 * Repository for DecisionTreeStep queries.
 *
 * Centralizes query logic to optimize database access
 * and provide a cleaner API for step retrieval.
 */
class DecisionTreeStepRepository
{
    /**
     * Get all orphaned steps (steps not connected to tree).
     *
     * Optimized approach:
     * - Gets all FirstStepIDs from elements
     * - Gets all ResultingStepIDs from answers
     * - Returns steps not in either list
     *
     * @return SS_List
     */
    public function getOrphans(): SS_List
    {
        // Get all steps that ARE in the tree
        $connectedStepIds = [];

        // Add FirstSteps from elements
        foreach (\DNADesign\SilverStripeElementalDecisionTree\Model\ElementDecisionTree::get() as $element) {
            if ($element->FirstStepID) {
                $connectedStepIds[] = $element->FirstStepID;
            }
        }

        // Add ResultingSteps from answers
        foreach (\DNADesign\SilverStripeElementalDecisionTree\Model\DecisionTreeAnswer::get() as $answer) {
            if ($answer->ResultingStepID) {
                $connectedStepIds[] = $answer->ResultingStepID;
            }
        }

        // Get all steps
        $allSteps = DecisionTreeStep::get();

        if (empty($connectedStepIds)) {
            return $allSteps;
        }

        // Return steps NOT in the connected list
        return $allSteps->exclude('ID', array_unique($connectedStepIds));
    }

    /**
     * Get initial steps (not belonging to an answer, not of result type).
     *
     * @return SS_List
     */
    public function getInitialSteps(): SS_List
    {
        // Get all ResultingStepIDs from answers
        $answerResultIds = \DNADesign\SilverStripeElementalDecisionTree\Model\DecisionTreeAnswer::get()
            ->column('ResultingStepID');

        // Filter empty values from the array
        $answerResultIds = array_filter($answerResultIds);

        // Get all steps that are not results of answers and not result type
        $steps = DecisionTreeStep::get()
            ->exclude('Type', 'Result');

        // Only exclude answer results if there are any
        if (!empty($answerResultIds)) {
            $steps = $steps->exclude('ID', $answerResultIds);
        }

        return $steps;
    }

    /**
     * Get a step with eager-loaded relationships.
     *
     * @param int $id
     * @return DecisionTreeStep|null
     */
    public function getById(int $id): ?DecisionTreeStep
    {
        return DecisionTreeStep::get()->byID($id);
    }

    /**
     * Get all question-type steps.
     *
     * @return SS_List
     */
    public function getQuestions(): SS_List
    {
        return DecisionTreeStep::get()->filter('Type', 'Question');
    }

    /**
     * Get all result-type steps.
     *
     * @return SS_List
     */
    public function getResults(): SS_List
    {
        return DecisionTreeStep::get()->filter('Type', 'Result');
    }
}

