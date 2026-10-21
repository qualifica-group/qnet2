/**
 * User employment profile strings (spec 0015: Profile / Contract / Contract
 * data sections). Extracted from `en.ts` to keep that file within the engineering
 * size limits (see `.claude/rules/engineering.md` §6); merged into the
 * `users.*` namespace from there.
 */

/** Section titles of the three employment sections (spec 0222: the record sections carry no description). */
export const usersFormEmploymentSections = {
  profile: { title: 'Profile' },
  contract: { title: 'Contract' },
  contractHistory: { title: 'Contract history' },
  payment: { title: 'Payment' },
  contractData: { title: 'Contract data' },
}

/** Field labels, placeholders and validation messages for `employment.*`. */
export const usersFormEmployment = {
  jobRoles: 'Job roles',
  jobRolesPlaceholder: 'Select job roles…',
  jobRolesSearch: 'Search job roles…',
  jobRolesEmpty: 'No job roles found.',
  jobRolesError: 'Unable to load job roles.',
  jobRolesRemove: 'Remove job role',
  businessFunctions: 'Business functions of membership',
  businessFunctionsPlaceholder: 'Select the functions (required for an active person)…',
  businessFunctionsSearch: 'Search business functions…',
  businessFunctionsEmpty: 'No business function found.',
  businessFunctionsError: 'Unable to load the business functions.',
  businessFunctionsRemove: 'Remove business function',
  managedBusinessFunctions: 'Manager of functions',
  managedBusinessFunctionsDescription: 'If the function already has a manager, it passes to this person.',
  managedBusinessFunctionsPlaceholder: 'Select the functions they manage…',
  managedBusinessFunctionsSearch: 'Search business functions…',
  managedBusinessFunctionsEmpty: 'No business function found.',
  managedBusinessFunctionsError: 'Unable to load the business functions.',
  managedBusinessFunctionsRemove: 'Remove managed function',
  reportsTo: 'Reports to',
  reportsToPlaceholder: 'Select managers…',
  reportsToSearch: 'Search users…',
  reportsToEmpty: 'No users found.',
  reportsToError: 'Unable to load users.',
  reportsToRemove: 'Remove manager',
  isAssignable: 'Assignable',
  isAssignableDescription: 'When off, the person receives no assignment at all (Leads, Request Management, Members, import), even with a site and an assignment scope.',
  coversAllProductCategories: 'Scope on all categories',
  coversAllProductCategoriesDescription: 'This person’s assignment scope covers any product category, regardless of business function: the rows below disappear and are cleared.',
  productLines: 'Assignment scope',
  productLinesHint: 'Each row pairs a business function with a product category, sub-categories included: check "All" to cover every category of that function, or pick a container category (e.g. "Training") to cover it and its children. With neither a row nor the flag above they receive no assignment.',
  productLineIncomplete: 'Each row requires both a business function and a product category (or "All").',
  primaryOperationalSite: 'Physical site',
  primaryOperationalSitePlaceholder: 'Select a physical site…',
  primaryOperationalSiteSearch: 'Search operational sites…',
  primaryOperationalSiteEmpty: 'No operational sites found.',
  primaryOperationalSiteError: 'Unable to load operational sites.',
  remoteOperationalSites: 'Remote sites',
  remoteOperationalSitesPlaceholder: 'Select one or more remote sites…',
  remoteOperationalSitesSearch: 'Search operational sites…',
  remoteOperationalSitesEmpty: 'No operational sites found.',
  remoteOperationalSitesError: 'Unable to load operational sites.',
  remoteOperationalSitesRemove: 'Remove operational site',
  hiredAt: 'Hired at',
  terminatedAt: 'Terminated at',
  standardDailyMinutes: 'Standard daily duration',
  breakDailyMinutes: 'Daily break duration',
  terminatedBeforeHiredAt: 'Termination date must be on or after the hire date.',
}

/** New grid columns for the users table (spec 0015). */
export const usersColumnsEmployment = {
  business_function: 'Business function',
  company: 'Company',
  operational_site: 'Operational site',
  contract_type: 'Contract type',
  relationship_type: 'Relationship type',
  employment_status: 'Status',
  managed_business_functions: 'Manager of functions',
  reports_to: 'Reports to',
  hired_at: 'Hired at',
  terminated_at: 'Terminated at',
}

/** Read-only detail labels for the employment sections (spec 0015). */
export const usersDetailEmployment = {
  functionManager: 'Manager',
  jobRoles: 'Job roles',
  businessFunctions: 'Business functions of membership',
  managedBusinessFunctions: 'Manager of functions',
  managedBusinessFunctionsEmpty: 'Not the manager of any function.',
  reportsTo: 'Reports to',
  productLines: 'Assignment scope',
  contractType: 'Contract type',
  relationshipType: 'Relationship type',
  company: 'Company',
  primaryOperationalSite: 'Physical site',
  remoteOperationalSites: 'Remote sites',
  hiredAt: 'Hired at',
  terminatedAt: 'Terminated at',
  standardDailyMinutes: 'Standard daily duration',
  breakDailyMinutes: 'Daily break duration',
  hourlyCost: 'Hourly cost',
  hourlyCostPerHour: '/h',
  hourlyCostHint: 'Derived from the salary of the contract condition in force today.',
  collectiveAgreementLevel: 'Collective agreement and level',
  contractStartsOn: 'In force since',
  contractReadOnlyHint: 'Shows the condition in force today: change it from the contract history.',
  terminatedAtHint: 'On the termination date the person is deactivated; their data stays on file.',
  iban: 'IBAN',
  ibanHolder: 'Account holder',
  contractHistory: {
    add: 'New condition',
    createNote: 'The contract history is filled in after the user is saved.',
    period: 'From {{from}} to {{to}}',
    ongoing: 'ongoing',
    current: 'Current',
    reason: 'Reason',
    delete: 'Delete last condition',
    deleteConfirm: 'Delete the condition starting {{date}}? You can then enter a new one.',
    created: 'Contract condition added.',
    deleted: 'Contract condition deleted.',
    loadError: 'Unable to load the contract history.',
    forbidden: 'You do not have permission for this action.',
    notLast: 'Only the last condition can be deleted.',
    genericError: 'Action failed. Try again.',
    periods: { monthly: 'month', yearly: 'year', hourly: 'hour' },
    form: {
      startsOn: 'Start date',
      startsOnRequired: 'Enter the start date.',
      relationshipType: 'Relationship type',
      contractType: 'Contract type',
      contractTypePlaceholder: 'Select the contract type…',
      contractTypeSearch: 'Search contract type…',
      contractTypeEmpty: 'No contract type found.',
      contractTypeError: 'Unable to load contract types.',
      contractTypeRequired: 'Select the contract type.',
      company: 'Company',
      companyPlaceholder: 'Select a company…',
      companySearch: 'Search companies…',
      companyEmpty: 'No company found.',
      companyError: 'Unable to load companies.',
      level: 'Collective agreement and level',
      levelPlaceholder: 'Select the level…',
      levelSearch: 'Search agreement or level…',
      levelEmpty: 'No level found.',
      levelError: 'Unable to load levels.',
      salaryAmount: 'Salary',
      salaryPeriod: 'Period',
      salaryPeriodPlaceholder: 'Choose…',
      salaryInvalid: 'Invalid amount.',
      salaryPeriodRequired: 'Enter the salary period.',
      changeReason: 'Reason',
      save: 'Save',
    },
  },
  none: 'None',
}

/**
 * Assignment configuration (user directive 2026-09-11): assignment scope + Sedi, the
 * two halves the server intersects to decide who may receive a record. Shared
 * by the form and the scheda so the two surfaces cannot tell the rule
 * differently.
 */
export const usersAssignment = {
  title: 'Assignment configuration',
  sitesHint: 'Physical and remote sites weigh the same for assignment: what counts is belonging to the site, not how the person works there.',
  assignable: 'Assignable',
  notAssignable: 'Not assignable',
  /** Spec 0129 D-1: shown instead of the count/list while the jolly flag is on. */
  allCategories: 'All categories',
  chips: {
    competence: 'Assignment scopes',
    physicalSite: 'Physical site',
    remoteSites: 'Remote sites',
  },
  blockers: {
    disabled: 'Assignment turned off by the Assignable setting',
    competence: 'No assignment scope configured: without at least one business function + product category pair the person enters no assignment pool.',
    site: 'No operational site: without a physical or remote site the person enters no assignment pool.',
  },
  stats: {
    competence: 'Assignment scopes',
    sites: 'Sites',
    sitesBreakdown: '{{physical}} physical · {{remote}} remote',
    matching: 'Matching',
    blockers: {
      disabled: 'Assignment turned off',
      competence: 'Assignment scope missing',
      site: 'Site missing',
    },
  },
  summary: { title: 'Summary' },
}
