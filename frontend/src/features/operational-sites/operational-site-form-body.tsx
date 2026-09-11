import { MapPin } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { Form, FormControl } from '@/components/ui/form'
import {
  MAIN_COLUMN_CLASS,
  PANEL_GRID_CLASS,
  SIDE_COLUMN_CLASS,
} from '@/components/record-form/layout'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { OperationalSiteFormHeader } from '@/features/operational-sites/operational-site-form-header'
import { OperationalSiteFormSummary } from '@/features/operational-sites/operational-site-form-summary'
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import { useOperationalSiteForm } from '@/features/operational-sites/use-operational-site-form'
import type {
  OperationalSiteDetail,
  OperationalSiteFormMode,
} from '@/features/operational-sites/types'

interface OperationalSiteFormBodyProps {
  mode: OperationalSiteFormMode
  onSuccess: (operationalSite: OperationalSiteDetail) => void
  onCancel: () => void
}

/**
 * Field-permission keys backing the geo cascade (spec 0004/0011 meta
 * contract). `GeoSelect` renders the four levels as a single reusable visual
 * unit (country → region → province → comune), so — unlike `line1`/
 * `postal_code`, each wrapped in its own `MetaField` — their visibility/edit
 * state is aggregated here rather than gated per level: the cascade is not
 * worth rendering unless at least one level is visible, and it is locked as a
 * whole the moment any level is not editable.
 */
const GEO_META_KEYS = ['country_id', 'state_id', 'province_id', 'city_id'] as const

/**
 * DOM id bridging the sticky header's save action to the RHF `<form>` below,
 * exactly as every other record form does: the same id serves the footer
 * actions, so both copies of the button submit this form without either of them
 * nesting the other.
 */
const OPERATIONAL_SITE_FORM_ID = 'operational-site-form'

/**
 * The operational-site create/edit form UI. `line1`/`postal_code` are wrapped
 * in `MetaField` (spec 0004): hidden fields are absent, non-editable fields
 * render disabled, `required` comes from the resolved `ResourcePermissions`
 * — no hardcoded permission logic lives here. The geo cascade reuses
 * `features/geo/geo-select` (never duplicated) and is bridged to RHF via
 * `useWatch`/`setValue`, mirroring `features/personal-data/address-form`. All
 * non-render logic lives in `useOperationalSiteForm`.
 *
 * Laid out as the TWIN of the Opportunità/anagrafiche forms (user directive
 * 2026-09-11): not a resemblance — the layout primitives are literally the same
 * objects (`@/components/record-form`), so the screens cannot drift apart with a
 * later edit to one of them.
 */
export function OperationalSiteFormBody({
  mode,
  onSuccess,
  onCancel,
}: OperationalSiteFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useOperationalSiteForm({ mode, onSuccess })

  const geoPermissions = GEO_META_KEYS.map((key) => fieldPermission(key))
  const geoVisible = geoPermissions.some((permission) => permission.visible)
  const geoDisabled = geoPermissions.some(
    (permission) => permission.disabled || !permission.editable,
  )

  const geoValue: GeoValue = {
    country_id: useWatch({ control: form.control, name: 'country_id' }) ?? null,
    state_id: useWatch({ control: form.control, name: 'state_id' }) ?? null,
    province_id: useWatch({ control: form.control, name: 'province_id' }) ?? null,
    city_id: useWatch({ control: form.control, name: 'city_id' }) ?? null,
  }

  const handleGeoChange = (next: GeoValue) => {
    form.setValue('country_id', next.country_id)
    form.setValue('state_id', next.state_id)
    form.setValue('province_id', next.province_id)
    form.setValue('city_id', next.city_id)
  }

  return (
    <div className="@container flex flex-1 flex-col overflow-y-auto bg-surface">
      <Form {...form}>
        <OperationalSiteFormHeader
          isEdit={mode.type === 'edit'}
          formId={OPERATIONAL_SITE_FORM_ID}
          isSubmitting={form.formState.isSubmitting}
          submitError={serverError}
          onCancel={onCancel}
        />

        <div className={PANEL_GRID_CLASS}>
          <aside className={SIDE_COLUMN_CLASS}>
            <OperationalSiteFormSummary
              control={form.control}
              persistedCity={mode.type === 'edit' ? mode.operationalSite.city : null}
            />
          </aside>

          <div className={MAIN_COLUMN_CLASS}>
            {/* `display: contents`: this native `<form>` only scopes the HTML
                submit boundary, it must not become an extra flex box. */}
            <form
              id={OPERATIONAL_SITE_FORM_ID}
              onSubmit={form.handleSubmit(onSubmit)}
              className="contents"
              noValidate
            >
              <FormSection
                icon={MapPin}
                title={t('operationalSites.form.sections.address.title')}
                description={t('operationalSites.form.sections.address.description')}
              >
                <MetaField
                  control={form.control}
                  name="alias"
                  metaKey="alias"
                  label={t('operationalSites.form.alias')}
                >
                  {({ field, disabled, readOnly }) => (
                    <FormControl>
                      <Input disabled={disabled} readOnly={readOnly} {...field} />
                    </FormControl>
                  )}
                </MetaField>

                {/* Street + postal code share the first row, the geo cascade
                    spans it: the comune now reads after the street, as in every
                    other address surface. */}
                <div className="grid gap-3 sm:grid-cols-3">
                  <MetaField
                    control={form.control}
                    name="line1"
                    metaKey="line1"
                    label={t('operationalSites.form.line1')}
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
                    name="postal_code"
                    metaKey="postal_code"
                    label={t('operationalSites.form.postalCode')}
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

                  {geoVisible && (
                    <div className="sm:col-span-3">
                      <GeoSelect
                        value={geoValue}
                        onChange={handleGeoChange}
                        disabled={geoDisabled}
                        layout="compact"
                      />
                    </div>
                  )}
                </div>
              </FormSection>

              <CustomFieldsSection resource="operational-sites" control={form.control} />

              {/* The same actions the identity bar carries, repeated where the
                  form ends: the operator finishes typing far from the sticky bar. */}
              <RecordFormActions
                formId={OPERATIONAL_SITE_FORM_ID}
                isSubmitting={form.formState.isSubmitting}
                submitLabel={t('operationalSites.form.save')}
                submittingLabel={t('operationalSites.form.saving')}
                cancel={{ label: t('operationalSites.form.cancel'), onCancel }}
              />
            </form>
          </div>
        </div>
      </Form>
    </div>
  )
}
