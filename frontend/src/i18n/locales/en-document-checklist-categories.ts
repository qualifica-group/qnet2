/**
 * Document checklist categories domain (spec 0410): categories synchronized
 * with ArchivioSmart. The sync status and log texts reuse the
 * `documentChecklists.syncStatus` / `documentChecklists.syncLogs` keys.
 * Mirror of `it-document-checklist-categories.ts`.
 */

export const documentChecklistCategories = {
  title: 'Checklist categories',
  subtitle: 'Group document checklists into categories, synchronized with ArchivioSmart.',
  forbidden: 'You do not have permission to view checklist categories.',
  columns: {
    name: 'Name',
    description: 'Description',
    sort_order: 'Order',
    checklists_count: 'Checklists',
    sync_status: 'Synchronization',
    sync_error: 'Sync error',
    synced_at: 'Last synchronization',
    created_at: 'Created at',
  },
  actions: {
    syncAgain: 'Synchronize again',
  },
  toast: {
    syncAgainQueued: 'Synchronization queued.',
    syncError: 'Unable to start the synchronization. Try again.',
  },
  detail: {
    loadError: 'Unable to load the category. Try again.',
  },
  form: {
    newCategory: 'New category',
    createTitle: 'Create category',
    createSubtitle: 'Add a category to group checklists.',
    editTitle: 'Edit category',
    editSubtitle: 'Update the category.',
    name: 'Name',
    description: 'Description',
    sortOrder: 'Order',
    sortOrderHint: 'A number from 0 to 65535: categories are listed from the lowest.',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Category created successfully.',
    updated: 'Category updated successfully.',
    deleted: 'Category deleted successfully.',
    genericError: 'Something went wrong. Try again.',
    deleteError: 'Unable to delete the category. Try again.',
    leaveConfirm: {
      title: 'Leave without saving?',
      description: 'The category has not been created yet: the data entered will be lost.',
      confirm: 'Leave without saving',
      cancel: 'Keep editing',
    },
    sections: {
      identity: {
        title: 'Details',
      },
    },
  },
  validation: {
    nameRequired: 'The name is required.',
    nameMax: 'The name can contain at most 255 characters.',
    descriptionMax: 'The description can contain at most 2000 characters.',
    sortOrderRange: 'The order must be an integer between 0 and 65535.',
  },
}
