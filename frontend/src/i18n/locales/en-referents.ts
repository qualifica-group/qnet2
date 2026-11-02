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
    subtitle: 'Details of the selected referent, editable in place.',
    loadError: 'Unable to load the referent. Please try again.',
    details: 'Details',
    // The Configuratore commissioni rules whose recipient is the referent (spec 0336).
    related: {
      title: 'Related records',
      commissionConfigurations: 'Configured commissions',
      countLabel_one: '{{count}} related record',
      countLabel_other: '{{count}} related records',
    },
    // The KPI strip's own labels, not the side column's Contacts/Addresses
    // block titles, or the same word would mean two different things.
    stats: {
      contacts: 'Contact details',
      addresses: 'Locations',
      cardType: 'Card type',
    },
  },
  form: {
    newReferent: 'New referent',
    createTitle: 'Create referent',
    createSubtitle: 'Add a new referent to your organization.',
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
    supplierRegistry: 'Supplier registry',
    supplierRegistryPlaceholder: 'Select a supplier registry…',
    supplierRegistrySearch: 'Search suppliers…',
    supplierRegistryEmpty: 'No suppliers found.',
    supplierRegistryError: 'Unable to load the suppliers.',
    contactScope: 'Contact scope',
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
    leaveConfirm: {
      title: 'Leave without saving?',
      description: 'The referent has not been created yet: what you entered will be lost.',
      confirm: 'Leave without saving',
      cancel: 'Keep editing',
    },
    sections: {
      identity: {
        title: 'Personal details',
      },
      details: {
        title: 'Referent details',
      },
      contacts: {
        title: 'Contacts',
      },
      addresses: {
        title: 'Addresses',
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
    subtitle: 'Details of the selected referent type, editable in place.',
    loadError: 'Unable to load the referent type. Please try again.',
    created_at: 'Created at',
  },
  form: {
    newReferentType: 'New referent type',
    createTitle: 'Create referent type',
    createSubtitle: 'Add a new referent type to your organization.',
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
    leaveConfirm: {
      title: 'Leave without saving?',
      description: 'The referent type has not been created yet: what you entered will be lost.',
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
