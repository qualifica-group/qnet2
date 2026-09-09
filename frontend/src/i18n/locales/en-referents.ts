/**
 * Referents + Referent Types domains (spec 0016). Extracted to a sibling file
 * to keep `en.ts` within the engineering size limits (see
 * `.claude/rules/engineering.md` §6); its public shape is unchanged.
 */

export const referents = {
  title: 'Referents',
  subtitle: 'Browse, filter and manage the contact referents of your organization.',
  forbidden: "You don't have permission to view referents.",
  columns: {
    name: 'Name',
    referent_type: 'Referent type',
    contact_scope: 'Contact scope',
    primary_contact: 'Primary contact',
    created_at: 'Created at',
    user: 'Linked user',
  },
  detail: {
    title: 'Referent details',
    subtitle: 'Read-only view of the selected referent.',
    loadError: 'Unable to load the referent. Please try again.',
    details: 'Details',
  },
  form: {
    newReferent: 'New referent',
    createTitle: 'Create referent',
    createSubtitle: 'Add a new referent to your organization.',
    editTitle: 'Edit referent',
    editSubtitle: 'Update the selected referent.',
    referentType: 'Referent type',
    referentTypePlaceholder: 'Select a referent type…',
    referentTypeSearch: 'Search referent types…',
    referentTypeEmpty: 'No referent types found.',
    referentTypeError: 'Unable to load referent types.',
    linkedUser: 'Linked user',
    linkedUserPlaceholder: 'Select a user…',
    linkedUserSearch: 'Search users…',
    linkedUserEmpty: 'No users found.',
    linkedUserError: 'Unable to load users.',
    contactScope: 'Contact scope',
    activitySectors: 'Activity sectors',
    activitySectorsComingSoon: 'Coming soon',
    notes: 'Notes',
    notesMax: 'Notes must be at most 5000 characters.',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Referent created successfully.',
    updated: 'Referent updated successfully.',
    deleted: 'Referent deleted successfully.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the referent. Please try again.',
    deleteForbidden: 'You cannot delete this referent.',
    sections: {
      identity: {
        title: 'Personal details',
        description: 'Identifying details of the referent.',
      },
      details: {
        title: 'Referent details',
        description: 'Type, linked user, contact scope and notes.',
      },
      contacts: {
        title: 'Contacts',
        description: 'Phone and email contact details.',
      },
      addresses: {
        title: 'Addresses',
        description: 'Registered offices and billing addresses.',
      },
    },
  },
}

export const referentTypes = {
  title: 'Referent Types',
  subtitle: 'Browse, filter and manage the referent types of your organization.',
  forbidden: "You don't have permission to view referent types.",
  columns: {
    name: 'Name',
    created_at: 'Created at',
  },
  detail: {
    title: 'Referent type details',
    subtitle: 'Read-only view of the selected referent type.',
    loadError: 'Unable to load the referent type. Please try again.',
    created_at: 'Created at',
  },
  form: {
    newReferentType: 'New referent type',
    createTitle: 'Create referent type',
    createSubtitle: 'Add a new referent type to your organization.',
    editTitle: 'Edit referent type',
    editSubtitle: 'Update the selected referent type.',
    name: 'Name',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Referent type created successfully.',
    updated: 'Referent type updated successfully.',
    deleted: 'Referent type deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the referent type. Please try again.',
    deleteForbidden: 'You cannot delete this referent type.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name of the referent type.',
      },
    },
  },
}
