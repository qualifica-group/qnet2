/**
 * Sectors domain strings (spec 0018), kept in a sibling file per the
 * engineering size limits (see `.claude/rules/engineering.md` §6). Mirrors
 * the `productCategories`/`referentTypes` shape.
 */
export const sectors = {
  title: 'Sectors',
  subtitle: 'Browse, filter and manage your sectors.',
  forbidden: "You don't have permission to view sectors.",
  columns: {
    name: 'Name',
    parent: 'Parent',
    is_active: 'Active',
    created_at: 'Created at',
  },
  detail: {
    title: 'Sector details',
    subtitle: 'Read-only view of the selected sector.',
    loadError: 'Unable to load the sector. Please try again.',
  },
  badges: {
    notActive: 'Not active',
  },
  form: {
    newSector: 'New sector',
    createTitle: 'Create sector',
    createSubtitle: 'Add a new sector.',
    editTitle: 'Edit sector',
    editSubtitle: 'Update the selected sector.',
    name: 'Name',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    parent: 'Parent sector',
    parentPlaceholder: 'Select a parent sector…',
    parentSearch: 'Search sectors…',
    parentEmpty: 'No sectors found.',
    parentNoMatch: 'No matches found.',
    parentError: 'Unable to load sectors.',
    noParent: 'No parent (root sector)',
    isActive: 'Active',
    isActiveHint:
      'An inactive sector, with all its sub-sectors, no longer appears in selection fields. Existing links stay valid.',
    isActiveInheritedNotice: 'Not active because "{{sector}}" is not',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Sector created successfully.',
    updated: 'Sector updated successfully.',
    deleted: 'Sector deleted successfully.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the sector. Please try again.',
    deleteForbidden: 'You cannot delete this sector.',
    deleteInUse: 'This sector has sub-sectors and cannot be deleted.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, parent and status of the sector.',
      },
    },
  },
}
