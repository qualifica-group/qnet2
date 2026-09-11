/**
 * Localized strings for the companies module (spec 0010): grid columns, the
 * read-only detail sheet and the create/edit form (general + single embedded
 * address section).
 *
 * Extracted from `en.ts` to keep that file within the engineering size limits
 * (see `.claude/rules/engineering.md` §6). Public API of `en.ts` is unchanged.
 */
export const companies = {
  title: 'Companies',
  subtitle: 'Browse, filter and manage the companies of your application.',
  forbidden: "You don't have permission to view companies.",
  columns: {
    id: 'ID',
    denomination: 'Denomination',
    vat_number: 'VAT number',
    city: 'City',
    province: 'Province',
    region: 'Region',
    postal_code: 'Postal code',
    country: 'Country',
    created_at: 'Created at',
  },
  detail: {
    title: 'Company details',
    subtitle: 'Read-only view of the selected company.',
    loadError: 'Unable to load the company. Please try again.',
    summary: {
      title: 'Summary',
      description: 'Updated as you type.',
    },
    // The card's own labels, the way `operationalSites.detail` has them:
    // the ones under `columns` are table headers, not card labels.
    city: 'City',
    province: 'Province',
    region: 'Region',
    country: 'Country',
  },
  form: {
    newCompany: 'New company',
    createTitle: 'Create company',
    createSubtitle: 'Add a new company to your application.',
    editTitle: 'Edit company',
    editSubtitle: 'Update the selected company.',
    denomination: 'Denomination',
    vatNumber: 'VAT number',
    line1: 'Address',
    line2: 'Address line 2',
    postalCode: 'Postal code',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Company created successfully.',
    updated: 'Company updated successfully.',
    deleted: 'Company deleted successfully.',
    denominationRequired: 'Denomination is required.',
    denominationMax: 'Denomination must be at most 255 characters.',
    line1Required: 'Address is required when an address is provided.',
    vatNumberInvalid: 'The VAT number is not valid.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the company. Please try again.',
    deleteForbidden: 'You cannot delete this company.',
    sections: {
      general: {
        title: 'General',
        description: 'Company name and VAT number.',
      },
      address: {
        title: 'Address',
        description: 'Registered office address.',
      },
    },
  },
}
