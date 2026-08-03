/**
 * Opportunity workflow configurator domain (spec 0047, Lane C). Sibling
 * file so `en.ts` stays within the engineering size limits (see
 * `.claude/rules/engineering.md` §6). A workflow is a NEW, distinct
 * dimension from `opportunityStatuses` (the sales pipeline): criteria-matched
 * per opportunity, each with its own set of "processing statuses" (a pinned
 * `open` first row, the two pinned terminal `closed_won`/`closed_lost` rows
 * last, and reorderable custom rows in between).
 */

export const opportunityWorkflows = {
  title: 'Workflow configurator',
  subtitle: 'Browse, filter and manage the processing-status workflows applied to opportunities.',
  forbidden: "You don't have permission to view opportunity workflows.",
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
  },
  detail: {
    title: 'Opportunity workflow details',
    subtitle: 'Read-only view of the selected opportunity workflow.',
    loadError: 'Unable to load the opportunity workflow. Please try again.',
    active: 'Active',
    inactive: 'Inactive',
    criteriaTitle: 'Criteria',
    statusesTitle: 'Statuses',
    createdAt: 'Created at',
  },
  form: {
    newOpportunityWorkflow: 'New workflow',
    createTitle: 'Create opportunity workflow',
    createSubtitle: 'Define a new processing-status workflow for opportunities.',
    editTitle: 'Edit opportunity workflow',
    editSubtitle: 'Update the selected opportunity workflow.',
    name: 'Name',
    isActive: 'Active',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Opportunity workflow created successfully.',
    updated: 'Opportunity workflow updated successfully.',
    deleted: 'Opportunity workflow deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the opportunity workflow. Please try again.',
    deleteForbidden: 'You cannot delete this opportunity workflow.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name and active status of the workflow.',
      },
      criteria: {
        title: 'Criteria',
        description: 'The workflow applies to opportunities matching ALL of these criteria.',
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
      markValidated: 'System status "Validated"',
      requiresNoteBadge: 'Note required',
      dragHandleLabel: 'Drag to reorder',
      add: 'Add status',
      remove: 'Remove status',
      nameRequired: 'Every status needs a name.',
      defaultOpenName: 'Open',
      defaultValidatedName: 'Validated',
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
    subtitle: 'Statuses applied to opportunities that match no active workflow.',
    loadError: 'Unable to load the default statuses. Please try again.',
    saved: 'Default statuses updated successfully.',
    forbidden: 'You cannot update the default statuses.',
    genericError: 'Unable to update the default statuses. Please try again.',
  },
}
