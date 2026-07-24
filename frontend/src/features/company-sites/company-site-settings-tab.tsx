import { Building2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { toRelationFieldRef } from '@/components/form/relation-field-ref'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import type { CompanySiteFormValues } from '@/features/company-sites/use-company-site-form'

interface SettingsTabContentProps {
  control: Control<CompanySiteFormValues>
  selectedCompanyItem: ForSelectItem | null
}

/**
 * Impostazioni tab: the owning company. The ERP-specific fields (responsibles,
 * document progressives, quotation layout/header/footer) were de-verticalized
 * to company-sites custom fields (spec de-verticalization point 1) and now
 * render via the generic `<CustomFieldsSection>` in the Profilo tab. The
 * preferred bank is a per-row flag in the Banche tab, not a field here.
 */
export function SettingsTabContent({ control, selectedCompanyItem }: SettingsTabContentProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={Building2}
      title={t('companySites.form.sections.company.title')}
      description={t('companySites.form.sections.company.description')}
    >
      <RelationSelectField
        control={control}
        name="company_id"
        metaKey="company_id"
        label={t('companySites.form.company')}
        resource={COMPANIES_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('companySites.form.companySearch')}
        selected={toRelationFieldRef(selectedCompanyItem)}
        placeholder={t('companySites.form.companyPlaceholder')}
        emptyLabel={t('companySites.form.companyEmpty')}
        errorLabel={t('companySites.form.companyError')}
        clearLabel={t('common.clear')}
        retryLabel={t('common.retry')}
      />
    </FormSection>
  )
}
