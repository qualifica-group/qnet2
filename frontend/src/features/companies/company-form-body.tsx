import { Building2, MapPin } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { formatOnBlur } from '@/lib/formatting/format-on-blur'
import { formatVatNumber } from '@/lib/formatting/input-format'
import { Form, FormControl } from '@/components/ui/form'
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useCompanyForm } from '@/features/companies/use-company-form'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type { CompanyDetail } from '@/features/companies/types'
import type { CompanyFormMode } from '@/features/companies/company-form'

interface CompanyFormBodyProps {
  mode: CompanyFormMode
  onSuccess: (company: CompanyDetail) => void
  onCancel: () => void
}

/**
 * The company create/edit form UI. `denomination`/`vat_number` and every
 * address subfield are wrapped in `MetaField` (spec 0004): hidden fields are
 * absent, non-editable fields render disabled, `required` comes from the
 * resolved `ResourcePermissions` — no hardcoded permission logic lives here.
 * All non-render logic lives in `useCompanyForm`. The address is a single
 * embedded block (ADR 0010: one address per company, no add/remove
 * affordance), gated as a whole on the `address` field permission; its
 * `<GeoSelect>` bridge lives inline (single call site — no reusable wrapper).
 * `<CustomFieldsSection>` (spec 0021 pilot) mounts the resource's admin-defined
 * custom fields with zero companies-specific rendering/validation logic.
 */
export function CompanyFormBody({ mode, onSuccess, onCancel }: CompanyFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useCompanyForm({ mode, onSuccess })

  const generalVisible =
    fieldPermission('denomination').visible || fieldPermission('vat_number').visible
  const addressPermission = fieldPermission('address')
  const addressDisabled = addressPermission.disabled || !addressPermission.editable

  const geoValue: GeoValue = {
    country_id: useWatch({ control: form.control, name: 'address.country_id' }) ?? null,
    state_id: useWatch({ control: form.control, name: 'address.state_id' }) ?? null,
    province_id: useWatch({ control: form.control, name: 'address.province_id' }) ?? null,
    city_id: useWatch({ control: form.control, name: 'address.city_id' }) ?? null,
  }

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-col gap-4 p-4"
          noValidate
        >
          {generalVisible && (
            <FormSection
              icon={Building2}
              title={t('companies.form.sections.general.title')}
              description={t('companies.form.sections.general.description')}
            >
              <MetaField
                control={form.control}
                name="denomination"
                metaKey="denomination"
                label={t('companies.form.denomination')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      autoComplete="organization"
                      disabled={disabled}
                      readOnly={readOnly}
                      {...field}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="vat_number"
                metaKey="vat_number"
                label={t('companies.form.vatNumber')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      autoComplete="off"
                      disabled={disabled}
                      readOnly={readOnly}
                      {...field}
                      onBlur={formatOnBlur(field, formatVatNumber)}
                    />
                  </FormControl>
                )}
              </MetaField>
            </FormSection>
          )}

          {addressPermission.visible && (
            <FormSection
              icon={MapPin}
              title={t('companies.form.sections.address.title')}
              description={t('companies.form.sections.address.description')}
            >
              {/* Street + postal code share the first row; line2 and the geo
                  cascade span it. The column span sits on each MetaField so a
                  hidden field leaves no empty cell. */}
              <div className="grid gap-3 sm:grid-cols-3">
                <MetaField
                  control={form.control}
                  name="address.line1"
                  metaKey="address"
                  label={t('companies.form.line1')}
                  className="sm:col-span-2"
                >
                  {({ field, disabled, readOnly }) => (
                    <FormControl>
                      <Input
                        autoComplete="address-line1"
                        disabled={disabled}
                        readOnly={readOnly}
                        {...field}
                      />
                    </FormControl>
                  )}
                </MetaField>

                <MetaField
                  control={form.control}
                  name="address.postal_code"
                  metaKey="address"
                  label={t('companies.form.postalCode')}
                >
                  {({ field, disabled, readOnly }) => (
                    <FormControl>
                      <Input
                        autoComplete="postal-code"
                        disabled={disabled}
                        readOnly={readOnly}
                        {...field}
                      />
                    </FormControl>
                  )}
                </MetaField>

                <MetaField
                  control={form.control}
                  name="address.line2"
                  metaKey="address"
                  label={t('companies.form.line2')}
                  className="sm:col-span-3"
                >
                  {({ field, disabled, readOnly }) => (
                    <FormControl>
                      <Input
                        autoComplete="address-line2"
                        disabled={disabled}
                        readOnly={readOnly}
                        {...field}
                      />
                    </FormControl>
                  )}
                </MetaField>

                <div className="sm:col-span-3">
                  <GeoSelect
                    value={geoValue}
                    onChange={(next) => {
                      form.setValue('address.country_id', next.country_id)
                      form.setValue('address.state_id', next.state_id)
                      form.setValue('address.province_id', next.province_id)
                      form.setValue('address.city_id', next.city_id)
                    }}
                    disabled={addressDisabled}
                    layout="compact"
                  />
                </div>
              </div>
            </FormSection>
          )}

          <CustomFieldsSection resource="companies" control={form.control} />

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('companies.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('companies.form.saving')
                : t('companies.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
