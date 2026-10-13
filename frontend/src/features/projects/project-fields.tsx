import { useTranslation } from 'react-i18next'
import { useFormState, useWatch, type Control, type UseFormSetValue } from 'react-hook-form'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { PROJECT_STATUSES_FOR_SELECT_RESOURCE } from '@/features/pipeline-statuses/for-select-api'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { PROJECT_GEO_FIELDS } from '@/features/projects/project-record'
import type { ProjectFormValues } from '@/features/projects/use-project-form'

/*
 * The project's fields, one component each, shared by the detail's in-place
 * rows and the create draft's rows (spec 0220).
 */

interface FieldProps {
  control: Control<ProjectFormValues>
}

interface SelectedFieldProps extends FieldProps {
  /** The current value's `{id, name}`, so the trigger never waits on a label lookup. */
  selected: RelationFieldRef | null
}

/** Geo cascade levels, positionally aligned with `PROJECT_GEO_FIELDS`, for the required markers. */
const GEO_LEVELS = ['country', 'state', 'province', 'city'] as const

function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

function useRelationLabels() {
  const { t } = useTranslation()
  return {
    placeholder: t('projects.form.selectPlaceholder'),
    emptyLabel: t('projects.form.selectEmpty'),
    errorLabel: t('projects.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }
}

/** "Codice" (spec 0025): editable on create only — the field permission makes it read-only afterwards. */
export function ProjectCodeField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
      name="code"
      metaKey="code"
      label={t('projects.form.code')}
      hint={t('projects.form.hints.code')}
      hintLabel={t('projects.form.code')}
    >
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            autoComplete="off"
            disabled={disabled}
            readOnly={readOnly}
            placeholder={t('projects.form.codePlaceholder')}
            {...field}
            value={field.value ?? ''}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

export function ProjectNameField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="name" metaKey="name" label={t('projects.form.name')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

export function ProjectDescriptionField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="description" metaKey="description" label={t('projects.form.description')}>
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

/** "Stato" (D-5): the pipeline status. */
export function ProjectStatusField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="pipeline_status_id"
      metaKey="pipeline_status_id"
      label={t('projects.form.status')}
      resource={PROJECT_STATUSES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('projects.form.statusSearch')}
      selected={selected}
      {...useRelationLabels()}
    />
  )
}

/** "Partner": a referente, not a user. */
export function ProjectPartnerField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="partner_id"
      metaKey="partner_id"
      label={t('projects.form.partner')}
      hint={t('projects.form.hints.partner')}
      resource={REFERENTS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('projects.form.partnerSearch')}
      selected={selected}
      {...useRelationLabels()}
    />
  )
}

/** "Sede": inherited as a prefill by every campaign/lead created under the project. */
export function ProjectOperationalSiteField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="operational_site_id"
      metaKey="operational_site_id"
      label={t('projects.form.operationalSite')}
      hint={t('projects.form.hints.operationalSite')}
      resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('projects.form.operationalSiteSearch')}
      selected={selected}
      {...useRelationLabels()}
    />
  )
}

/** "Righe di prodotto" (spec 0094/0132): the shared row editor. */
export function ProjectProductLinesField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name="product_lines" metaKey="product_lines" label={t('projects.form.productLines')}>
      {({ field, disabled }) => <ProductLinesField value={field.value} onChange={field.onChange} disabled={disabled} />}
    </MetaField>
  )
}

/**
 * The geo cascade (spec 0027 BR-4) as ONE editor: `GeoSelect` bridged to RHF
 * via `useWatch`/`setValue`, the client half of the hierarchy rule shown as
 * one message under it (the cascade exposes no per-level error slot).
 */
export function ProjectGeoField({ control, setValue }: FieldProps & { setValue: UseFormSetValue<ProjectFormValues> }) {
  const { field: fieldPermission } = useResourcePermissions()
  const { errors } = useFormState({ control })
  const permissions = PROJECT_GEO_FIELDS.map((key) => fieldPermission(key))
  const disabled = permissions.some((permission) => permission.disabled || !permission.editable)
  const requiredLevels = GEO_LEVELS.filter((_, index) => permissions[index].required)

  const value: GeoValue = {
    country_id: useWatch({ control, name: 'country_id' }) ?? null,
    state_id: useWatch({ control, name: 'state_id' }) ?? null,
    province_id: useWatch({ control, name: 'province_id' }) ?? null,
    city_id: useWatch({ control, name: 'city_id' }) ?? null,
  }

  const handleChange = (next: GeoValue) => {
    for (const key of PROJECT_GEO_FIELDS) {
      setValue(key, next[key], { shouldValidate: true, shouldDirty: true })
    }
  }

  const hierarchyError = errors.country_id?.message ?? errors.province_id?.message ?? errors.city_id?.message

  return (
    <div className="flex min-w-0 flex-col gap-2">
      <GeoSelect value={value} onChange={handleChange} disabled={disabled} requiredLevels={requiredLevels} />
      {hierarchyError ? (
        <p className="text-sm font-medium text-destructive" role="alert">
          {hierarchyError}
        </p>
      ) : null}
    </div>
  )
}

/** "Data inizio" / "Data fine": date inputs hold `''` for "empty". */
export function ProjectDateField({ control, name, label }: FieldProps & { name: 'start_date' | 'end_date'; label: string }) {
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

interface NumberFieldProps extends FieldProps {
  name: 'total_budget' | 'target_lead'
  label: string
  hint: string
  step: string
}

/** "Budget totale" / "Lead obiettivo". */
export function ProjectNumberField({ control, name, label, hint, step }: NumberFieldProps) {
  return (
    <MetaField control={control} name={name} metaKey={name} label={label} hint={hint} hintLabel={label}>
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
