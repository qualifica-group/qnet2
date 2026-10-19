/**
 * Absence types catalogue (spec 0278). Satellite file to keep `en.ts` within the size limits.
 * Root key `absenceTypes.*`.
 */

export const absenceTypes = {
  forbidden: 'You do not have permission to view absence types.',
  columns: {
    name: 'Name',
    deducts_balance: 'Deducts a balance',
    requires_approval: 'Requires approval',
    is_active: 'Active',
    sort_order: 'Order',
  },
  detail: {
    loadError: 'Unable to load the absence type. Please try again.',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newAbsenceType: 'New absence type',
    createTitle: 'Create absence type',
    createSubtitle: 'Add a new absence type to the catalogue.',
    name: 'Name',
    deductsBalance: 'Deducts a balance',
    deductsBalanceHint: "When on, requests of this type reduce the employee's yearly balance.",
    requiresApproval: 'Requires approval',
    isActive: 'Active',
    sortOrder: 'Order',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Absence type created successfully.',
    updated: 'Absence type updated successfully.',
    deleted: 'Absence type deleted successfully.',
    nameRequired: 'The name is required.',
    nameMax: 'The name can be at most 100 characters.',
    sortOrderInvalid: 'The order must be an integer greater than or equal to 0.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the absence type. Please try again.',
    deleteForbidden: 'You cannot delete this absence type.',
    deleteInUse: 'Cannot delete: the type is used by at least one request. Deactivate it instead.',
    sections: {
      identity: {
        title: 'Details',
      },
    },
  },
}
