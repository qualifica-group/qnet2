/**
 * Quote statuses domain (spec 0065). Sibling file so `en.ts` stays within
 * the engineering size limits (see `.claude/rules/engineering.md` §6).
 * Clone of `opportunity-statuses` (D-2), with the delete-guard message
 * adjusted to Quotes. System statuses are "Bozza"/"Accettata"/"Rifiutata"
 * and a fixed 3-value `group` enum (open/pending/closed), plus a drag & drop
 * reorder sheet for the custom rows.
 */

export const quoteStatuses = {
  title: 'Quote statuses',
  subtitle: 'Browse, filter and manage the statuses used by quotes.',
  forbidden: "You don't have permission to view quote statuses.",
  columns: {
    name: 'Name',
    color: 'Color',
    sort_order: 'Order',
    group: 'Group',
    created_at: 'Created at',
  },
  advancedFilters: {
    name: 'Name',
    sortOrderRange: 'Order',
    createdRange: 'Created at',
  },
  detail: {
    title: 'Quote status details',
    subtitle: 'Read-only view of the selected quote status.',
    loadError: 'Unable to load the quote status. Please try again.',
    color: 'Color',
    sort_order: 'Order',
    group: 'Group',
    created_at: 'Created at',
  },
  form: {
    newQuoteStatus: 'New status',
    createTitle: 'Create quote status',
    createSubtitle: 'Add a new status for quotes.',
    editTitle: 'Edit quote status',
    editSubtitle: 'Update the selected quote status.',
    name: 'Name',
    color: 'Color',
    group: {
      label: 'Group',
      open: 'Open',
      pending: 'Pending',
      closed: 'Closed',
    },
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Quote status created successfully.',
    updated: 'Quote status updated successfully.',
    deleted: 'Quote status deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    colorMax: 'Color must be at most 32 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the quote status. Please try again.',
    deleteForbidden: 'You cannot delete this quote status.',
    deleteInUseFallback: 'This quote status is used by a quote and cannot be deleted.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, color and group of the status.',
      },
    },
    hints: {
      systemStatusGroup: "A system status's group is fixed and cannot be changed.",
    },
  },
  reorder: {
    openButton: 'Reorder',
    title: 'Reorder statuses',
    subtitle: 'Drag the custom statuses to reorder them. "Bozza" stays first and "Rifiutata" stays last.',
    dragHandleLabel: 'Drag to reorder',
    loadError: 'Unable to load the statuses. Please try again.',
    saved: 'Order updated successfully.',
    forbidden: 'You cannot reorder these statuses.',
    genericError: 'Unable to update the order. Please try again.',
  },
}
