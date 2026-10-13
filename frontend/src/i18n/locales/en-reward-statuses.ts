/**
 * Reward statuses domain (spec 0060). Sibling file so `en.ts` stays within
 * the engineering size limits (see `.claude/rules/engineering.md` §6).
 * Reference table describing the STATES applicable to linked rewards
 * (`rewards`), cloned from `opportunity-statuses` plus `description` and
 * `is_active`. Spec 0073 adds the three-phase `group` and brings the system
 * rows to three ("In attesa", "Approvato", "Negato").
 */

export const rewardStatuses = {
  title: 'Reward Statuses',
  subtitle: 'Browse, filter and manage the statuses used by linked rewards.',
  forbidden: "You don't have permission to view reward statuses.",
  columns: {
    name: 'Name',
    description: 'Description',
    color: 'Color',
    group: 'Group',
    sort_order: 'Order',
    is_active: 'Active',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  advancedFilters: {
    name: 'Name',
    isActive: 'Active',
    sortOrderRange: 'Order',
    createdRange: 'Created at',
    updatedRange: 'Updated at',
  },
  detail: {
    title: 'Reward status details',
    subtitle: 'Details of the selected record, editable in place.',
    loadError: 'Unable to load the reward status. Please try again.',
    description: 'Description',
    color: 'Color',
    group: 'Group',
    sort_order: 'Order',
    is_active: 'Active',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newRewardStatus: 'New status',
    createTitle: 'Create reward status',
    createSubtitle: 'Add a new status for linked rewards.',
    name: 'Name',
    description: 'Description',
    color: 'Color',
    group: {
      label: 'Group',
      pending: 'Pending',
      closed_won: 'Approved',
      closed_lost: 'Denied',
    },
    isActive: 'Active',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Reward status created successfully.',
    updated: 'Reward status updated successfully.',
    deleted: 'Reward status deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    descriptionMax: 'Description must be at most 500 characters.',
    colorRequired: 'Color is required.',
    groupRequired: 'Group is required.',
    colorMax: 'Color must be at most 32 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the reward status. Please try again.',
    deleteForbidden: 'You cannot delete this reward status.',
    deleteInUseFallback: 'This reward status is used by a linked reward and cannot be deleted.',
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
  reorder: {
    openButton: 'Reorder',
    title: 'Reorder statuses',
    subtitle: 'Drag the custom statuses to reorder them. "In attesa" always stays first.',
    dragHandleLabel: 'Drag to reorder',
    loadError: 'Unable to load the statuses. Please try again.',
    saved: 'Order updated successfully.',
    forbidden: 'You cannot reorder these statuses.',
    genericError: 'Unable to update the order. Please try again.',
  },
}
