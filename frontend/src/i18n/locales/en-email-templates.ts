/**
 * Email Templates domain (spec 0175): a supporting CRUD table used by the
 * work order's email composer. Sibling file to keep `en.ts` within the
 * engineering size limits (see `.claude/rules/engineering.md` §6), mirrors
 * `it-email-templates.ts`.
 *
 * Base copy for FE-02 (the "Email Templates" module): table, detail and form
 * with a placeholder picker (AC-022). `modules.work_orders` is the only
 * admitted value today (D-10), kept as a map so it extends without breaking
 * the shape.
 */

export const emailTemplates = {
  title: 'Email templates',
  subtitle: 'Browse, filter and manage the email templates reused across work orders.',
  forbidden: "You don't have permission to view email templates.",
  columns: {
    name: 'Name',
    module: 'Module',
    subject: 'Subject',
    description: 'Description',
    is_active: 'Active',
    created_at: 'Created at',
  },
  modules: {
    work_orders: 'Work orders',
    invoices: 'Invoices',
  },
  detail: {
    title: 'Email template details',
    subtitle: 'Read-only view of the selected template.',
    loadError: 'Unable to load the email template. Please retry.',
    module: 'Module',
    subject: 'Subject',
    body: 'Body',
    description: 'Description',
    isActive: 'Active',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newEmailTemplate: 'New email template',
    createTitle: 'Create email template',
    createSubtitle: 'Add a new email template.',
    editTitle: 'Edit email template',
    editSubtitle: 'Update the selected email template.',
    name: 'Name',
    module: 'Module',
    subject: 'Subject',
    body: 'Body',
    description: 'Description',
    isActive: 'Active',
    variablesPicker: 'Insert placeholder',
    variablesPickerEmpty: 'No placeholder available.',
    variablesPickerError: 'Unable to load the placeholders. Please retry.',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Email template created successfully.',
    updated: 'Email template updated successfully.',
    deleted: 'Email template deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name may contain at most 191 characters.',
    nameDuplicate: 'A template with this name already exists for this module.',
    subjectRequired: 'Subject is required.',
    subjectMax: 'Subject may contain at most 255 characters.',
    bodyRequired: 'Body is required.',
    descriptionMax: 'Description may contain at most 500 characters.',
    genericError: 'Something went wrong. Please retry.',
    deleteError: 'Unable to delete the email template. Please retry.',
    deleteForbidden: 'You cannot delete this email template.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, module, subject and status of the template.',
      },
      content: {
        title: 'Content',
        description: 'The template body, with placeholders and an insertion picker.',
      },
    },
  },
}
