import { useTranslation } from 'react-i18next'
import { useFormContext, type Control } from 'react-hook-form'
import { RelationMultiSelectField } from '@/components/form/relation-multi-select-field'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { CompetenceLinesField } from '@/features/product-lines/competence-lines-field'
import type { KnownProductLine } from '@/features/product-lines/types'
import { DurationInput } from '@/features/users/duration-input'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { toFieldRefs } from '@/features/users/user-record'
import { JOB_ROLES_FOR_SELECT_RESOURCE } from '@/features/job-roles/for-select-api'
import type { EmploymentRelationRef } from '@/features/users/types'
import type { UserFormValues } from '@/features/users/use-user-form'

/*
 * The employment profile's fields (spec 0015) and the assignment configuration
 * (competence + Sedi), one component each, shared by the detail's in-place
 * rows and the create draft's rows (spec 0222).
 */

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

/** "Mansioni" (spec 0271): zero or more job roles, same picker as "Riporta a". */
export function UserJobRolesField({ control, selected }: FieldProps & { selected: EmploymentRelationRef[] }) {
  const { t } = useTranslation()
  return (
    <RelationMultiSelectField
      control={control}
      name="employment.job_role_ids"
      metaKey="employment.job_role_ids"
      label={t('users.form.employment.jobRoles')}
      resource={JOB_ROLES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('users.form.employment.jobRolesSearch')}
      selected={toFieldRefs(selected)}
      placeholder={t('users.form.employment.jobRolesPlaceholder')}
      emptyLabel={t('users.form.employment.jobRolesEmpty')}
      errorLabel={t('users.form.employment.jobRolesError')}
      removeLabel={t('users.form.employment.jobRolesRemove')}
      retryLabel={t('common.retry')}
    />
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

/** "Data assunzione" / "Data cessazione": date inputs hold `''` for "empty". */
export function UserDateField({
  control,
  name,
  label,
  description,
}: FieldProps & { name: 'employment.hired_at' | 'employment.terminated_at'; label: string; description?: string }) {
  return (
    <MetaField
      control={control}
      name={name}
      metaKey={name}
      label={label}
      description={description ? <FormDescription>{description}</FormDescription> : undefined}
    >
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

/** "IBAN" / "Intestatario" (spec 0272 D-6): plain text, the server normalises and validates (mod-97). */
export function UserPaymentTextField({
  control,
  name,
  label,
}: FieldProps & { name: 'employment.iban' | 'employment.iban_holder'; label: string }) {
  return (
    <MetaField control={control} name={name} metaKey={name} label={label}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}
