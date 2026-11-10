import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `financial-account-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: 'financial-accounts',
  basePath: '/financial-accounts',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.financialAccounts',
  // The record card renders its own Edit action, so the generic page header
  // must not stack a second button — the same registration Opportunita',
  // Utenti and Lead carry.
  detailOwnsEditAction: true,
}
