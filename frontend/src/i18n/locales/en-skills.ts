/**
 * Skills catalogue (spec 0286). Satellite file to keep `en.ts` within the size limits.
 * Mirror of `it-skills.ts` — same key set. Root key `skills.*`.
 */

export const skills = {
  forbidden: 'You do not have permission to view the skills catalogue.',
  levels: { 1: 'Basic', 2: 'Intermediate', 3: 'Advanced', 4: 'Expert' },
  columns: {
    name: 'Name',
    category: 'Category',
    job_roles: 'Job roles',
    is_active: 'Active',
    users_count: 'People',
  },
  detail: {
    loadError: 'Unable to load the skill. Try again.',
    usersCount: 'People with the skill',
    created_at: 'Created on',
    updated_at: 'Updated on',
  },
  form: {
    newSkill: 'New skill',
    createTitle: 'Create skill',
    createSubtitle: 'Add a skill to the catalogue.',
    name: 'Name',
    category: 'Category',
    categoryHint: 'Free text, optional: it groups skills together (for example “Languages”).',
    description: 'Description',
    isActive: 'Active',
    jobRoles: 'Job roles requiring it',
    jobRole: 'Job role',
    jobRolePlaceholder: 'Select the job role',
    jobRoleSearch: 'Search a job role…',
    jobRoleEmpty: 'No job role found.',
    jobRoleError: 'Unable to load job roles.',
    minLevel: 'Minimum level',
    minLevelShort: 'minimum level',
    addJobRole: 'Add job role',
    removeJobRole: 'Remove job role',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Skill created successfully.',
    updated: 'Skill updated successfully.',
    deleted: 'Skill deleted successfully.',
    nameRequired: 'The name is required.',
    nameMax: 'The name can be at most 255 characters long.',
    categoryMax: 'The category can be at most 100 characters long.',
    jobRoleRequired: 'Pick the job role in every row or remove the empty row.',
    jobRoleDuplicate: 'The same job role cannot appear twice.',
    genericError: 'Something went wrong. Try again.',
    deleteError: 'Unable to delete the skill. Try again.',
    deleteForbidden: 'You cannot delete this skill.',
    deleteInUse: 'Cannot delete: the skill is assigned to at least one person. Deactivate it instead.',
    sections: {
      identity: {
        title: 'Details',
      },
    },
  },
}
