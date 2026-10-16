import { useTranslation } from 'react-i18next'
import { Briefcase, CalendarClock, FileSignature, KeyRound } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { useCascadeEditable } from '@/components/record-form/use-cascade-editable'
import { Badge } from '@/components/ui/badge'
import { useEnumOptions } from '@/features/config/use-config'
import { formatEuro } from '@/features/invoices/invoice-format'
import { formatDate } from '@/lib/formatting/date-display'
import { UserEmailField, UserPasswordFields, UserRolesField, UserSwitchField } from '@/features/users/user-account-fields'
import {
  UserCompanyField,
  UserDateField,
  UserDurationField,
  UserJobDescriptionField,
  UserQualificationField,
  UserRelationshipTypeField,
  UserReportsToField,
} from '@/features/users/user-employment-fields'
import { UserHourlyCostField } from '@/features/users/user-hourly-cost-field'
import { MANAGER_CASCADE_FIELDS, type UserRecordSectionProps } from '@/features/users/user-record'
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
 * "Profilo": manager status, job description and the reporting line. A
 * manager reports to no one (spec 0166 D-5): turning the switch on empties
 * "Riporta a", so its row opens only when that is editable too.
 */
export function UserProfileRecordSection({ values, form, inline }: UserRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const managerCascadeEditable = useCascadeEditable(MANAGER_CASCADE_FIELDS)

  return (
    <RecordSection title={t('users.form.sections.profile.title')} icon={<Briefcase />}>
      <RecordFieldList>
        <RecordInlineField
          field="employment.is_manager"
          label={t('users.detail.employment.isManager')}
          inline={inline}
          canEdit={managerCascadeEditable}
          editor={
            <UserSwitchField
              control={control}
              name="employment.is_manager"
              label={t('users.form.employment.isManager')}
              description={t('users.form.employment.isManagerDescription')}
            />
          }
        >
          {t(values.is_manager ? 'common.yes' : 'common.no')}
        </RecordInlineField>
        <RecordInlineField
          field="employment.job_description"
          label={t('users.detail.employment.jobDescription')}
          inline={inline}
          editor={<UserJobDescriptionField control={control} />}
        >
          {values.job_description || <DetailEmpty />}
        </RecordInlineField>
        {values.is_manager ? null : (
          <RecordInlineField
            field="employment.reports_to_ids"
            label={t('users.detail.employment.reportsTo')}
            inline={inline}
            editor={<UserReportsToField control={control} selected={values.reports_to} />}
          >
            <RefBadges refs={values.reports_to} />
          </RecordInlineField>
        )}
      </RecordFieldList>
    </RecordSection>
  )
}

/** "Rapporto contrattuale": relationship type and employing company. */
export function UserContractRecordSection({ values, form, inline }: UserRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('users.form.sections.contract.title')} icon={<FileSignature />}>
      <RecordFieldList>
        <RecordInlineField
          field="employment.relationship_type"
          label={t('users.detail.employment.relationshipType')}
          inline={inline}
          editor={<UserRelationshipTypeField control={control} />}
        >
          {values.relationship_type ? t(`enums.relationship_type.${values.relationship_type}`) : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="employment.company_id"
          label={t('users.detail.employment.company')}
          inline={inline}
          editor={<UserCompanyField control={control} selected={values.company} />}
        >
          {values.company ? (
            <RecordLink domain="companies" id={values.company.id}>
              {values.company.label}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** "Dati contrattuali": qualification, dates and daily durations. */
export function UserContractDataRecordSection({ values, form, inline }: UserRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const hiredLabel = t('users.detail.employment.hiredAt')
  const terminatedLabel = t('users.detail.employment.terminatedAt')
  const standardLabel = t('users.detail.employment.standardDailyMinutes')
  const breakLabel = t('users.detail.employment.breakDailyMinutes')
  const hourlyCostLabel = t('users.detail.employment.hourlyCost')

  return (
    <RecordSection title={t('users.form.sections.contractData.title')} icon={<CalendarClock />}>
      <RecordFieldList>
        <RecordInlineField
          field="employment.qualification_type"
          label={t('users.detail.employment.qualificationType')}
          inline={inline}
          editor={<UserQualificationField control={control} />}
        >
          {values.qualification_type ? t(`enums.qualification_type.${values.qualification_type}`) : <DetailEmpty />}
        </RecordInlineField>
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
          editor={<UserDateField control={control} name="employment.terminated_at" label={terminatedLabel} />}
        >
          {formatDate(values.terminated_at) || <DetailEmpty />}
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
        <RecordInlineField
          field="employment.hourly_cost"
          label={hourlyCostLabel}
          inline={inline}
          editor={<UserHourlyCostField control={control} label={hourlyCostLabel} />}
        >
          {values.hourly_cost !== null ? `${formatEuro(values.hourly_cost)}${t('users.form.employment.hourlyCostPerHour')}` : <DetailEmpty />}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
