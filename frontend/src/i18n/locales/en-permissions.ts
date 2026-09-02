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
    viewDocuments: 'View documents',
    // Beyond BasePolicy's CRUD: the supervisory act of assigning the GA2
    // Operator at creation (user directive 2026-07-29).
    assignOperator: 'Assign operator',
    impersonate: 'Impersonate',
    // Non-canonical abilities introduced by spec 0078: `manage` gates
    // approve/reject on a field-change-request (FieldChangeRequestPolicy);
    // `updateSource` gates writing request-management's protected "Fonte"
    // field directly on any of its three write channels
    // (RequestManagementPolicy, generated as `request-management.updateSource`).
    manage: 'Manage',
    updateSource: 'Edit the Source',
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
    'reward-types': 'Vouchers, Rewards and Incentives',
    'rewarded-referents': 'Rewarded Referents',
    sectors: 'Sectors',
    sources: 'Sources',
    tags: 'Tags',
    'units-of-measure': 'Units of Measure',
    'product-typologies': 'Product Typologies',
    'vat-rates': 'VAT',
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
