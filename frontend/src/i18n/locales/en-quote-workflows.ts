/**
 * Quote workflow configurator domain (spec 0047, moved from the Opportunity
 * onto the Quote by spec 0083 D-6). Sibling file so `en.ts` stays within the
 * engineering size limits (see `.claude/rules/engineering.md` §6). A workflow
 * is a NEW, distinct dimension from the sales pipeline: criteria-matched per
 * quote, each with its own set of "processing statuses" (a pinned `open`
 * first row, the two pinned terminal `closed_won`/`closed_lost` rows last,
 * and reorderable custom rows in between).
 */

export const quoteWorkflows = {
  title: 'Workflow configurator',
  subtitle: 'Browse, filter and manage the processing-status workflows applied to quotes.',
  forbidden: "You don't have permission to view quote workflows.",
  columns: {
    name: 'Name',
    criteriaFields: 'Criteria fields',
    criteriaValues: 'Criteria values',
    statusesCount: 'Statuses',
    isActive: 'Active',
    updatedAt: 'Updated at',
  },
  criterionFields: {
    state_id: 'Region',
    source_id: 'Source',
    business_function_id: 'Business function',
    product_category_id: 'Product category',
    /** Spec 0083 (D-7): hint shown next to a criterion field resolved from the parent Opportunity. */
    inheritedHint: 'Inherited from the opportunity',
  },
  detail: {
    title: 'Quote workflow details',
    subtitle: 'Read-only view of the selected quote workflow.',
    loadError: 'Unable to load the quote workflow. Please try again.',
    active: 'Active',
    inactive: 'Inactive',
    criteriaTitle: 'Criteria',
    statusesTitle: 'Statuses',
    createdAt: 'Created at',
  },
  form: {
    newQuoteWorkflow: 'New workflow',
    createTitle: 'Create quote workflow',
    createSubtitle: 'Define a new processing-status workflow for quotes.',
    editTitle: 'Edit quote workflow',
    editSubtitle: 'Update the selected quote workflow.',
    name: 'Name',
    isActive: 'Active',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Quote workflow created successfully.',
    updated: 'Quote workflow updated successfully.',
    deleted: 'Quote workflow deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the quote workflow. Please try again.',
    deleteForbidden: 'You cannot delete this quote workflow.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name and active status of the workflow.',
      },
      criteria: {
        title: 'Criteria',
        description: 'The workflow applies to quotes matching ALL of these criteria.',
      },
      statuses: {
        title: 'Workflow statuses',
        description: 'The processing statuses of this workflow. "Open" stays first and "Closed" stays last.',
      },
    },
    criteria: {
      field: 'Field',
      fieldPlaceholder: 'Select a field…',
      value: 'Value',
      valuePlaceholder: 'Select a value…',
      valueSearchPlaceholder: 'Search…',
      valueEmpty: 'No options found.',
      valueError: 'Unable to load the options.',
      add: 'Add criterion',
      remove: 'Remove criterion',
      required: 'At least one criterion is required.',
      fieldRequired: 'Select a field.',
      valueRequired: 'Select a value.',
      duplicateField: 'This field is already used by another criterion.',
    },
    statuses: {
      name: 'Status name',
      description: 'Status description',
      descriptionPlaceholder: 'Explain when this status applies…',
      descriptionHint: 'Show the status description',
      requiresNote: 'Requires an explanatory note',
      requiresNoteBadge: 'Note required',
      dragHandleLabel: 'Drag to reorder',
      add: 'Add status',
      remove: 'Remove status',
      nameRequired: 'Every status needs a name.',
      defaultOpenName: 'Open',
      defaultClosedWonName: 'Closed (won)',
      defaultClosedLostName: 'Closed (lost)',
      group: {
        label: 'Group',
        open: 'Open',
        pending: 'Pending',
        validated: 'Validated',
        closed_won: 'Closed (won)',
        closed_lost: 'Closed (lost)',
      },
    },
  },
  defaultStatuses: {
    openButton: 'Default statuses',
    title: 'Global default statuses',
    subtitle: 'Statuses applied to quotes that match no active workflow.',
    loadError: 'Unable to load the default statuses. Please try again.',
    saved: 'Default statuses updated successfully.',
    forbidden: 'You cannot update the default statuses.',
    genericError: 'Unable to update the default statuses. Please try again.',
  },
}
