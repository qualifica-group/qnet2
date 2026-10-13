/**
 * Localized strings for the operational-sites module (spec 0011): grid
 * columns, the in-place editable detail and the create form (single
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
    is_active: 'Active',
    created_at: 'Created at',
  },
  detail: {
    title: 'Operational site details',
    subtitle: 'View and edit the selected operational site.',
    loadError: 'Unable to load the operational site. Please try again.',
    city: 'City',
    province: 'Province',
    region: 'Region',
    country: 'Country',
    created_at: 'Created at',
  },
  form: {
    newOperationalSite: 'New operational site',
    createTitle: 'Create operational site',
    createSubtitle: 'Add a new operational site to your organization.',
    alias: 'Alias',
    line1: 'Street',
    postalCode: 'Postal code',
    isActive: 'Active site',
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
    leaveConfirm: {
      title: 'Leave without saving?',
      description: 'The operational site has not been created yet: the data you entered will be lost.',
      confirm: 'Leave without saving',
      cancel: 'Keep editing',
    },
    sections: {
      address: {
        title: 'Address',
        description: 'Location of the operational site.',
      },
      status: {
        title: 'Status',
        description: 'An inactive site is no longer offered in selection menus.',
      },
    },
  },
}
