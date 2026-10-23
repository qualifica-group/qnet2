/**
 * Permission catalogue (English): ability verbs, assignable module names and
 * the Role form's permission explorer UI strings (spec 0076). Extracted from
 * `en.ts` to keep it within the engineering size limits (see
 * `.claude/rules/engineering.md` §6), mirror of `it-permissions.ts`.
 *
 * `resources` keys match the real permission prefixes
 * (`backend/config/authorization.php`, `AssignablePermissionCatalogue`), read
 * as a direct lookup by `resourceLabel()`/`abilityLabel()`
 * (`features/roles/permission-labels.ts`): no extra client-side mapping. Names
 * reuse verbatim the labels already chosen in `en-navigation.ts` for the same
 * module, to avoid two different names for the same thing across the UI.
 */

export const permissions = {
  abilities: {
    viewAny: 'View list',
    view: 'View',
    create: 'Create',
    update: 'Edit',
    delete: 'Delete',
    export: 'Export',
    import: 'Import',
    viewActivity: 'View activity',
    // Actions beyond BasePolicy's CRUD/export/import/viewActivity, exposed
    // only by a few policies (e.g. ContractPolicy, RequestManagementPolicy).
    validate: 'Validate',
    terminate: 'Terminate',
    program: 'Program',
    changeStatus: 'Change status',
    reactivate: 'Reopen',
    viewAll: 'View all',
    // Spec 0105: Request Management's third visibility tier — the requests of
    // the actor's own Sedi, with no need to manage them.
    viewSite: 'View by site',
    // Spec 0165: Enrollee Management only — the enrollees of the actor's
    // physical Sede (not the remote ones), on top of those they operate.
    viewPrimarySite: 'View physical site',
    viewDocuments: 'View documents',
    // Beyond BasePolicy's CRUD: the supervisory act of assigning the GA2
    // Operator at creation (user directive 2026-07-29).
    assignOperator: 'Assign operator',
    appendTeamMember: 'Add to team',
    impersonate: 'Impersonate',
    // Non-canonical abilities introduced by spec 0078: `manage` gates
    // approve/reject on a field-change-request (FieldChangeRequestPolicy);
    // `updateSource` gates writing request-management's protected "Fonte"
    // field directly on any of its three write channels
    // (RequestManagementPolicy, generated as `request-management.updateSource`).
    manage: 'Manage',
    updateSource: 'Edit the Source',
    // Spec 0116: the Task record-role matrix. `manageAll` is the "gestore"
    // permission (D-2, distinct from `viewAll`); `complete`/`block` gate the
    // domain actions of the same name; `validate` reuses the ability already
    // declared above for contracts.
    manageAll: 'Manage all',
    complete: 'Complete',
    block: 'Block',
    // Spec 0126 D-2: lets an actor delete other users' notes (NotePolicy::abilities()).
    deleteAny: 'Delete others\' notes',
    // Spec 0158 D-3: publish a filter view as shared (`table-filter-views.publish`).
    publish: 'Publish shared filters',
    // Spec 0175 D-14: the work order's Email tab (history) and send composer,
    // on top of BasePolicy's CRUD (`work-orders.viewEmails`/`sendEmail`).
    viewEmails: 'View emails',
    sendEmail: 'Send email',
    revealCardNumber: 'Reveal card number',
    collect: 'Record collections',
    // Spec 0243: registered active invoices.
    downgrade: 'Correct/undo registration',
    // Spec 0211: passive invoices.
    pay: 'Record payments',
    // Spec 0213: commission statements.
    issue: 'Issue',
    cancel: 'Cancel',
    adjust: 'Manage adjustments',
    // Spec 0208: purchase requests (RDA).
    fulfill: 'Fulfil lines (order, receive)',
    manageStatuses: 'Manage any line status',
    deleteLine: 'Delete lines',
    close: 'Close',
    // Spec 0210: purchase orders (ODA).
    approve: 'Approve',
    merge: 'Merge',
    send: 'Send to supplier',
    // Spec 0254: receipts and residuals of ODA lines.
    receive: 'Register receipts',
    // Spec 0201 D-10: work order Contract data tab and per-line payment management.
    viewContractData: 'View contract data',
    viewInvoices: 'View proforma and invoices',
    managePayments: 'Manage payments',
    manageCompensation: 'Manage compensation data',
    // Spec 0279: absence calendar, three visibility levels.
    calendarOwn: 'Calendar: own only',
    calendarAll: 'Calendar: everyone',
  },
  resources: {
    users: 'Users',
    roles: 'Roles',
    // Sub-entities not assignable from the Role form (governed by the parent
    // module's field-permission matrix), but still present in the DB catalogue.
    addresses: 'Addresses',
    contacts: 'Contacts',
    personal_data: 'Personal data',
    // Permission-only modules with no menu entry of their own (`shared` area).
    attachments: 'Attachments',
    notes: 'Notes',
    attributes: 'Attributes',
    'business-functions': 'Business Functions',
    campaigns: 'Campaigns',
    'commission-configurations': 'Commission Configurator',
    companies: 'Companies',
    'company-sites': 'Company Sites',
    'contract-statuses': 'Contract Statuses',
    contracts: 'Contracts',
    'custom-fields': 'Custom Fields',
    'document-layouts': 'Layouts',
    // Spec 0175: supporting tables of the work order's email composer.
    'email-templates': 'Email templates',
    'document-bundles': 'Document bundles',
    // Spec 0130: validated/closed-won subset of Request Management, own permissions.
    'enrollee-management': 'Enrollee Management',
    'field-change-requests': 'Change Requests',
    leads: 'Leads',
    'operational-sites': 'Operational Sites',
    opportunities: 'Opportunities',
    'payment-methods': 'Payment Methods',
    'pipeline-statuses': 'Project/Campaign Statuses',
    'product-categories': 'Product Categories',
    products: 'Products',
    projects: 'Projects',
    'quote-workflows': 'Offer status configurator',
    quotes: 'Quotes',
    'referent-types': 'Referent Types',
    referents: 'Referents',
    registries: 'Registries',
    'request-management': 'Request Management',
    'reward-statuses': 'Reward Statuses',
    'work-order-payment-statuses': 'Work order payment statuses',
    'reward-types': 'Vouchers, Rewards and Incentives',
    'rewarded-referents': 'Rewarded Referents',
    sectors: 'Sectors',
    sources: 'Sources',
    tags: 'Tags',
    'units-of-measure': 'Units of Measure',
    'financial-accounts': 'Financial accounts',
    'proforma-requests': 'Proforma requests',
    'purchase-requests': 'Purchase requests (RDA)',
    'purchase-orders': 'Purchase orders (ODA)',
    'cost-types': 'Cost types',
    'contract-types': 'Contract types',
    'job-roles': 'Job roles',
    'job-activities': 'Activities',
    'org-chart': 'Org chart',
    'staff-trainings': 'Training',
    'staff-courses': 'Courses',
    'user-skills': 'Skills',
    skills: 'Skills catalogue',
    stock: 'Equipment',
    'purchase-request-priorities': 'RDA priorities',
    'purchase-order-statuses': 'ODA statuses',
    'purchase-request-line-statuses': 'RDA line statuses',
    'invoice-tags': 'Invoice tags',
    invoices: 'Active invoices',
    'invoice-installments': 'Due dates',
    ledger: 'Ledger',
    'purchase-invoices': 'Passive invoices',
    'purchase-invoice-installments': 'Payable installments',
    // Spec 0212: agent commissions.
    'agent-fiscal-profiles': 'Agent fiscal profiles',
    'commission-accruals': 'Commission accruals',
    'commission-settlements': 'Commission statements',
    'enasarco-parameters': 'ENASARCO parameters',
    'product-typologies': 'Product Typologies',
    // Task module and its five configurators (spec 0101).
    tasks: 'Tasks',
    'task-statuses': 'Task Statuses',
    'task-types': 'Task Types',
    'task-categories': 'Task Categories',
    'task-priorities': 'Task Priorities',
    'task-importances': 'Task Importances',
    'task-templates': 'Task Templates',
    'vat-rates': 'VAT',
    // Spec 0158: permission-only module, cross-cutting every table (custom filters/saved views).
    'table-filter-views': 'Saved filters',
    // Permission-only: see the legacy old id on grids and record details (`legacy-ids.view`).
    'legacy-ids': 'Legacy IDs',
  },
  areas: {
    // Trailing area of the tree: permission-only modules that are assignable
    // but have no menu entry of their own (`notes`, `attachments` — spec 0076).
    shared: 'Shared',
  },
}

/** UI strings of the Role form's two-panel permission explorer (spec 0076). */
export const permissionExplorer = {
  searchPlaceholder: 'Search modules or permissions…',
  searchLabel: 'Search the permission catalogue',
  searchEmpty: 'No module or permission found.',
  actionsHeading: 'Actions',
  fieldsHeading: 'Fields',
  nativeFieldsLabel: 'Native',
  customFieldsLabel: 'Custom',
  // Selected/total counter, both per area/module and global.
  selectionCount: '{{selected}}/{{total}}',
  selectAllArea: 'Select area',
}
