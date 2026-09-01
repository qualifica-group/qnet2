/**
 * Units of measure domain (spec 0088). Sibling file so `en.ts` stays within
 * the engineering size limits (see `.claude/rules/engineering.md` §6).
 * Lookup table of the units of measure selectable on Products, congelated
 * onto Offer/Quote lines when they are saved (D-5). Follows `vat-rates` for
 * leanness (no `is_active`, no `sort_order`, no reorder) and
 * `payment-methods` for `code` (unique, immutable after create, D-1).
 */

export const unitsOfMeasure = {
  title: 'Units of Measure',
  subtitle: 'Browse, filter and manage the units of measure used by your catalogue.',
  forbidden: "You don't have permission to view units of measure.",
  columns: {
    name: 'Name',
    symbol: 'Symbol',
    code: 'Code',
    description: 'Description',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  detail: {
    title: 'Unit of measure details',
    subtitle: 'Read-only view of the selected unit of measure.',
    loadError: 'Unable to load the unit of measure. Please try again.',
    symbol: 'Symbol',
    description: 'Description',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newUnitOfMeasure: 'New unit of measure',
    createTitle: 'Create unit of measure',
    createSubtitle: 'Add a new unit of measure.',
    editTitle: 'Edit unit of measure',
    editSubtitle: 'Update the selected unit of measure.',
    name: 'Name',
    symbol: 'Symbol',
    code: 'Code',
    description: 'Description',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Unit of measure created successfully.',
    updated: 'Unit of measure updated successfully.',
    deleted: 'Unit of measure deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    symbolRequired: 'Symbol is required.',
    symbolMax: 'Symbol must be at most 16 characters.',
    codeRequired: 'Code is required.',
    codeMax: 'Code must be at most 64 characters.',
    codeInvalid:
      'Code must start with a lowercase letter and contain only lowercase letters, digits and underscores.',
    descriptionMax: 'Description must be at most 500 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the unit of measure. Please try again.',
    deleteForbidden: 'You cannot delete this unit of measure.',
    deleteInUse: 'Cannot delete: the unit of measure is used by a product or a quote line.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, symbol, code and description.',
      },
    },
    hints: {
      codeLocked: 'The code cannot be changed after creation.',
    },
  },
}
