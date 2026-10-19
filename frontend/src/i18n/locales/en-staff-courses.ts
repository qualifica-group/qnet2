/**
 * Staff courses catalogue (spec 0284). Satellite file to keep `en.ts` within the size limits.
 * Mirror of `it-staff-courses.ts` — same key set. Root key `staffCourses.*`.
 */

export const staffCourses = {
  forbidden: 'You do not have permission to view courses.',
  columns: {
    name: 'Name',
    duration_hours: 'Duration (hours)',
    validity_months: 'Validity (months)',
    job_roles: 'Job roles',
    is_active: 'Active',
    trainings_count: 'Trainings',
  },
  detail: {
    loadError: 'Unable to load the course. Try again.',
    trainingsCount: 'Recorded trainings',
    created_at: 'Created on',
    updated_at: 'Updated on',
  },
  form: {
    newCourse: 'New course',
    createTitle: 'Create course',
    createSubtitle: 'Add a course to the staff training catalogue.',
    name: 'Name',
    description: 'Description',
    durationHours: 'Duration',
    durationHint: 'In hours, decimals allowed (for example 7.5). Leave empty if not defined.',
    hoursUnit: 'hours',
    validityMonths: 'Validity',
    validityHint: 'How many months the course stays valid after completion. Leave empty if it never expires.',
    monthsUnit: 'months',
    isActive: 'Active',
    jobRoles: 'Job roles requiring it',
    jobRolesHint: 'People with one of these job roles must hold the course: if it is missing they show as “Missing” in the Status view.',
    jobRolesSearch: 'Search a job role…',
    jobRolesPlaceholder: 'Select job roles',
    jobRolesEmpty: 'No job role found.',
    jobRolesError: 'Unable to load job roles.',
    jobRolesRemove: 'Remove job role',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Course created successfully.',
    updated: 'Course updated successfully.',
    deleted: 'Course deleted successfully.',
    nameRequired: 'The name is required.',
    nameMax: 'The name can be at most 255 characters long.',
    durationInvalid: 'The duration must be a number of hours greater than zero (at least one minute).',
    validityInvalid: 'The validity must be a whole number of months from 1 to 240.',
    genericError: 'Something went wrong. Try again.',
    deleteError: 'Unable to delete the course. Try again.',
    deleteForbidden: 'You cannot delete this course.',
    deleteInUse: 'Cannot delete: the course has recorded trainings. Deactivate it instead.',
    sections: {
      identity: {
        title: 'Details',
      },
    },
  },
}
