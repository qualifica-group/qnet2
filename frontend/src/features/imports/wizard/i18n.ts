import i18n from '@/i18n'
import { importWizard as importWizardEn } from '@/i18n/locales/en-import-wizard'
import { importWizard as importWizardIt } from '@/i18n/locales/it-import-wizard'
import {
  requestManagementLabelsEn,
  requestManagementLabelsIt,
  requestManagementWizardEn,
  requestManagementWizardIt,
} from '@/features/imports/wizard/i18n-request-management'

/**
 * Registers the `importWizard` i18next namespace as a side effect of
 * importing this module (spec 0033), mirroring `features/migrations/i18n.ts`:
 * `en.ts`/`it.ts` stay within the engineering size limits, and this module
 * never requires editing those two shared files. Imported once by
 * `import-wizard.tsx` (the feature's render entry point); ES module caching
 * makes the registration idempotent.
 */
i18n.addResourceBundle('en', 'importWizard', importWizardEn)
i18n.addResourceBundle('it', 'importWizard', importWizardIt)

// Request-management import (spec 0348): deep-merged so the lead strings above are untouched.
i18n.addResourceBundle('en', 'importWizard', requestManagementWizardEn, true, true)
i18n.addResourceBundle('it', 'importWizard', requestManagementWizardIt, true, true)
i18n.addResourceBundle('en', 'translation', requestManagementLabelsEn, true, true)
i18n.addResourceBundle('it', 'translation', requestManagementLabelsIt, true, true)
