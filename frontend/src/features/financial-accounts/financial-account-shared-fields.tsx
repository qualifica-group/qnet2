import { useTranslation } from 'react-i18next'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { FinancialAccountTextField } from '@/features/financial-accounts/financial-account-text-field'
import type { FinancialAccountFormValues } from '@/features/financial-accounts/financial-account-schema'
import type { NamedRef } from '@/features/financial-accounts/types'

interface FieldsProps {
  form: UseFormReturn<FinancialAccountFormValues>
}

interface CompanyFieldProps extends FieldsProps {
  /** The persisted company (edit mode), used to hydrate the picker label. */
  selected: NamedRef | null
}

/** Optional link to the parent company, common to the three account types. */
export function CompanyField({ form, selected }: CompanyFieldProps) {
  const { t } = useTranslation()

  return (
    <RelationSelectField
      control={form.control}
      name="company_id"
      metaKey="company_id"
      label={t('financialAccounts.form.company')}
      resource={COMPANIES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('financialAccounts.form.companySearch')}
      selected={selected}
      placeholder={t('financialAccounts.form.companyPlaceholder')}
      emptyLabel={t('financialAccounts.form.companyEmpty')}
      errorLabel={t('financialAccounts.form.companyError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}

/** Optional free-text notes, common to the three account types. */
export function NotesField({ form }: FieldsProps) {
  const { t } = useTranslation()

  return (
    <FinancialAccountTextField
      control={form.control}
      name="notes"
      label={t('financialAccounts.form.notes')}
      multiline
    />
  )
}

/** Street + postal code + geo cascade, for the types that carry an address (bank account, cash). */
export function AddressFields({ form }: FieldsProps) {
  const { t } = useTranslation()
  const { control, setValue } = form

  const geoValue: GeoValue = {
    country_id: useWatch({ control, name: 'country_id' }),
    state_id: useWatch({ control, name: 'state_id' }),
    province_id: useWatch({ control, name: 'province_id' }),
    city_id: useWatch({ control, name: 'city_id' }),
  }

  const handleGeoChange = (next: GeoValue) => {
    setValue('country_id', next.country_id, { shouldDirty: true })
    setValue('state_id', next.state_id, { shouldDirty: true })
    setValue('province_id', next.province_id, { shouldDirty: true })
    setValue('city_id', next.city_id, { shouldDirty: true })
  }

  return (
    <div className="grid gap-3 sm:grid-cols-3">
      <FinancialAccountTextField
        control={control}
        name="address_line"
        label={t('financialAccounts.form.addressLine')}
        autoComplete="address-line1"
        className="sm:col-span-2"
      />
      <FinancialAccountTextField
        control={control}
        name="postal_code"
        label={t('financialAccounts.form.postalCode')}
        autoComplete="postal-code"
      />
      <div className="sm:col-span-3">
        <GeoSelect value={geoValue} onChange={handleGeoChange} layout="compact" />
      </div>
    </div>
  )
}
