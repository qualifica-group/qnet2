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
    nature: 'Nature',
    color: 'Color',
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
    sortOrder: 'Order',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newCostType: 'New cost type',
    createTitle: 'Create cost type',
    createSubtitle: 'Add a new cost type.',
    name: 'Name',
    description: 'Description',
    nature: 'Nature',
    color: 'Color',
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
    natureRequired: 'Nature is required.',
    colorRequired: 'Color is required.',
    colorInvalid: 'Pick one of the available colors.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the cost type. Please try again.',
    deleteForbidden: 'You cannot delete this cost type.',
    deleteInUse: 'Cannot delete: the cost type is used by purchase order lines.',
    hints: {
      nature:
        'Direct: chargeable to a work order. Indirect: production cost, allocated. General: structure and overhead.',
    },
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, description, nature, color and status.',
      },
    },
  },
  nature: {
    direct: 'Direct',
    indirect: 'Indirect',
    general: 'General',
  },
  reorder: {
    openButton: 'Reorder',
    title: 'Reorder cost types',
    subtitle: 'Drag the rows to change their order. The order applies to the table and to every dropdown.',
    dragHandleLabel: 'Drag to reorder',
    loadError: 'Unable to load the list. Please retry.',
    saved: 'Order updated successfully.',
    forbidden: 'You cannot reorder these rows.',
    genericError: 'Unable to update the order. Please retry.',
    inactiveBadge: 'Inactive',
  },
}
