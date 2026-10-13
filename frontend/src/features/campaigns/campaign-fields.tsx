import { useTranslation } from 'react-i18next'
import { useFormState, useWatch, type Control, type UseFormSetValue } from 'react-hook-form'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'
import type { GeoScope } from '@/features/geo/geo-scope'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { PROJECT_STATUSES_FOR_SELECT_RESOURCE } from '@/features/pipeline-statuses/for-select-api'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import { formatDecimal } from '@/features/products/column-renderers'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { CAMPAIGN_GEO_FIELDS } from '@/features/campaigns/campaign-record'
import { CampaignRelationField } from '@/features/campaigns/campaign-relation-field'
import { useCampaignProjectMeta } from '@/features/campaigns/use-campaign-project-meta'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'

/*
 * The campaign's fields, one component each, shared by the detail's in-place
 * rows and the create draft's rows (spec 0221).
 */

interface FieldProps {
  control: Control<CampaignFormValues>
}

interface SelectedFieldProps extends FieldProps {
  /** The current value's `{id, name}`, so the trigger never waits on a label lookup. */
  selected: RelationFieldRef | null
}

/** Only the country is required, and only while the linked project does not fill it (BR-4/BR-5). */
const COUNTRY_REQUIRED: ReadonlyArray<GeoScope> = ['country']
const NONE_REQUIRED: ReadonlyArray<GeoScope> = []

function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/** "Codice" (spec 0025): editable on create only — the field permission makes it read-only afterwards. */
export function CampaignCodeField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
      name="code"
      metaKey="code"
      label={t('campaigns.form.code')}
      hint={t('campaigns.form.hints.code')}
      hintLabel={t('campaigns.form.code')}
    >
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            autoComplete="off"
            disabled={disabled}
            readOnly={readOnly}
            placeholder={t('campaigns.form.codePlaceholder')}
            {...field}
            value={field.value ?? ''}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

export function CampaignNameField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="name" metaKey="name" label={t('campaigns.form.name')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

export function CampaignDescriptionField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="description" metaKey="description" label={t('campaigns.form.description')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Textarea
            disabled={disabled}
            readOnly={readOnly}
            value={field.value ?? ''}
            onChange={(event) => field.onChange(event.target.value || null)}
            onBlur={field.onBlur}
            name={field.name}
            ref={field.ref}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

/** "Stato": the campaign's own only while standalone (BR-2); a linked campaign reads the project's. */
export function CampaignStatusField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <CampaignRelationField
      control={control}
      name="pipeline_status_id"
      metaKey="pipeline_status_id"
      label={t('campaigns.form.status')}
      resource={PROJECT_STATUSES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('campaigns.form.statusSearch')}
      selected={selected}
    />
  )
}

/** "Partner": a referente, prefilled (never locked) by a Project pick. */
export function CampaignPartnerField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <CampaignRelationField
      control={control}
      name="partner_id"
      metaKey="partner_id"
      label={t('campaigns.form.partner')}
      hint={t('campaigns.form.hints.partner')}
      resource={REFERENTS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('campaigns.form.partnerSearch')}
      selected={selected}
    />
  )
}

/** "Sede": prefilled (never locked) by a Project pick, inherited in turn by the campaign's leads. */
export function CampaignSiteField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <CampaignRelationField
      control={control}
      name="operational_site_id"
      metaKey="operational_site_id"
      label={t('campaigns.form.operationalSite')}
      hint={t('campaigns.form.hints.operationalSite')}
      resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('campaigns.form.operationalSiteSearch')}
      selected={selected}
    />
  )
}

/** "Fonte" (spec 0176): the campaign's own, inherited by its leads as a prefill. */
export function CampaignSourceField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <CampaignRelationField
      control={control}
      name="source_id"
      metaKey="source_id"
      label={t('campaigns.form.source')}
      hint={t('campaigns.form.hints.source')}
      resource={SOURCES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('campaigns.form.sourceSearch')}
      selected={selected}
    />
  )
}

/**
 * "Righe di prodotto" (spec 0094/0132): the shared row editor. Required
 * whenever it can be edited at all: a linked campaign reads the project's
 * rows and never opens this editor (BR-2).
 */
export function CampaignProductLinesField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
      name="product_lines"
      metaKey="product_lines"
      label={t('campaigns.form.productLines')}
      required
    >
      {({ field, disabled }) => <ProductLinesField value={field.value} onChange={field.onChange} disabled={disabled} />}
    </MetaField>
  )
}

/**
 * The geo cascade (spec 0027 BR-4/BR-5) as ONE editor: `GeoSelect` bridged to
 * RHF, the levels the linked project fills locked (the row's section says
 * so), the client half of the hierarchy rule shown as one message under it.
 */
export function CampaignGeoField({ control, setValue }: FieldProps & { setValue: UseFormSetValue<CampaignFormValues> }) {
  const { field: fieldPermission } = useResourcePermissions()
  const { errors } = useFormState({ control })
  const disabled = CAMPAIGN_GEO_FIELDS.some((key) => {
    const permission = fieldPermission(key)
    return permission.disabled || !permission.editable
  })
  const lockedLevels = useWatch({ control, name: 'geo_locked_levels' })

  const value: GeoValue = {
    country_id: useWatch({ control, name: 'country_id' }) ?? null,
    state_id: useWatch({ control, name: 'state_id' }) ?? null,
    province_id: useWatch({ control, name: 'province_id' }) ?? null,
    city_id: useWatch({ control, name: 'city_id' }) ?? null,
  }

  const handleChange = (next: GeoValue) => {
    for (const key of CAMPAIGN_GEO_FIELDS) {
      setValue(key, next[key], { shouldValidate: true, shouldDirty: true })
    }
  }

  const hierarchyError = errors.country_id?.message ?? errors.province_id?.message ?? errors.city_id?.message

  return (
    <div className="flex min-w-0 flex-col gap-2">
      <GeoSelect
        value={value}
        onChange={handleChange}
        disabled={disabled}
        lockedLevels={lockedLevels}
        requiredLevels={lockedLevels.includes('country') ? NONE_REQUIRED : COUNTRY_REQUIRED}
      />
      {hierarchyError ? (
        <p className="text-sm font-medium text-destructive" role="alert">
          {hierarchyError}
        </p>
      ) : null}
    </div>
  )
}

/** "Data inizio" / "Data fine": date inputs hold `''` for "empty". */
export function CampaignDateField({ control, name, label }: FieldProps & { name: 'start_date' | 'end_date'; label: string }) {
  return (
    <MetaField control={control} name={name} metaKey={name} label={label}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input type="date" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

/**
 * BR-3: the linked project's residual budget under the campaign's own, so the
 * ceiling is known BEFORE the 422. Display-only: the backend stays the authority.
 */
function RemainingBudgetHint({ control }: FieldProps) {
  const { t } = useTranslation()
  const projectId = useWatch({ control, name: 'project_id' })
  const { data: meta } = useCampaignProjectMeta(projectId)

  if (projectId === null || !meta || meta.remaining_budget === null) {
    return null
  }
  return (
    <FormDescription>
      {t('campaigns.form.projectRemainingBudget', { amount: formatDecimal(meta.remaining_budget) })}
    </FormDescription>
  )
}

interface NumberFieldProps extends FieldProps {
  name: 'total_budget' | 'target_lead'
  label: string
  step: string
}

/** "Budget totale" (with the project's residual) / "Lead obiettivo". */
export function CampaignNumberField({ control, name, label, step }: NumberFieldProps) {
  return (
    <MetaField
      control={control}
      name={name}
      metaKey={name}
      label={label}
      description={name === 'total_budget' ? <RemainingBudgetHint control={control} /> : undefined}
    >
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            type="number"
            step={step}
            disabled={disabled}
            readOnly={readOnly}
            value={numberInputValue(field.value)}
            onChange={(event) => field.onChange(event.target.value === '' ? null : Number(event.target.value))}
            onBlur={field.onBlur}
            name={field.name}
            ref={field.ref}
          />
        </FormControl>
      )}
    </MetaField>
  )
}
