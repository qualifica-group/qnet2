/**
 * Contract statuses domain (spec 0072). Sibling file so `en.ts` stays within
 * the engineering size limits (see `.claude/rules/engineering.md` §6).
 * Closest sibling is `quote-statuses` (same status configurator shape),
 * widened with `description`, `is_active` and the exclusive `is_default`
 * (BR-5, same invariant as `DocumentLayoutDefaultManager`). The 7 seeded rows
 * (D-2) include 4 system rows: "Da validare" (HEAD) and the
 * "Sospeso"/"Annullato"/"Disdetto" trio (TAIL); the `group` enum is dedicated
 * (`ContractStatusGroup`, D-5) with the closed phase carrying its outcome
 * (open/pending/closed_won/closed_lost).
 */

export const contractStatuses = {
  title: 'Contract statuses',
  subtitle: 'Browse, filter and manage the statuses used by contracts.',
  forbidden: "You don't have permission to view contract statuses.",
  columns: {
    name: 'Name',
    description: 'Description',
    color: 'Color',
    sort_order: 'Order',
    group: 'Group',
    is_active: 'Active',
    is_default: 'Default',
    created_at: 'Created at',
  },
  advancedFilters: {
    name: 'Name',
    isActive: 'Active',
    isDefault: 'Default',
    sortOrderRange: 'Order',
    createdRange: 'Created at',
  },
  detail: {
    title: 'Contract status details',
    subtitle: 'Details of the selected record, editable in place.',
    loadError: 'Unable to load the contract status. Please try again.',
    description: 'Description',
    color: 'Color',
    sort_order: 'Order',
    group: 'Group',
    isActive: 'Active',
    isDefault: 'Default',
    created_at: 'Created at',
  },
  form: {
    newContractStatus: 'New status',
    createTitle: 'Create contract status',
    createSubtitle: 'Add a new status for contracts.',
    name: 'Name',
    description: 'Description',
    color: 'Color',
    group: {
      label: 'Group',
      open: 'Open',
      pending: 'Pending',
      closed_won: 'Closed (positive)',
      closed_lost: 'Closed (negative)',
    },
    isActive: 'Active',
    isDefault: 'Default',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Contract status created successfully.',
    updated: 'Contract status updated successfully.',
    deleted: 'Contract status deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    descriptionMax: 'Description must be at most 500 characters.',
    colorMax: 'Color must be at most 32 characters.',
    defaultRequiresActive: 'A default status must be active.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the contract status. Please try again.',
    deleteForbidden: 'You cannot delete this contract status.',
    deleteInUseFallback: 'This contract status is used by a contract and cannot be deleted.',
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
    subtitle: 'Drag the custom statuses to reorder them. "Da validare" stays first and "Sospeso"/"Annullato"/"Disdetto" stay last.',
    dragHandleLabel: 'Drag to reorder',
    loadError: 'Unable to load the statuses. Please try again.',
    saved: 'Order updated successfully.',
    forbidden: 'You cannot reorder these statuses.',
    genericError: 'Unable to update the order. Please try again.',
  },
}
