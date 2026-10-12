/**
 * Cost types domain (spec 0210). Sibling file so `en.ts` stays within the
 * engineering size limits. Configurable lookup chosen on every purchase
 * order line.
 */

export const costTypes = {
  title: 'Cost types',
  subtitle: 'Browse, filter and manage the cost types used on purchase order lines.',
  forbidden: "You don't have permission to view cost types.",
  columns: {
    name: 'Name',
    description: 'Description',
    sort_order: 'Order',
    is_active: 'Active',
    lines_count: 'Order lines',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  detail: {
    title: 'Cost type details',
    subtitle: 'Read-only view of the selected cost type.',
    loadError: 'Unable to load the cost type. Please try again.',
    description: 'Description',
    sortOrder: 'Order',
    status: 'Status',
    active: 'Active',
    inactive: 'Inactive',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newCostType: 'New cost type',
    createTitle: 'Create cost type',
    createSubtitle: 'Add a new cost type.',
    editTitle: 'Edit cost type',
    editSubtitle: 'Update the selected cost type.',
    name: 'Name',
    description: 'Description',
    sortOrder: 'Order',
    isActive: 'Active',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Cost type created successfully.',
    updated: 'Cost type updated successfully.',
    deleted: 'Cost type deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 100 characters.',
    descriptionMax: 'Description must be at most 500 characters.',
    sortOrderInvalid: 'Order must be a whole number, zero or greater.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the cost type. Please try again.',
    deleteForbidden: 'You cannot delete this cost type.',
    deleteInUse: 'Cannot delete: the cost type is used by purchase order lines.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, description, order and status.',
      },
    },
  },
}
