import { useTranslation } from 'react-i18next'
import { useFormContext, type Control } from 'react-hook-form'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { CompetenceLinesField } from '@/features/product-lines/competence-lines-field'
import type { KnownProductLine } from '@/features/product-lines/types'
import { DurationInput } from '@/features/users/duration-input'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { toFieldRefs } from '@/features/users/user-record'
import {
  QUALIFICATION_TYPES,
  RELATIONSHIP_TYPES,
  type EmploymentRelationRef,
  type QualificationType,
  type RelationshipType,
} from '@/features/users/types'
import type { UserFormValues } from '@/features/users/use-user-form'

/*
 * The employment profile's fields (spec 0015) and the assignment configuration
 * (competence + Sedi), one component each, shared by the detail's in-place
 * rows and the create draft's rows (spec 0222).
 */

/** Radix `Select` cannot hold an empty-string value: "no selection" uses this sentinel. */
const NONE_VALUE = '__none__'

interface FieldProps {
  control: Control<UserFormValues>
}

/**
 * "Competente per tutte le categorie" (spec 0129 D-1/D-2): turning it on
 * clears the competence rows — one state only, no dormant rows under a true
 * flag — which is why its row opens only when the rows are editable too.
 */
export function UserCoversAllField({ control }: FieldProps) {
  const { t } = useTranslation()
  const { setValue } = useFormContext<UserFormValues>()
  return (
    <MetaField
      control={control}
      name="employment.covers_all_product_categories"
      metaKey="employment.covers_all_product_categories"
      layout="inline"
      label={t('users.form.employment.coversAllProductCategories')}
      description={<FormDescription>{t('users.form.employment.coversAllProductCategoriesDescription')}</FormDescription>}
    >
      {({ field, disabled }) => (
        <FormControl>
          <Switch
            checked={field.value}
            onCheckedChange={(checked) => {
              field.onChange(checked)
              if (checked) {
                setValue('employment.product_lines', [], { shouldDirty: true })
              }
            }}
            disabled={disabled}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

/** "Competenze" (spec 0111/0129): funzione aziendale -> categoria prodotto rows, labelled from the known pairs. */
export function UserCompetenceField({ control, knownLines }: FieldProps & { knownLines: KnownProductLine[] }) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
      name="employment.product_lines"
      metaKey="employment.product_lines"
      label={t('users.form.employment.productLines')}
      hint={t('users.form.employment.productLinesHint')}
    >
      {({ field, disabled }) => (
        <CompetenceLinesField value={field.value} onChange={field.onChange} knownLines={knownLines} disabled={disabled} />
      )}
    </MetaField>
  )
}

/** "Sede fisica" (spec 0103 D-3): at most one. */
export function UserPrimarySiteField({ control, selected }: FieldProps & { selected: EmploymentRelationRef | null }) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="employment.primary_operational_site_id"
      metaKey="employment.primary_operational_site_id"
      label={t('users.form.employment.primaryOperationalSite')}
      hint={t('users.assignment.sitesHint')}
      resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('users.form.employment.primaryOperationalSiteSearch')}
      selected={selected ? { id: selected.id, name: selected.label } : null}
      placeholder={t('users.form.employment.primaryOperationalSitePlaceholder')}
      emptyLabel={t('users.form.employment.primaryOperationalSiteEmpty')}
      errorLabel={t('users.form.employment.primaryOperationalSiteError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}

/** "Sedi remote" (spec 0103 D-1): operative exactly like the physical one. */
export function UserRemoteSitesField({ control, selected }: FieldProps & { selected: EmploymentRelationRef[] }) {
  const { t } = useTranslation()
  return (
    <RelationMultiSelectField
      control={control}
      name="employment.remote_operational_site_ids"
      metaKey="employment.remote_operational_site_ids"
      label={t('users.form.employment.remoteOperationalSites')}
      resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('users.form.employment.remoteOperationalSitesSearch')}
      selected={toFieldRefs(selected)}
      placeholder={t('users.form.employment.remoteOperationalSitesPlaceholder')}
      emptyLabel={t('users.form.employment.remoteOperationalSitesEmpty')}
      errorLabel={t('users.form.employment.remoteOperationalSitesError')}
      removeLabel={t('users.form.employment.remoteOperationalSitesRemove')}
      retryLabel={t('common.retry')}
    />
  )
}

export function UserJobDescriptionField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <MetaField
      control={control}
      name="employment.job_description"
      metaKey="employment.job_description"
      label={t('users.form.employment.jobDescription')}
    >
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

/** "Riporta a" (spec 0166): zero or more managers; emptied whenever the person is a manager (D-5). */
export function UserReportsToField({ control, selected }: FieldProps & { selected: EmploymentRelationRef[] }) {
  const { t } = useTranslation()
  return (
    <RelationMultiSelectField
      control={control}
      name="employment.reports_to_ids"
      metaKey="employment.reports_to_ids"
      label={t('users.form.employment.reportsTo')}
      resource={USERS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('users.form.employment.reportsToSearch')}
      selected={toFieldRefs(selected)}
      showAvatar
      placeholder={t('users.form.employment.reportsToPlaceholder')}
      emptyLabel={t('users.form.employment.reportsToEmpty')}
      errorLabel={t('users.form.employment.reportsToError')}
      removeLabel={t('users.form.employment.reportsToRemove')}
      retryLabel={t('common.retry')}
    />
  )
}

interface EnumFieldProps<TValue extends string> extends FieldProps {
  name: 'employment.relationship_type' | 'employment.qualification_type'
  label: string
  noneLabel: string
  options: readonly TValue[]
  enumKey: 'relationship_type' | 'qualification_type'
}

/** "Tipo rapporto" / "Qualifica": a closed list with an explicit "none". */
export function UserEnumField<TValue extends RelationshipType | QualificationType>({
  control,
  name,
  label,
  noneLabel,
  options,
  enumKey,
}: EnumFieldProps<TValue>) {
  const { t } = useTranslation()
  return (
    <MetaField control={control} name={name} metaKey={name} label={label}>
      {({ field, disabled }) => (
        <Select
          value={field.value ?? NONE_VALUE}
          onValueChange={(next) => field.onChange(next === NONE_VALUE ? null : next)}
          disabled={disabled}
        >
          <FormControl>
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
          </FormControl>
          <SelectContent>
            <SelectItem value={NONE_VALUE}>{noneLabel}</SelectItem>
            {options.map((option) => (
              <SelectItem key={option} value={option}>
                {t(`enums.${enumKey}.${option}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
  )
}

export function UserRelationshipTypeField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <UserEnumField
      control={control}
      name="employment.relationship_type"
      label={t('users.form.employment.relationshipType')}
      noneLabel={t('users.form.employment.relationshipTypeNone')}
      options={RELATIONSHIP_TYPES}
      enumKey="relationship_type"
    />
  )
}

export function UserQualificationField({ control }: FieldProps) {
  const { t } = useTranslation()
  return (
    <UserEnumField
      control={control}
      name="employment.qualification_type"
      label={t('users.form.employment.qualificationType')}
      noneLabel={t('users.form.employment.qualificationTypeNone')}
      options={QUALIFICATION_TYPES}
      enumKey="qualification_type"
    />
  )
}

/** "Azienda": the employing company. */
export function UserCompanyField({ control, selected }: FieldProps & { selected: EmploymentRelationRef | null }) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="employment.company_id"
      metaKey="employment.company_id"
      label={t('users.form.employment.company')}
      resource={COMPANIES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('users.form.employment.companySearch')}
      selected={selected ? { id: selected.id, name: selected.label } : null}
      placeholder={t('users.form.employment.companyPlaceholder')}
      emptyLabel={t('users.form.employment.companyEmpty')}
      errorLabel={t('users.form.employment.companyError')}
      clearLabel={t('common.clear')}
      retryLabel={t('common.retry')}
    />
  )
}

/** "Data assunzione" / "Data cessazione": date inputs hold `''` for "empty". */
export function UserDateField({
  control,
  name,
  label,
}: FieldProps & { name: 'employment.hired_at' | 'employment.terminated_at'; label: string }) {
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

/** "Orario giornaliero" / "Pausa": a daily duration in minutes, typed as `H:MM`. */
export function UserDurationField({
  control,
  name,
  label,
}: FieldProps & { name: 'employment.standard_daily_minutes' | 'employment.break_daily_minutes'; label: string }) {
  return (
    <MetaField control={control} name={name} metaKey={name} label={label}>
      {({ field, disabled }) => (
        <FormControl>
          <DurationInput value={field.value} onChange={field.onChange} disabled={disabled} label={label} />
        </FormControl>
      )}
    </MetaField>
  )
}
