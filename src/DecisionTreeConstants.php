<?php

namespace DNADesign\SilverStripeElementalDecisionTree;

/**
 * Constants for DecisionTree module.
 * Centralized definitions to avoid magic strings and enable easy refactoring.
 */
class DecisionTreeConstants
{
    /**
     * Step type: Question
     */
    public const STEP_TYPE_QUESTION = 'Question';

    /**
     * Step type: Result
     */
    public const STEP_TYPE_RESULT = 'Result';

    /**
     * Available step types
     */
    public const STEP_TYPES = [
        self::STEP_TYPE_QUESTION,
        self::STEP_TYPE_RESULT,
    ];

    /**
     * Relationship field names
     */
    public const FIELD_FIRST_STEP = 'FirstStep';
    public const FIELD_RESULTING_STEP = 'ResultingStep';
    public const FIELD_QUESTION = 'Question';
    public const FIELD_ANSWERS = 'Answers';
    public const FIELD_INTRODUCTION = 'Introduction';
    public const FIELD_TYPE = 'Type';
    public const FIELD_HIDE_TITLE = 'HideTitle';
    public const FIELD_CONTENT = 'Content';
    public const FIELD_TITLE = 'Title';

    /**
     * CMS-related field names
     */
    public const FIELD_PARENT_ANSWER = 'ParentAnswer';
    public const FIELD_PARENT_ELEMENT = 'ParentElement';

    /**
     * Form input names
     */
    public const FORM_ANSWER_ID = 'stepanswerid';
    public const FORM_PATHWAY = 'decisionpathway';

    /**
     * Tab names in CMS
     */
    public const TAB_MAIN = 'Root.Main';
    public const TAB_TREE = 'Root.Tree';

    /**
     * CSS/HTML classes
     */
    public const CLASS_STEP_OPTIONS = 'decisiontree-option';
    public const CLASS_STEP_ERROR = 'step--error';
    public const CLASS_STEP = 'step';

    /**
     * Default values
     */
    public const DEFAULT_RESULT_TITLE = 'Our recommendation';
    public const DEFAULT_EMPTY_STEPS = 'Select a step';

    /**
     * CMS relationship field names for link construction
     */
    public const CMS_FIELD_FIRST_STEP = 'FirstStep';
    public const CMS_FIELD_RESULTING_STEP = 'ResultingStep';
}

