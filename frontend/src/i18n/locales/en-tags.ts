/**
 * Tags domain (spec 0019). Extracted to a sibling file to keep `en.ts`
 * within the engineering size limits (see `.claude/rules/engineering.md`
 * §6); mirrors `sources`/`referentTypes` 1:1, plus the delete-in-use (409)
 * branch mirroring `productCategories`.
 */

export const tags = {
  title: 'Tags',
  subtitle: 'Browse, filter and manage the tags of your organization.',
  forbidden: "You don't have permission to view tags.",
  columns: {
    name: 'Name',
    created_at: 'Created at',
  },
  detail: {
    title: 'Tag details',
    subtitle: 'Details of the selected tag, editable in place.',
    loadError: 'Unable to load the tag. Please try again.',
    created_at: 'Created at',
  },
  form: {
    newTag: 'New tag',
    createTitle: 'Create tag',
    createSubtitle: 'Add a new tag to your organization.',
    name: 'Name',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Tag created successfully.',
    updated: 'Tag updated successfully.',
    deleted: 'Tag deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the tag. Please try again.',
    deleteInUse: 'Cannot delete: the tag is attached to at least one record.',
    leaveConfirm: {
      title: 'Leave without saving?',
      description: 'The tag has not been created yet: what you entered will be lost.',
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
