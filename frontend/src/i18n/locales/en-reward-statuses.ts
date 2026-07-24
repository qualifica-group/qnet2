/**
 * Reward statuses domain (spec 0060). Sibling file so `en.ts` stays within
 * the engineering size limits (see `.claude/rules/engineering.md` §6).
 * Reference table describing the STATES applicable to linked rewards
 * (`rewards`), cloned from `opportunity-statuses` with no `group`, plus
 * `description` and `is_active`, and a single system row `pending`
 * ("In attesa").
 */

export const rewardStatuses = {
  title: 'Reward Statuses',
  subtitle: 'Browse, filter and manage the statuses used by linked rewards.',
  forbidden: "You don't have permission to view reward statuses.",
  columns: {
    name: 'Name',
    description: 'Description',
    color: 'Color',
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
    subtitle: 'Read-only view of the selected reward status.',
    loadError: 'Unable to load the reward status. Please try again.',
    description: 'Description',
    color: 'Color',
    sort_order: 'Order',
    is_active: 'Active',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newRewardStatus: 'New status',
    createTitle: 'Create reward status',
    createSubtitle: 'Add a new status for linked rewards.',
    editTitle: 'Edit reward status',
    editSubtitle: 'Update the selected reward status.',
    name: 'Name',
    description: 'Description',
    color: 'Color',
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
    colorMax: 'Color must be at most 32 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the reward status. Please try again.',
    deleteForbidden: 'You cannot delete this reward status.',
    deleteInUseFallback: 'This reward status is used by a linked reward and cannot be deleted.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, description, color and active/inactive state.',
      },
    },
    hints: {
      systemStatusLocked: "A system status's fields are fixed and cannot be changed.",
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
