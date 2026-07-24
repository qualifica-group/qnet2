import i18n from '@/i18n'
import { attributeLayout as attributeLayoutEn } from '@/i18n/locales/en-attribute-layout'
import { attributeLayout as attributeLayoutIt } from '@/i18n/locales/it-attribute-layout'

/**
 * Registers the `attributeLayout` i18next namespace as a side effect of
 * importing this module (spec 0062), mirroring `features/migrations/i18n.ts`:
 * `en.ts`/`it.ts` are owned by other in-flight lanes and already sit near the
 * engineering file-size limit, so this domain registers itself as an
 * additional namespace instead of merging into the default `translation`
 * bundle. Imported once by the configurator's and the mount section's entry
 * components; ES module caching makes the registration idempotent.
 */
i18n.addResourceBundle('en', 'attributeLayout', attributeLayoutEn)
i18n.addResourceBundle('it', 'attributeLayout', attributeLayoutIt)
