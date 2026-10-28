/**
 * Attributes domain (spec 0017). Split from `en-products.ts` to keep that
 * file within the engineering size limits (`.claude/rules/engineering.md` §6).
 */

export const attributes = {
  title: 'Attributes',
  subtitle: 'Browse, filter and manage the reusable dynamic attributes.',
  forbidden: "You don't have permission to view attributes.",
  columns: {
    code: 'Code',
    name: 'Name',
    type: 'Type',
    created_at: 'Created at',
  },
  detail: {
    loadError: 'Unable to load the attribute. Please try again.',
    details: 'Details',
    created_at: 'Created at',
  },
  form: {
    newAttribute: 'New attribute',
    createTitle: 'Create attribute',
    createSubtitle: 'Add a new reusable dynamic attribute.',
    code: 'Code',
    name: 'Name',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Attribute created successfully.',
    updated: 'Attribute updated successfully.',
    deleted: 'Attribute deleted successfully.',
    codeRequired: 'Code is required.',
    codeMax: 'Code must be at most 64 characters.',
    codeInvalid: 'Code must be a lowercase snake_case identifier.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the attribute. Please try again.',
    deleteForbidden: 'You cannot delete this attribute.',
    deleteInUse: 'This attribute is assigned to a category or has product values.',
    leaveConfirm: {
      title: 'Leave without saving?',
      description: 'The attribute has not been created yet: what you entered will be lost.',
      confirm: 'Leave without saving',
      cancel: 'Keep editing',
    },
    optionValueRequired: 'Value is required.',
    optionValueMax: 'Value must be at most 191 characters.',
    optionLabelRequired: 'Label is required.',
    optionLabelMax: 'Label must be at most 191 characters.',
    optionsRequiredForEnum: 'Add at least one option for a list attribute.',
    optionValuesDuplicate: 'Option values must be unique.',
    relationEntityTypeRequired: 'The relation target module is required.',
    relationForSelectResourceRequired: 'The picker resource is required.',
  },
  layout: {
    // Title of the synthetic "Other information" section `AttributeLayoutRenderer`
    // (spec 0062) generates at runtime for effective attributes not placed in
    // any section of the configured layout — never persisted.
    otherInformation: 'Other information',
    // Hint on a conditional section (spec 0288): the boolean attribute that shows it.
    visibleWhen: 'Visible if: {{name}}',
  },
}
