/**
 * Rewarded Referents domain (spec 0059). Read-only aggregated module: one row
 * per Referent with at least one reward assignment, expandable (master/detail)
 * row with each reward's detail. Extracted to a sibling file to keep `en.ts`
 * within the engineering size limits (see `.claude/rules/engineering.md` §6).
 */

export const rewardedReferents = {
  title: 'Rewarded Referents',
  subtitle: 'Referents with at least one voucher, reward or incentive assigned.',
  forbidden: "You don't have permission to view rewarded referents.",
  columns: {
    name: 'Name',
    registries: 'Linked registries',
    email: 'Email',
    phone: 'Phone',
    rewardsCount: 'Total rewards',
    activeRewardsCount: 'Active rewards',
    completedRewardsCount: 'Completed rewards',
    lastAssignedAt: 'Last assigned',
  },
  advancedFilters: {
    rewardType: 'Reward type',
    opportunity: 'Opportunity',
    opportunityStatus: 'Commercial status',
    workflowStatus: 'Workflow status',
    operator: 'Operator',
    assignedAt: 'Assignment date',
  },
  detail: {
    loadError: "Unable to load this referent's rewards. Please try again.",
    empty: 'No rewards found for this referent.',
    assignedAt: 'Assigned on',
    sourceRemoved: 'Origin no longer available',
    client: 'Client',
    categories: 'Product categories',
    commercialStatus: 'Commercial status',
    workflowStatus: 'Workflow status',
    operator: 'Operator',
  },
}
