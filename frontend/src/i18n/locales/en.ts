/**
 * English is the source language. Every user-facing string lives here as the
 * canonical key set; other locales mirror this structure.
 *
 * Large, self-contained domains (`personalData`, `enums`) live in sibling
 * `en-*.ts` files to keep this file within the engineering size limits
 * (see `.claude/rules/engineering.md` §6); `en`'s public shape is unchanged.
 */
import { personalData, personalDataFieldLabels } from './en-personal-data'
import { enums } from './en-enums'
import { companies } from './en-companies'
import { companySites } from './en-company-sites'
import { operationalSites } from './en-operational-sites'
import { imports } from './en-imports'
import { activityLog } from './en-activity-log'
import { attachments } from './en-attachments'
import { exports } from './en-exports'
import { table } from './en-table'
import { referents, referentTypes } from './en-referents'
import { registries } from './en-registries'
import { attributes, productCategories, products } from './en-products'
import { customFields } from './en-custom-fields'
import { sectors } from './en-sectors'
import { sources } from './en-sources'
import { vatRates } from './en-vat-rates'
import { unitsOfMeasure } from './en-units-of-measure'
import { productTypologies } from './en-product-typologies'
import { paymentMethods } from './en-payment-methods'
import { tags } from './en-tags'
import { pipelineStatuses } from './en-pipeline-statuses'
import { projects } from './en-projects'
import { campaigns } from './en-campaigns'
import { leads } from './en-leads'
import { opportunities } from './en-opportunities'
import { productLines } from './en-product-lines'
import { quoteWorkflows } from './en-quote-workflows'
import { quotes } from './en-quotes'
import { contractStatuses } from './en-contract-statuses'
import { contracts } from './en-contracts'
import { workOrders } from './en-work-orders'
import { commissionConfigurations } from './en-commission-configurations'
import { rewardTypes } from './en-reward-types'
import { rewardStatuses } from './en-reward-statuses'
import { rewardedReferents } from './en-rewarded-referents'
import { fieldChangeRequests } from './en-field-change-requests'
import { documentLayouts } from './en-document-layouts'
import { navigation } from './en-navigation'
import { settings } from './en-settings'
import { requestManagement } from './en-request-management'
import { notes } from './en-notes'
import { notifications } from './en-notifications'
import { leadImports } from './en-lead-imports'
import { businessFunctions } from './en-business-functions'
import { moduleStats, statsPanel } from './en-stats'
import { impersonation } from './en-impersonation'
import { permissions, permissionExplorer } from './en-permissions'
import {
  usersColumnsEmployment,
  usersDetailEmployment,
  usersFormEmployment,
  usersFormEmploymentSections,
  usersFormTabs,
} from './en-users-employment'

export const en = {
  common: {
    loading: 'Loading…',
    retry: 'Retry',
    search: 'Search',
    notFound: 'Page not found',
    backToDashboard: 'Back to dashboard',
    comingSoon: 'This section is not available yet.',
    clear: 'Clear',
    confirm: 'Confirm',
    cancel: 'Cancel',
    confirmTitle: 'Are you sure?',
    yes: 'Yes',
    no: 'No',
    back: 'Back',
    edit: 'Edit',
    new: 'New',
    viewProfile: "View {{name}}'s profile",
    /** Label of the select a tab strip collapses into when the tabs no longer fit. */
    tabsSelectLabel: 'Section',
    /** Appended to the name when duplicating a record (row action "duplicate"); leading space by design. */
    copySuffix: ' (copy)',
  },
  config: {
    error: {
      title: 'Unable to start the application',
      description:
        "We couldn't load the application configuration. Check your connection and try again.",
      retry: 'Retry',
    },
  },
  navigation,
  theme: {
    toggle: 'Toggle theme',
    light: 'Light',
    dark: 'Dark',
    system: 'System',
  },
  actions: {
    view: 'View',
    edit: 'Edit',
    delete: 'Delete',
    duplicate: 'Duplicate',
    activity: 'Activity',
    convertToOpportunity: 'Convert to Opportunity',
    documents: 'Documents',
    notes: 'Notes',
    impersonate: 'Impersonate',
    layout: 'Attribute layout',
    generatePdf: 'Download quote',
    transferContact: 'Transfer contact',
  },
  table,
  // Strings of the generic statistics panel (spec 0026). The per-module widget
  // labels live under each module's own `stats` key, merged below.
  statsPanel,
  users: {
    stats: moduleStats.users,
    title: 'Users',
    subtitle: 'Browse, filter and manage the users of your application.',
    forbidden: "You don't have permission to view users.",
    columns: {
      id: 'ID',
      avatar: 'Avatar',
      name: 'Name',
      email: 'Email',
      roles: 'Roles',
      locale: 'Language',
      is_active: 'Active',
      created_at: 'Created at',
      user_type: 'Type',
      primary_address: 'Primary address',
      country: 'Country',
      region: 'Region',
      province: 'Province',
      city: 'City',
      primary_contact: 'Primary contacts',
      ...usersColumnsEmployment,
    },
    detail: {
      title: 'User details',
      subtitle: 'Read-only view of the selected user.',
      loadError: 'Unable to load the user. Please try again.',
      // Read-only Employment section (spec 0015).
      employment: usersDetailEmployment,
    },
    form: {
      tabs: usersFormTabs,
      newUser: 'New user',
      avatarLabel: 'Avatar',
      createTitle: 'Create user',
      createSubtitle: 'Add a new user to your application.',
      editTitle: 'Edit user',
      editSubtitle: 'Update the selected user.',
      name: 'Name',
      email: 'Email',
      locale: 'Language',
      roles: 'Roles',
      rolesPlaceholder: 'Select roles…',
      rolesSearch: 'Search roles…',
      rolesEmpty: 'No roles found.',
      rolesError: 'Unable to load roles.',
      rolesRemove: 'Remove role',
      is_active: 'Active',
      isActiveHint: 'An inactive account is denied login.',
      password: 'Password',
      newPassword: 'New password',
      confirmPassword: 'Confirm password',
      passwordEditHint: 'Leave blank to keep the current password.',
      save: 'Save',
      saving: 'Saving…',
      cancel: 'Cancel',
      created: 'User created successfully.',
      updated: 'User updated successfully.',
      deleted: 'User deleted successfully.',
      nameRequired: 'Name is required.',
      nameMax: 'Name must be at most 255 characters.',
      emailRequired: 'Email is required.',
      emailInvalid: 'Enter a valid email address.',
      passwordMinLength: 'Password must be at least 8 characters.',
      confirmPasswordRequired: 'Please confirm the password.',
      passwordsDontMatch: 'Passwords do not match.',
      genericError: 'Something went wrong. Please try again.',
      deleteError: 'Unable to delete the user. Please try again.',
      deleteForbidden: 'You cannot delete this user.',
      sections: {
        identity: {
          title: 'Personal details',
          description: 'Identifying details of the person or company.',
        },
        credentials: {
          title: 'Authentication',
          description: 'Sign-in credentials and interface language.',
        },
        access: {
          title: 'Roles & access',
          description: 'Assigned roles; permissions are inherited from the roles.',
        },
        contacts: {
          title: 'Contacts',
          description: 'Phone and email contact details.',
        },
        addresses: {
          title: 'Addresses',
          description: 'Registered offices and billing addresses.',
        },
        ...usersFormEmploymentSections,
      },
      // The personal-data card fields/sections (spec 0008), read by the role
      // field-permissions matrix (`fieldPermissionLabel('users', 'personal_data.*')`).
      personal_data: personalDataFieldLabels,
      // Employment profile fields (spec 0015): Profile/Contract/Contract data tabs.
      employment: usersFormEmployment,
    },
  },
  personalData,
  geo: {
    country: 'Country',
    state: 'Region',
    province: 'Province',
    city: 'City',
    countryPlaceholder: 'Select a country',
    statePlaceholder: 'Select a region',
    provincePlaceholder: 'Select a province',
    cityPlaceholder: 'Select a city',
    empty: 'No options available',
    error: 'Failed to load options.',
    search: 'Search',
    noMatch: 'No matches found',
    retry: 'Retry',
    // Derived geo scope (spec 0027 D-2): the finest level that is filled in.
    scope: {
      country: 'National',
      state: 'Regional',
      province: 'Provincial',
      city: 'City',
    },
  },
  roles: {
    title: 'Roles',
    subtitle: 'Browse, filter and manage the roles and their permissions.',
    forbidden: "You don't have permission to view roles.",
    columns: {
      id: 'ID',
      name: 'Name',
      permissions: 'Permissions',
      users_count: 'Users',
      created_at: 'Created at',
    },
    detail: {
      title: 'Role details',
      subtitle: 'Read-only view of the selected role.',
      loadError: 'Unable to load the role. Please try again.',
    },
    form: {
      newRole: 'New role',
      createTitle: 'Create role',
      createSubtitle: 'Add a new role and choose its permissions.',
      editTitle: 'Edit role',
      editSubtitle: 'Update the selected role and its permissions.',
      name: 'Name',
      permissions: 'Permissions',
      selectAll: 'Select all',
      selectAllGlobal: 'Select all permissions',
      noPermissions: 'No permissions are available to assign.',
      users: 'Members',
      usersPlaceholder: 'Select users…',
      usersSearch: 'Search users…',
      usersEmpty: 'No users found.',
      usersError: 'Unable to load users.',
      usersRemove: 'Remove member',
      save: 'Save',
      saving: 'Saving…',
      cancel: 'Cancel',
      created: 'Role created successfully.',
      updated: 'Role updated successfully.',
      deleted: 'Role deleted successfully.',
      nameRequired: 'Name is required.',
      nameMax: 'Name must be at most 255 characters.',
      genericError: 'Something went wrong. Please try again.',
      deleteError: 'Unable to delete the role. Please try again.',
      deleteForbidden: 'You cannot delete this role.',
      sections: {
        details: {
          title: 'Role details',
          description: 'Role name and the users it is assigned to.',
        },
        permissions: {
          title: 'Permissions',
          description:
            'Grouped by domain. Advanced permissions live in the dedicated configuration.',
        },
      },
      advanced: 'Advanced configuration',
      advancedActions: 'Additional actions',
    },
    // Per-role field-permission matrix (spec 0006): a DB-driven restriction
    // within the code security ceiling, editable from the role form.
    fieldPermissions: {
      title: 'Field permissions',
      visible: 'Visible',
      editable: 'Editable',
      required: 'Required',
      empty: 'No fields are available to configure.',
      loadError: 'Unable to load the field catalogue. Please try again.',
      mandatory: 'Required to create the record — cannot be restricted by a role.',
    },
    // Two-panel permission explorer (spec 0076): area/module tree + module detail.
    permissionExplorer,
  },
  companies: { ...companies, stats: moduleStats.companies },
  companySites: { ...companySites, stats: moduleStats.companySites },
  settings,
  notifications,
  avatar: {
    chooseImage: 'Choose image',
    removeAvatar: 'Remove',
    uploading: 'Uploading…',
    invalidImage: 'Please choose a JPEG, PNG, GIF or WebP image.',
    imageTooLarge: 'The image must be at most 10 MB.',
    avatarUploadError: 'Unable to update the avatar. Please try again.',
  },
  auth: {
    signInTitle: 'Sign in',
    brandClaim: 'Your work, in one place.',
    brandSupport:
      'Quotes, work orders and commissions in a single flow, from the first talk to delivery.',
    showPassword: 'Show password',
    hidePassword: 'Hide password',
    signInSubtitle: 'Enter your credentials to access your account.',
    email: 'Email',
    password: 'Password',
    signIn: 'Sign in',
    signingIn: 'Signing in…',
    signOut: 'Sign out',
    account: 'Account',
    invalidCredentials: 'Invalid email or password.',
    genericError: 'Something went wrong. Please try again.',
    emailRequired: 'Email is required.',
    emailInvalid: 'Enter a valid email address.',
    passwordRequired: 'Password is required.',
    forgotPasswordLink: 'Forgot password?',
    forgotPasswordTitle: 'Reset your password',
    forgotPasswordSubtitle: "Enter your email and we'll send you a reset link.",
    sendResetLink: 'Send reset link',
    sending: 'Sending…',
    resetLinkSent: 'If an account exists for that email, a reset link has been sent.',
    backToSignIn: 'Back to sign in',
    resetPasswordTitle: 'Set a new password',
    resetPasswordSubtitle: 'Choose a new password for your account.',
    newPassword: 'New password',
    confirmPassword: 'Confirm password',
    resetPasswordSubmit: 'Reset password',
    resetting: 'Resetting…',
    passwordResetSuccess: 'Your password has been reset. You can now sign in.',
    passwordsDontMatch: 'Passwords do not match.',
    passwordMinLength: 'Password must be at least 8 characters.',
    resetLinkInvalid: 'This reset link is invalid or has expired. Request a new one.',
    tooManyRequests: 'Too many requests. Please wait a moment and try again.',
  },
  // Metadata-driven authorization (spec 0004): shared strings used by `MetaField`
  // and any form consuming `useResourceMeta`/`ResourcePermissions`.
  authorization: {
    loadError: 'Unable to load permissions. Please try again.',
    fieldNotEditable: 'This field cannot be edited.',
    moreInfo: 'More information',
  },
  permissions,
  // Localized labels for backend domain enums (extracted to `en-enums.ts`).
  enums,
  businessFunctions,
  operationalSites: { ...operationalSites, stats: moduleStats.operationalSites },
  imports,
  activityLog,
  exports,
  referents: { ...referents, stats: moduleStats.referents },
  referentTypes,
  registries: { ...registries, stats: moduleStats.registries },
  attributes,
  customFields,
  productCategories: { ...productCategories, stats: moduleStats.productCategories },
  sectors,
  products: { ...products, stats: moduleStats.products },
  sources,
  vatRates,
  unitsOfMeasure,
  productTypologies,
  paymentMethods,
  tags,
  projects: { ...projects, stats: moduleStats.projects },
  pipelineStatuses,
  campaigns: { ...campaigns, stats: moduleStats.campaigns },
  leadImports,
  leads: { ...leads, stats: moduleStats.leads },
  opportunities: { ...opportunities, stats: moduleStats.opportunities },
  productLines,
  quoteWorkflows,
  quotes,
  contractStatuses,
  contracts,
  workOrders,
  commissionConfigurations,
  requestManagement,
  // Shared validation messages of the `attribute_values` dynamic map (spec
  // 0084): a neutral namespace reused by every context that collects it
  // (product/opportunity/quote) instead of one per domain.
  attributeValues: {
    validation: {
      required: 'This field is required.',
      enumInvalid: 'Select a valid option.',
    },
  },
  fieldChangeRequests,
  rewardTypes,
  rewardStatuses,
  rewardedReferents,
  notes,
  importRuns: { stats: moduleStats.importRuns },
  attachments,
  impersonation,
  documentLayouts,
}

export type TranslationResources = typeof en
