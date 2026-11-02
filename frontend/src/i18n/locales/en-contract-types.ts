/**
 * Contract types catalogue (spec 0290). Satellite file to keep `en.ts` within the size limits.
 * Root key `contractTypes.*`.
 */

export const contractTypes = {
  forbidden: 'You do not have permission to view contract types.',
  columns: {
    name: 'Name',
    is_active: 'Active',
    sort_order: 'Order',
  },
  detail: {
    loadError: 'Unable to load the contract type. Please try again.',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newContractType: 'New contract type',
    createTitle: 'Create contract type',
    createSubtitle: 'Add a new contract type to the catalogue.',
    name: 'Name',
    isActive: 'Active',
    sortOrder: 'Order',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Contract type created successfully.',
    updated: 'Contract type updated successfully.',
    deleted: 'Contract type deleted successfully.',
    nameRequired: 'The name is required.',
    nameMax: 'The name can be at most 100 characters.',
    sortOrderInvalid: 'The order must be an integer greater than or equal to 0.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the contract type. Please try again.',
    deleteInUse: 'Cannot delete: the type is used by at least one contract condition. Deactivate it instead.',
    sections: {
      identity: {
        title: 'Details',
      },
    },
  },
}
