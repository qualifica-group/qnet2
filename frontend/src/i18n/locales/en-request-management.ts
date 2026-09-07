/**
 * Request Management domain (spec 0049): the "Gestione Richieste" operative
 * view over Opportunities for commercial operators (D-1, no new entity —
 * the record IS an Opportunity). Sibling file so `en.ts` stays within the
 * engineering size limits (see `.claude/rules/engineering.md` §6).
 */

export const requestManagement = {
  title: 'Request Management',
  subtitle: 'Work opportunities: verify contacts and complete dynamic fields.',
  forbidden: "You don't have permission to view Request Management.",
  categoryTabs: {
    all: 'All',
  },
  columns: {
    source: 'Source',
    pendingChangeRequests: 'Change requests',
    productCategory: 'Product category',
    offerLines: 'Product lines',
    generalNotes: 'General notes',
    operator: 'Operator (GA2)',
    operationalSite: 'Operational site',
    firstName: 'First name',
    lastName: 'Last name',
    taxCode: 'Tax code',
    phone: 'Phone',
    createdAt: 'Created at',
    nextCallbackAt: 'Next callback',
    transferred: 'Transferred',
    quoteWorkflowStatus: 'Working status',
  },
  pendingChangeRequests: {
    alert_one: '{{count}} pending change request',
    alert_other: '{{count}} pending change requests',
  },
  advancedFilters: {
    registry: 'Registry',
    referent: 'Contact',
    opportunityStatus: 'Sales status',
    operationalSite: 'Operational site',
    expectedCloseRange: 'Expected close date',
    nextCallbackRange: 'Next callback',
  },
  detail: {
    title: 'Request details',
    subtitle: 'Work the selected opportunity: contacts and dynamic fields.',
  },
  delete: {
    success: 'Request deleted.',
    forbidden: 'You are not allowed to delete this request.',
    error: 'Unable to delete the request. Please try again.',
  },
  assign: {
    tableButton: 'Assign operators',
    description: '{{count}} request(s) selected.',
    actions: {
      balancedHint: 'Distributes the selected requests across the Site operators, balancing their workload.',
      singleHint: 'Assigns every selected request to the same operator.',
    },
    success: 'Operators assigned to {{count}} request(s).',
    errors: {
      noOperators: 'No operator found for the selected Site.',
      generic: 'Unable to assign the operators. Please try again.',
    },
  },
  transfer: {
    title: 'Transfer contact',
    description: '{{count}} request(s) selected.',
    notice: 'Contact transferred from {{site}}',
    success: '{{count}} request(s) transferred.',
    errors: {
      generic: 'Unable to transfer the contact. Please try again.',
    },
  },
  form: {
    /** Edit/duplicate stay "not applicable" (spec 0057 D-7): the work panel is the only way to change an existing request. */
    notApplicable: 'Request Management has no edit form: work the record from its detail panel.',
    newRequest: 'New request',
    createTitle: 'New request',
    createSubtitle: 'Client details and product lines of the new request.',
    create: {
      client: {
        title: 'Client details',
        description: 'Pick an existing registry, or fill in the details of a new client.',
        registryLabel: 'Existing registry',
        registryPlaceholder: 'No registry selected',
        registrySearch: 'Search a registry',
        registryEmpty: 'No results',
        registryError: 'Could not load the options.',
        registryHint: 'Picking an existing registry hides the identity/contacts/address fields below and links the request to it.',
        identityGroup: 'Identity',
        contactsGroup: 'Contacts',
        addressGroup: 'Address',
      },
      generalNotes: {
        label: 'General notes',
        placeholder: 'What the client asked for, in their own words…',
      },
      summary: {
        description: 'What you are about to create.',
        existingRegistry: 'Existing registry',
      },
      attribution: {
        title: 'Attribution',
        description: 'Where the request comes from and who reports it.',
        source: 'Source',
        sourceSearch: 'Search a source',
        reporter: 'Reporter',
        reporterSearch: 'Search a reporter',
        operationalSite: 'Operational site',
        operationalSiteSearch: 'Search a site',
        selectPlaceholder: 'Select',
        selectEmpty: 'No results',
        selectError: 'Could not load the options.',
        rewards: {
          fieldLabel: 'Assigned rewards',
          add: 'Add reward',
          remove: 'Remove {{name}}',
          searchPlaceholder: 'Search a reward type…',
          empty: 'No reward type found.',
          error: 'Could not load the reward types.',
          loadMore: 'Load more',
          reporterRequiredHint: 'Select a reporter first to assign a reward.',
        },
      },
      team: {
        title: 'Team',
        description: 'Supervisor and account managers of the offer.',
        supervisor: 'Supervisor',
        supervisorSearch: 'Search supervisors…',
        managers: 'Account managers',
        operatorFilteredBySite: 'Only the operators of the selected site.',
        selectPlaceholder: 'Select',
        selectEmpty: 'No results',
        selectError: 'Could not load the options.',
      },
      cancel: 'Cancel',
      save: 'Create request',
      saving: 'Creating…',
      success: 'Request created.',
      validation: {
        productLinesRequired: 'Add at least one product line.',
        productLineIncomplete: 'Select a business function and a product category for every row.',
        // Spec 0077 INV-2: every row shares the same Business function
        // (creation: no historic record to grandfather).
        businessFunctionMismatch: 'All rows must share the same business function.',
        sourceRequired: 'Select a source.',
      },
      errors: {
        generic: 'Something went wrong. Please try again.',
        identityIncomplete: 'Complete the client identity fields: {{fields}}',
        addressIncomplete: 'Complete the address (or clear it entirely): {{fields}}',
        contactsInvalid: 'Fix the contacts: {{fields}}',
      },
    },
  },
  /**
   * "Offer lines" (user directive 2026-08-07): the section reuses the
   * `quotes.form.offerTab.*` keys (it IS the Offerte component); only the case
   * the Offerte form does not have lives here — no category picked yet, since
   * this module picks them on the same screen.
   */
  offerLines: {
    hintNoCategory: 'Pick a product category first: it scopes the selectable products.',
    editAction: 'Edit the offer rows',
    dialogTitle: 'Offer rows',
    dialogDescription: 'Edit product, quantity, unit price and VAT of this offer\'s rows.',
    validationSummary: 'Check the highlighted rows before saving.',
  },
  workPanel: {
    loadError: 'Could not load the record.',
    saving: 'Saving…',
    save: 'Save',
    saved: 'Working data saved.',
    genericError: 'Something went wrong. Please try again.',
    generalNotes: {
      title: 'General notes',
    },
    callback: {
      title: 'Next callback',
      description: 'Plan the next follow-up call with the client.',
      label: 'Callback date',
      timeLabel: 'Callback time (optional)',
    },
    attribution: {
      title: 'Attribution',
      description: 'Where the request comes from and who reports it.',
      source: 'Source',
      sourceSearch: 'Search a source',
      reporter: 'Reporter',
      reporterSearch: 'Search a reporter',
      operationalSite: 'Operational site',
      operationalSiteSearch: 'Search a site',
      selectPlaceholder: 'Select',
      selectEmpty: 'No results',
      selectError: 'Could not load the options.',
      rewards: {
        fieldLabel: 'Assigned rewards',
        add: 'Add reward',
        remove: 'Remove {{name}}',
        searchPlaceholder: 'Search a reward type…',
        empty: 'No reward type found.',
        error: 'Could not load the reward types.',
        loadMore: 'Load more',
        reporterRequiredHint: 'Select a reporter first to assign a reward.',
      },
    },
    team: {
      title: 'Team',
      description: 'Supervisor and account managers of the offer.',
      supervisor: 'Supervisor',
      supervisorSearch: 'Search supervisors…',
      managers: 'Account managers',
      operatorFilteredBySite: 'Only the operators of the selected site.',
      selectPlaceholder: 'Select',
      selectEmpty: 'No results',
      selectError: 'Could not load the options.',
    },
    client: {
      title: 'Client details',
      description: 'Identity, contacts and address of the client.',
      identityGroup: 'Identity',
      contactsGroup: 'Contacts',
      addressGroup: 'Address',
    },
    header: {
      title: 'Preliminary information',
      unsavedChanges: 'Unsaved changes',
      salesStatus: 'Sales',
      nextCallback: 'Next callback',
    },
    summary: {
      title: 'Request summary',
      description: 'Read-only commercial context.',
      registry: 'Client',
      referent: 'Contact',
      commercial: 'Sales rep',
      expectedCloseDate: 'Expected close date',
      estimatedValue: 'Estimated value',
      successProbability: 'Success probability',
    },
    productLines: {
      title: 'Product lines',
      description: "The request's business function and product category.",
      fieldLabel: 'Product lines',
      hint: 'The categories chosen here scope the products of interest and, once saved, the request-specific fields.',
    },
    collaboration: {
      notesTab: 'Notes',
      documentsTab: 'Documents',
      activityTab: 'History',
    },
    validation: {
      sourceRequired: 'Select a source.',
      productLinesRequired: 'Add at least one product line.',
      productLineIncomplete: 'Select a business function and a product category for every row.',
      // Spec 0077 INV-2, D-5: only enforced once `product_lines` was
      // actually edited (grandfathering a non-conformant historic record,
      // see `request-work-schema.ts`).
      businessFunctionMismatch: 'All rows must share the same business function.',
      summary: 'Cannot save: check these fields — {{fields}}.',
    },
  },
}
