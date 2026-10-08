/**
 * Work order payment statuses domain (spec 0201). Sibling file to keep
 * `en.ts` within size limits. Lookup of the payment statuses of work order
 * lines: same shape as `rewardStatuses` without group/system rows, with the
 * "Can be delivered" flag.
 */

export const workOrderPaymentStatuses = {
  title: 'Work order payment statuses',
  subtitle: 'Browse, filter and manage the payment statuses of work order lines.',
  forbidden: "You don't have permission to view work order payment statuses.",
  columns: {
    name: 'Name',
    description: 'Description',
    color: 'Color',
    sort_order: 'Order',
    is_active: 'Active',
    allows_delivery: 'Can be delivered',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  advancedFilters: {
    name: 'Name',
    isActive: 'Active',
    allowsDelivery: 'Can be delivered',
    sortOrderRange: 'Order',
    createdRange: 'Created at',
    updatedRange: 'Updated at',
  },
  detail: {
    title: 'Payment status details',
    subtitle: 'Read-only view of the selected payment status.',
    loadError: 'Unable to load the payment status. Please try again.',
    description: 'Description',
    color: 'Color',
    sort_order: 'Order',
    is_active: 'Active',
    allows_delivery: 'Can be delivered',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newWorkOrderPaymentStatus: 'New status',
    createTitle: 'Create payment status',
    createSubtitle: 'Add a new payment status for work order lines.',
    editTitle: 'Edit payment status',
    editSubtitle: 'Update the selected payment status.',
    name: 'Name',
    description: 'Description',
    color: 'Color',
    isActive: 'Active',
    allowsDelivery: 'Can be delivered',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Payment status created successfully.',
    updated: 'Payment status updated successfully.',
    deleted: 'Payment status deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    descriptionMax: 'Description must be at most 500 characters.',
    colorRequired: 'Color is required.',
    colorMax: 'Color must be at most 32 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the payment status. Please try again.',
    deleteForbidden: 'You cannot delete this payment status.',
    deleteInUseFallback: 'This payment status is used by a work order line and cannot be deleted.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, description, color, active state and delivery.',
      },
    },
    hints: {
      allowsDelivery:
        'When a line moves to a status with this flag, the work order supervisors and participants are notified.',
    },
  },
  reorder: {
    openButton: 'Reorder',
    title: 'Reorder statuses',
    subtitle: 'Drag the statuses to reorder them.',
    dragHandleLabel: 'Drag to reorder',
    loadError: 'Unable to load the statuses. Please try again.',
    saved: 'Order updated successfully.',
    forbidden: 'You cannot reorder these statuses.',
    genericError: 'Unable to update the order. Please try again.',
  },
}
