/**
 * Product typologies domain (spec 0099). Split into a sibling file to keep
 * `en.ts` within the size limits (see `.claude/rules/engineering.md` §6).
 * The catalogue of typologies selectable on Products and used by the Offer's
 * per-typology summary, where it is read LIVE through the product (D-5).
 * Mirrors `units-of-measure` for leanness (no `is_active`, no `sort_order`,
 * no reorder) and for `code` (unique, immutable after create, D-2).
 *
 * Note: "Typology" is distinct from "Type" (`products.product_type`), the
 * pre-existing enum this module leaves untouched (D-1).
 */

export const productTypologies = {
  title: 'Product Typologies',
  subtitle: 'Browse, filter and manage the typologies used by your product catalogue.',
  forbidden: 'You do not have permission to view product typologies.',
  columns: {
    name: 'Name',
    code: 'Code',
    description: 'Description',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  detail: {
    title: 'Product typology detail',
    subtitle: 'Read-only view of the selected product typology.',
    loadError: 'Unable to load the product typology. Please retry.',
    description: 'Description',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newProductTypology: 'New product typology',
    createTitle: 'Create product typology',
    createSubtitle: 'Add a new product typology.',
    editTitle: 'Edit product typology',
    editSubtitle: 'Update the selected product typology.',
    name: 'Name',
    code: 'Code',
    description: 'Description',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Product typology created successfully.',
    updated: 'Product typology updated successfully.',
    deleted: 'Product typology deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name may contain at most 191 characters.',
    codeRequired: 'Code is required.',
    codeMax: 'Code may contain at most 64 characters.',
    codeInvalid:
      'Code must start with a lowercase letter and contain only lowercase letters, digits and underscores.',
    descriptionMax: 'Description may contain at most 500 characters.',
    genericError: 'Something went wrong. Please retry.',
    deleteError: 'Unable to delete the product typology. Please retry.',
    deleteForbidden: 'You cannot delete this product typology.',
    deleteInUse:
      'This product typology cannot be deleted because it is linked to one or more products.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, code and description.',
      },
    },
    hints: {
      codeLocked: 'The code cannot be changed after creation.',
    },
  },
}
