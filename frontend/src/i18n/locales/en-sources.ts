/**
 * Sources domain (spec 0018). Extracted to a sibling file to keep `en.ts`
 * within the engineering size limits (see `.claude/rules/engineering.md`
 * §6); mirrors `referentTypes` (spec 0016) 1:1.
 */

export const sources = {
  title: 'Sources',
  subtitle: 'Browse, filter and manage the sources of your organization.',
  forbidden: "You don't have permission to view sources.",
  columns: {
    name: 'Name',
    created_at: 'Created at',
  },
  detail: {
    title: 'Source details',
    subtitle: 'Details of the selected source, editable in place.',
    loadError: 'Unable to load the source. Please try again.',
    created_at: 'Created at',
  },
  form: {
    newSource: 'New source',
    createTitle: 'Create source',
    createSubtitle: 'Add a new source to your organization.',
    name: 'Name',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Source created successfully.',
    updated: 'Source updated successfully.',
    deleted: 'Source deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the source. Please try again.',
    leaveConfirm: {
      title: 'Leave without saving?',
      description: 'The source has not been created yet: what you entered will be lost.',
      confirm: 'Leave without saving',
      cancel: 'Keep editing',
    },
    sections: {
      identity: {
        title: 'Details',
      },
    },
  },
}
