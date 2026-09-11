/**
 * Localized strings for the operational-sites module (spec 0011): grid
 * columns, the read-only detail sheet and the create/edit form (single
 * embedded address — the site has no own name/label, it IS its address).
 *
 * Extracted from `en.ts` to keep that file within the engineering size limits
 * (see `.claude/rules/engineering.md` §6). Public API of `en.ts` is unchanged.
 */
export const operationalSites = {
  title: 'Operational Sites',
  subtitle: 'Browse, filter and manage the operational sites of your organization.',
  forbidden: "You don't have permission to view operational sites.",
  columns: {
    alias: 'Alias',
    city: 'City',
    street: 'Street',
    postal_code: 'Postal code',
    province: 'Province',
    region: 'Region',
    created_at: 'Created at',
  },
  detail: {
    title: 'Operational site details',
    subtitle: 'Read-only view of the selected operational site.',
    loadError: 'Unable to load the operational site. Please try again.',
    alias: 'Alias',
    line1: 'Street',
    postal_code: 'Postal code',
    city: 'City',
    province: 'Province',
    region: 'Region',
    country: 'Country',
    created_at: 'Created at',
    summary: {
      title: 'Summary',
      description: 'Updated as you type.',
    },
  },
  form: {
    newOperationalSite: 'New operational site',
    createTitle: 'Create operational site',
    createSubtitle: 'Add a new operational site to your organization.',
    editTitle: 'Edit operational site',
    editSubtitle: 'Update the selected operational site.',
    alias: 'Alias',
    line1: 'Street',
    postalCode: 'Postal code',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Operational site created successfully.',
    updated: 'Operational site updated successfully.',
    deleted: 'Operational site deleted successfully.',
    line1Required: 'Street is required.',
    line1Max: 'Street must be at most 255 characters.',
    postalCodeMax: 'Postal code must be at most 20 characters.',
    aliasMax: 'Alias must be at most 255 characters.',
    cityRequired: 'City is required.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the operational site. Please try again.',
    deleteForbidden: 'You cannot delete this operational site.',
    sections: {
      address: {
        title: 'Address',
        description: 'Location of the operational site.',
      },
    },
  },
}
