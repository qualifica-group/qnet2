/**
 * Work order status configurator domain (spec 0322): twin of the quote status
 * configurator with its own groups (open, in progress, completed, closed) and
 * a progress percentage per status. Kept in a side file to keep `en.ts` within
 * the size limits (see `.claude/rules/engineering.md` §6).
 */

export const workOrderWorkflows = {
  title: 'Work order status configurator',
  subtitle: 'Browse, filter and manage the processing-status sets applied to work orders.',
  forbidden: "You don't have permission to view work order status sets.",
  columns: {
    name: 'Name',
    criteriaFields: 'Criteria fields',
    criteriaValues: 'Criteria values',
    statusesCount: 'Statuses',
    isActive: 'Active',
    updatedAt: 'Updated at',
  },
  criterionFields: {
    business_function_id: 'Business function',
    product_category_id: 'Product category',
    product_category_branch_id: 'Product category (branch)',
  },
  detail: {
    title: 'Work order status set detail',
    subtitle: 'View of the selected status set.',
    loadError: 'Unable to load the work order status set. Please try again.',
    active: 'Active',
    inactive: 'Inactive',
    criteriaTitle: 'Criteria',
    statusesTitle: 'Statuses',
    createdAt: 'Created at',
  },
  form: {
    newWorkOrderWorkflow: 'New status set',
    createTitle: 'Create work order status set',
    createSubtitle: 'Define a new set of processing statuses for work orders.',
    name: 'Name',
    isActive: 'Active',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Work order status set created successfully.',
    updated: 'Work order status set updated successfully.',
    deleted: 'Work order status set deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name can be at most 255 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the work order status set. Please try again.',
    deleteForbidden: 'You cannot delete this work order status set.',
    leaveConfirm: {
      title: 'Leave without saving?',
      description: 'The work order status set has not been created yet: the data entered will be lost.',
      confirm: 'Leave without saving',
      cancel: 'Keep editing',
    },
    sections: {
      identity: {
        title: 'Details',
      },
      criteria: {
        title: 'Criteria',
        description: 'The set applies to work orders that meet ALL of these criteria.',
      },
      statuses: {
        title: 'Processing statuses',
        description: 'The statuses of this set. "Open" stays first; "Completed" and "Closed" stay last.',
      },
    },
    criteria: {
      required: 'At least one criterion is required.',
      fieldRequired: 'Select a field.',
      valueRequired: 'Select a value.',
      duplicateField: 'This field is already used by another criterion.',
    },
    statuses: {
      nameRequired: 'Every status needs a name.',
      defaultOpenName: 'Open',
      defaultCompletedName: 'Completed',
      defaultClosedName: 'Closed',
      group: {
        open: 'Open',
        in_progress: 'In progress',
        completed: 'Completed',
        closed: 'Closed',
      },
    },
  },
  defaultStatuses: {
    openButton: 'Default statuses',
    title: 'Global default statuses',
    subtitle: 'Statuses applied to work orders that match no active set.',
    loadError: 'Unable to load the default statuses. Please try again.',
    saved: 'Default statuses updated successfully.',
    forbidden: 'You cannot update the default statuses.',
    genericError: 'Unable to update the default statuses. Please try again.',
  },
}
