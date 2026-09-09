/**
 * User employment profile strings (spec 0015: Profile / Contract / Contract
 * data sections). Extracted from `en.ts` to keep that file within the engineering
 * size limits (see `.claude/rules/engineering.md` §6); merged into the
 * `users.*` namespace from there.
 */

/** `FormSection` header (title/description) for the three employment sections. */
export const usersFormEmploymentSections = {
  profile: {
    title: 'Profile',
    description: 'Organizational role, manager status and reporting line.',
  },
  contract: {
    title: 'Contract',
    description: 'Relationship type, company and operational site.',
  },
  contractData: {
    title: 'Contract data',
    description: 'Qualification, employment dates and daily durations.',
  },
}

/** Field labels, placeholders and validation messages for `employment.*`. */
export const usersFormEmployment = {
  isManager: 'Manager',
  isManagerDescription: 'This person manages other employees.',
  jobDescription: 'Job description',
  reportsTo: 'Reports to',
  reportsToPlaceholder: 'Select a manager…',
  reportsToSearch: 'Search users…',
  reportsToEmpty: 'No users found.',
  reportsToError: 'Unable to load users.',
  businessFunction: 'Business function',
  businessFunctionPlaceholder: 'Select a business function…',
  businessFunctionSearch: 'Search business functions…',
  businessFunctionEmpty: 'No business functions found.',
  businessFunctionError: 'Unable to load business functions.',
  productCategories: 'Product categories',
  productCategoriesPlaceholder: 'Select one or more product categories…',
  productCategoriesSearch: 'Search product categories…',
  productCategoriesEmpty: 'No product categories found.',
  productCategoriesError: 'Unable to load product categories.',
  productCategoriesRemove: 'Remove product category',
  relationshipType: 'Relationship type',
  relationshipTypeNone: 'None',
  company: 'Company',
  companyPlaceholder: 'Select a company…',
  companySearch: 'Search companies…',
  companyEmpty: 'No companies found.',
  companyError: 'Unable to load companies.',
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
  qualificationType: 'Qualification',
  qualificationTypeNone: 'None',
  hiredAt: 'Hired at',
  terminatedAt: 'Terminated at',
  standardDailyMinutes: 'Standard daily duration',
  breakDailyMinutes: 'Daily break duration',
  jobDescriptionMax: 'Job description must be at most 255 characters.',
  terminatedBeforeHiredAt: 'Termination date must be on or after the hire date.',
}

/** New grid columns for the users table (spec 0015). */
export const usersColumnsEmployment = {
  business_function: 'Business function',
  company: 'Company',
  operational_site: 'Operational site',
  relationship_type: 'Relationship type',
  qualification_type: 'Qualification',
  is_manager: 'Manager',
  reports_to: 'Reports to',
  hired_at: 'Hired at',
  terminated_at: 'Terminated at',
}

/** Read-only detail labels for the Employment section (spec 0015). */
export const usersDetailEmployment = {
  title: 'Employment',
  isManager: 'Manager',
  jobDescription: 'Job description',
  reportsTo: 'Reports to',
  businessFunction: 'Business function',
  relationshipType: 'Relationship type',
  company: 'Company',
  primaryOperationalSite: 'Physical site',
  remoteOperationalSites: 'Remote sites',
  qualificationType: 'Qualification',
  hiredAt: 'Hired at',
  terminatedAt: 'Terminated at',
  standardDailyMinutes: 'Standard daily duration',
  breakDailyMinutes: 'Daily break duration',
  none: 'None',
}
