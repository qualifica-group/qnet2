/**
 * HR org structure catalogue (spec 0271). Sibling file so `en.ts` stays within the size limits.
 */

export const jobRoles = {
  title: 'Job roles',
  subtitle: 'Browse, filter and manage the job roles assigned to people.',
  forbidden: "You don't have permission to view job roles.",
  columns: {
    name: 'Name',
    description: 'Description',
    job_activities: 'Activities',
    users_count: 'People',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  detail: {
    title: 'Job role details',
    subtitle: 'Read-only view of the selected job role.',
    loadError: 'Unable to load the job role. Please try again.',
    usersCount: 'People',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newJobRole: 'New job role',
    createTitle: 'Create job role',
    createSubtitle: 'Add a new job role.',
    name: 'Name',
    description: 'Description',
    jobActivities: 'Activities',
    jobActivitiesSearch: 'Search activities…',
    jobActivitiesPlaceholder: 'Select activities',
    jobActivitiesEmpty: 'No activities found.',
    jobActivitiesError: 'Unable to load the activities.',
    jobActivitiesRemove: 'Remove activity',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Job role created successfully.',
    updated: 'Job role updated successfully.',
    deleted: 'Job role deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    descriptionMax: 'Description must be at most 1000 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the job role. Please try again.',
    deleteInUse: 'Cannot delete: the job role is assigned to people.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, description and activities.',
      },
    },
  },
}
