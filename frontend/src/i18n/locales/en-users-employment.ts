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
    description: 'Organizational role, job description and reporting line.',
  },
  contract: {
    title: 'Contract',
    description: 'Relationship type and employing company.',
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
  productLines: 'Competence',
  productLinesHint: 'Each row pairs a business function with a product category, sub-categories included: these are the competences this person can be assigned on. With no row at all they receive no assignment.',
  productLineIncomplete: 'Each row requires both a business function and a product category.',
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

/** Read-only detail labels for the employment sections (spec 0015). */
export const usersDetailEmployment = {
  isManager: 'Manager',
  jobDescription: 'Job description',
  reportsTo: 'Reports to',
  productLines: 'Competence',
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

/**
 * Assignment configuration (user directive 2026-09-11): competence + Sedi, the
 * two halves the server intersects to decide who may receive a record. Shared
 * by the form and the scheda so the two surfaces cannot tell the rule
 * differently.
 */
export const usersAssignment = {
  title: 'Assignment configuration',
  description: 'Competences and sites: they decide which offers can be matched to this person.',
  rule: 'A request reaches this person only when they belong to the request\'s site AND one of their competences covers the product category it requires.',
  sitesHint: 'Physical and remote sites weigh the same for assignment: what counts is belonging to the site, not how the person works there.',
  assignable: 'Assignable',
  notAssignable: 'Not assignable',
  chips: {
    competence: 'Competences',
    physicalSite: 'Physical site',
    remoteSites: 'Remote sites',
  },
  blockers: {
    competence: 'No competence configured: without at least one business function + product category pair the person enters no assignment pool.',
    site: 'No operational site: without a physical or remote site the person enters no assignment pool.',
  },
  stats: {
    competence: 'Competences',
    sites: 'Sites',
    sitesBreakdown: '{{physical}} physical · {{remote}} remote',
    matching: 'Matching',
    blockers: {
      competence: 'Competence missing',
      site: 'Site missing',
    },
  },
  summary: {
    title: 'Summary',
    description: 'Updated as you type.',
  },
}
