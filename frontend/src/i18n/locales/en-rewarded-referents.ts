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
    pendingRewardsCount: 'Pending rewards',
    approvedRewardsCount: 'Approved rewards',
    lastAssignedAt: 'Last assigned',
  },
  advancedFilters: {
    rewardType: 'Reward type',
    opportunity: 'Opportunity',
    quote: 'Quote',
    opportunityStatus: 'Commercial status',
    workflowStatus: 'Workflow status',
    operator: 'Operator',
    assignedAt: 'Assignment date',
    rewardStatus: 'Reward status',
  },
  detail: {
    loadError: "Unable to load this referent's rewards. Please try again.",
    empty: 'No rewards found for this referent.',
    assignedAt: 'Assigned on',
    sourceRemoved: 'Origin no longer available',
    sourceTypes: {
      opportunity: 'Opportunity',
      quote: 'Quote',
    },
    client: 'Client',
    categories: 'Product categories',
    commercialStatus: 'Commercial status',
    opportunityStatus: 'Opportunity status',
    workflowStatus: 'Workflow status',
    operator: 'Operator',
    status: 'Status',
    statusPlaceholder: 'Select a status',
    statusSearchPlaceholder: 'Search a status…',
    statusEmpty: 'No statuses found',
    statusError: 'Unable to load statuses',
    statusClearLabel: 'Remove status',
    statusRetry: 'Retry',
  },
}
