import type { TFunction } from 'i18next'
import type { ApiModule, ApiModuleSection } from '@/features/api-integrations/api-modules'

export function moduleLabel(t: TFunction, module: ApiModule): string {
  return module.labelKey ? t(module.labelKey) : module.fallbackLabel
}

export function sectionLabel(t: TFunction, section: ApiModuleSection): string {
  return section.labelKey ? t(section.labelKey) : t('apiIntegrations.docs.otherSection')
}
