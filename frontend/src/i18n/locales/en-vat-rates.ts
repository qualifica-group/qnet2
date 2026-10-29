/**
 * VAT rates domain. Extracted to a sibling file to keep `en.ts` within the
 * engineering size limits (see `.claude/rules/engineering.md` §6); mirrors
 * `sources` (`en-sources.ts`) 1:1, with the extra `rate` field.
 */

export const vatRates = {
  title: 'VAT',
  subtitle: 'Browse, filter and manage the VAT rates of your organization.',
  forbidden: "You don't have permission to view VAT rates.",
  columns: {
    name: 'Name',
    rate: 'Rate',
    created_at: 'Created at',
  },
  detail: {
    title: 'VAT rate details',
    subtitle: 'Details of the selected VAT rate, editable in place.',
    loadError: 'Unable to load the VAT rate. Please try again.',
    details: 'Details',
    created_at: 'Created at',
  },
  form: {
    newVatRate: 'New VAT rate',
    createTitle: 'Create VAT rate',
    createSubtitle: 'Add a new VAT rate to your organization.',
    editTitle: 'Edit VAT rate',
    editSubtitle: 'Update the selected VAT rate.',
    name: 'Name',
    rate: 'Rate',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'VAT rate created successfully.',
    updated: 'VAT rate updated successfully.',
    deleted: 'VAT rate deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    rateRequired: 'Rate is required.',
    rateInvalid: 'Rate must be zero or a positive number.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the VAT rate. Please try again.',
    leaveConfirm: {
      title: 'Leave without saving?',
      description: 'The VAT rate has not been created yet: what you entered will be lost.',
      confirm: 'Leave without saving',
      cancel: 'Keep editing',
    },
    sections: {
      identity: {
        title: 'Details',
        description: 'Name and rate of the VAT rate.',
      },
    },
  },
}
