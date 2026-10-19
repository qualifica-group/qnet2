import { useTranslation } from 'react-i18next'
import { Briefcase, CalendarClock, FileSignature, KeyRound } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import { useEnumOptions } from '@/features/config/use-config'
import { formatDate } from '@/lib/formatting/date-display'
import { UserEmailField, UserPasswordFields, UserRolesField, UserSwitchField } from '@/features/users/user-account-fields'
import {
  UserDateField,
  UserDurationField,
  UserBusinessFunctionsField,
  UserManagedBusinessFunctionsField,
  UserJobRolesField,
  UserReportsToField,
} from '@/features/users/user-employment-fields'
import { FunctionBadges } from '@/features/users/function-badges'
import { type UserRecordSectionProps, type UserRecordValues } from '@/features/users/user-record'
import type { EmploymentRelationRef } from '@/features/users/types'

/** Formats a total-minutes duration as `H:MM`, or `null` when unset (spec 0015). */
function formatMinutes(value: number | null): string | null {
  if (value === null) {
    return null
  }
  const hours = Math.floor(value / 60)
  const minutes = value % 60
  return `${hours}:${String(minutes).padStart(2, '0')}`
}

/** Zero or more refs as chips (roles, managers); the shared empty marker when there is none. */
export function RefBadges({ refs }: { refs: EmploymentRelationRef[] }) {
  if (refs.length === 0) {
    return <DetailEmpty />
  }
  return (
    <div className="flex flex-wrap gap-1">
      {refs.map((ref) => (
        <Badge key={ref.id} variant="secondary" className="max-w-full">
          {ref.label}
        </Badge>
      ))}
    </div>
  )
}

/**
 * "Credenziali e accesso": email, password, roles, whether the account may
 * sign in, and the interface language (read-only: not editable on the form).
 */
export function UserAccessRecordSection({ values, form, inline, isEdit }: UserRecordSectionProps & { isEdit: boolean }) {
  const { t } = useTranslation()
  const { control } = form
  const localeOptions = useEnumOptions('locale')
  const localeLabel = localeOptions.find((option) => option.value === values.locale)?.label ?? values.locale

  return (
    <RecordSection title={t('users.form.sections.credentials.title')} icon={<KeyRound />}>
      <RecordFieldList>
        <RecordInlineField field="email" label={t('users.form.email')} inline={inline} editor={<UserEmailField control={control} />}>
          {values.email || <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="password"
          label={t('users.detail.password.label')}
          inline={inline}
          editor={<UserPasswordFields control={control} isEdit={isEdit} />}
        >
          {t(`users.detail.password.${values.password_state}`)}
        </RecordInlineField>
        <RecordInlineField
          field="roles"
          label={t('users.form.roles')}
          inline={inline}
          editor={<UserRolesField control={control} selected={values.roles} />}
        >
          <RefBadges refs={values.roles} />
        </RecordInlineField>
        <RecordInlineField
          field="is_active"
          label={t('users.form.is_active')}
          inline={inline}
          editor={
            <UserSwitchField
              control={control}
              name="is_active"
              label={t('users.form.is_active')}
              description={t('users.form.isActiveHint')}
            />
          }
        >
          {t(values.is_active ? 'common.yes' : 'common.no')}
        </RecordInlineField>
        <RecordField label={t('users.form.locale')}>{localeLabel}</RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

/**
 * "Profilo": job roles, the functions of membership and managed, and the
 * reporting line.
 */
export function UserProfileRecordSection({ values, form, inline }: UserRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const managedFunctionIds = values.managed_business_functions.map((item) => item.id)

  return (
    <RecordSection title={t('users.form.sections.profile.title')} icon={<Briefcase />}>
      <RecordFieldList>
        <RecordInlineField
          field="employment.job_role_ids"
          label={t('users.detail.employment.jobRoles')}
          inline={inline}
          editor={<UserJobRolesField control={control} selected={values.job_roles} />}
        >
          <RefBadges refs={values.job_roles} />
        </RecordInlineField>
        <RecordInlineField
          field="employment.business_function_ids"
          label={t('users.detail.employment.businessFunctions')}
          inline={inline}
          editor={<UserBusinessFunctionsField control={control} selected={values.business_functions} />}
        >
          <FunctionBadges refs={values.business_functions} managedIds={managedFunctionIds} />
        </RecordInlineField>
        <RecordInlineField
          field="employment.managed_business_function_ids"
          label={t('users.detail.employment.managedBusinessFunctions')}
          inline={inline}
          editor={<UserManagedBusinessFunctionsField control={control} selected={values.managed_business_functions} />}
        >
          {values.managed_business_functions.length > 0 ? (
            <FunctionBadges refs={values.managed_business_functions} managedIds={managedFunctionIds} />
          ) : (
            <span className="text-muted-foreground">{t('users.detail.employment.managedBusinessFunctionsEmpty')}</span>
          )}
        </RecordInlineField>
        <RecordInlineField
          field="employment.reports_to_ids"
          label={t('users.detail.employment.reportsTo')}
          inline={inline}
          editor={<UserReportsToField control={control} selected={values.reports_to} />}
        >
          <RefBadges refs={values.reports_to} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/**
 * "Rapporto contrattuale" (spec 0272 D-2): the condition in force today, read
 * only. Relationship type and company are copies of the latest condition of the
 * contract history, which is where they change.
 */
export function UserContractRecordSection({ values }: { values: UserRecordValues }) {
  const { t } = useTranslation()
  const level = values.current_contract?.collective_agreement_level ?? null

  return (
    <RecordSection title={t('users.form.sections.contract.title')} icon={<FileSignature />}>
      <RecordFieldList>
        <RecordField label={t('users.detail.employment.relationshipType')}>
          {values.relationship_type ? t(`enums.relationship_type.${values.relationship_type}`) : <DetailEmpty />}
        </RecordField>
        <RecordField label={t('users.detail.employment.company')}>
          {values.company ? (
            <RecordLink domain="companies" id={values.company.id}>
              {values.company.label}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordField>
        <RecordField label={t('users.detail.employment.collectiveAgreementLevel')}>
          {level ? level.label : <DetailEmpty />}
        </RecordField>
        <RecordField label={t('users.detail.employment.contractStartsOn')}>
          {formatDate(values.current_contract?.starts_on ?? null) || <DetailEmpty />}
        </RecordField>
      </RecordFieldList>
      <p className="mt-2 text-xs text-muted-foreground">{t('users.detail.employment.contractReadOnlyHint')}</p>
    </RecordSection>
  )
}

/** "Dati contrattuali": hire and termination dates and daily durations. */
export function UserContractDataRecordSection({ values, form, inline }: UserRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const hiredLabel = t('users.detail.employment.hiredAt')
  const terminatedLabel = t('users.detail.employment.terminatedAt')
  const terminatedHint = t('users.detail.employment.terminatedAtHint')
  const standardLabel = t('users.detail.employment.standardDailyMinutes')
  const breakLabel = t('users.detail.employment.breakDailyMinutes')

  return (
    <RecordSection title={t('users.form.sections.contractData.title')} icon={<CalendarClock />}>
      <RecordFieldList>
        <RecordInlineField
          field="employment.hired_at"
          label={hiredLabel}
          inline={inline}
          editor={<UserDateField control={control} name="employment.hired_at" label={hiredLabel} />}
        >
          {formatDate(values.hired_at) || <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="employment.terminated_at"
          label={terminatedLabel}
          inline={inline}
          editor={
            <UserDateField
              control={control}
              name="employment.terminated_at"
              label={terminatedLabel}
              description={terminatedHint}
            />
          }
        >
          {formatDate(values.terminated_at) || <DetailEmpty />}
          <span className="block text-xs text-muted-foreground">{terminatedHint}</span>
        </RecordInlineField>
        <RecordInlineField
          field="employment.standard_daily_minutes"
          label={standardLabel}
          inline={inline}
          editor={<UserDurationField control={control} name="employment.standard_daily_minutes" label={standardLabel} />}
        >
          {formatMinutes(values.standard_daily_minutes) ?? <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="employment.break_daily_minutes"
          label={breakLabel}
          inline={inline}
          editor={<UserDurationField control={control} name="employment.break_daily_minutes" label={breakLabel} />}
        >
          {formatMinutes(values.break_daily_minutes) ?? <DetailEmpty />}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
