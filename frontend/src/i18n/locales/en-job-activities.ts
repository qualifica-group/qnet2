/**
 * HR org structure catalogue (spec 0271). Sibling file so `en.ts` stays within the size limits.
 */

export const jobActivities = {
  title: 'Activities',
  subtitle: 'Browse, filter and manage the activities linked to job roles.',
  forbidden: "You don't have permission to view activities.",
  columns: {
    name: 'Name',
    description: 'Description',
    job_roles: 'Job roles',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  detail: {
    title: 'Activity details',
    subtitle: 'Read-only view of the selected activity.',
    loadError: 'Unable to load the activity. Please try again.',
    jobRoles: 'Job roles',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newJobActivity: 'New activity',
    createTitle: 'Create activity',
    createSubtitle: 'Add a new activity.',
    name: 'Name',
    description: 'Description',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Activity created successfully.',
    updated: 'Activity updated successfully.',
    deleted: 'Activity deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    descriptionMax: 'Description must be at most 1000 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the activity. Please try again.',
    deleteForbidden: 'You cannot delete this activity.',
    deleteInUse: 'Cannot delete: the activity is linked to a job role.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name and description.',
      },
    },
  },
}
