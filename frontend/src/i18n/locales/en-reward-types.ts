/**
 * Vouchers, Rewards and Incentives domain (spec 0058). Sibling file so
 * `en.ts` stays within the engineering size limits (see
 * `.claude/rules/engineering.md` §6). Pure registry of reward TYPES: only
 * `name` and `color` (palette token), no "system status" layer (no
 * `group`/`sort_order`/reorder, unlike the `opportunity-statuses` template).
 */

export const rewardTypes = {
  title: 'Vouchers, Rewards and Incentives',
  subtitle: 'Browse, filter and manage the voucher, reward or incentive types.',
  forbidden: "You don't have permission to view reward types.",
  columns: {
    name: 'Name',
    color: 'Color',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  advancedFilters: {
    name: 'Name',
    createdRange: 'Created at',
    updatedRange: 'Updated at',
  },
  detail: {
    title: 'Reward type details',
    subtitle: 'Details of the selected record, editable in place.',
    loadError: 'Unable to load the reward type. Please try again.',
    color: 'Color',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newRewardType: 'New reward type',
    createTitle: 'Create reward type',
    createSubtitle: 'Add a new voucher, reward or incentive type.',
    name: 'Name',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    color: 'Color',
    colorRequired: 'Color is required.',
    colorMax: 'Color must be at most 32 characters.',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Reward type created successfully.',
    updated: 'Reward type updated successfully.',
    deleted: 'Reward type deleted successfully.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the reward type. Please try again.',
    deleteInUseFallback: 'This reward type is in use and cannot be deleted.',
    leaveConfirm: {
      title: 'Leave without saving?',
      description: 'The record has not been created yet: what you entered will be lost.',
      confirm: 'Leave without saving',
      cancel: 'Keep editing',
    },
    sections: {
      identity: {
        title: 'Details',
      },
    },
  },
}
