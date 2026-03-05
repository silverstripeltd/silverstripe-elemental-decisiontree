<?php

namespace DNADesign\SilverStripeElementalDecisionTree\Services;

use DNADesign\SilverStripeElementalDecisionTree\Model\ElementDecisionTree;
use SilverStripe\Security\Member;

/**
 * Handles permission checks for DecisionTree models.
 *
 * This service centralizes permission logic to ensure consistency
 * across DecisionTreeStep and DecisionTreeAnswer models.
 */
class DecisionTreePermissionService
{
    /**
     * Check if a member can create decision tree elements.
     *
     * @param Member|null $member
     * @param array $context
     * @return bool
     */
    public function canCreate(?Member $member = null, array $context = []): bool
    {
        return singleton(ElementDecisionTree::class)->canCreate($member, $context);
    }

    /**
     * Check if a member can view decision tree elements.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canView(?Member $member = null): bool
    {
        return singleton(ElementDecisionTree::class)->canView($member);
    }

    /**
     * Check if a member can edit decision tree elements.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canEdit(?Member $member = null): bool
    {
        return singleton(ElementDecisionTree::class)->canEdit($member);
    }

    /**
     * Check if a member can delete decision tree elements.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canDelete(?Member $member = null): bool
    {
        return singleton(ElementDecisionTree::class)->canDelete($member);
    }
}

