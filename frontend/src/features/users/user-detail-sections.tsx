import { useTranslation } from 'react-i18next'
import { Briefcase, CalendarClock, FileSignature, Globe, MapPin, Target } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import {
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import { useEnumOptions } from '@/features/config/use-config'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'
import type { ProductLine } from '@/features/product-lines/types'
import { UserAssignmentCallout } from '@/features/users/user-assignment-callout'
import type { AssignmentSummary } from '@/features/users/user-assignment'
import type { EmploymentDetail, EmploymentRelationRef, UserDetail } from '@/features/users/types'
import { formatDate } from '@/lib/formatting/date-display'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/** Stable empty defaults: a user with no employment reads the same as one with an empty profile. */
const EMPTY_PRODUCT_LINES: ProductLine[] = []
const EMPTY_SITES: EmploymentRelationRef[] = []

interface UserDetailSectionsProps {
  user: UserDetail
  assignment: AssignmentSummary
}

/**
 * The record's `RecordSectionsGrid` body. The ASSIGNMENT configuration comes
 * first and full width (user directive 2026-09-11): competence rows and Sedi
 * are what decides whether an offer can ever reach this person, so they read
 * before the contract terms that merely describe them.
 *
 * That section is rendered even for a user with no employment profile at all:
 * "nothing configured" is precisely the answer someone opens this card to get.
 * The remaining sections are absent when there is no profile to describe.
 */
export function UserDetailSections({ user, assignment }: UserDetailSectionsProps) {
  const { t } = useTranslation()
  const localeOptions = useEnumOptions('locale')
  const employment = user.employment ?? null
  const localeLabel =
    localeOptions.find((option) => option.value === user.locale)?.label ?? user.locale

  return (
    <RecordSectionsGrid>
      <RecordSection
        title={t('users.assignment.title')}
        icon={<Target />}
        className={FULL_WIDTH_SECTION_CLASS}
      >
        {/* Only when something is missing: a green band on a record that is
            already fine would be decoration, not information. */}
        {assignment.assignable ? null : <UserAssignmentCallout summary={assignment} />}

        <RecordFieldList>
          <RecordField label={t('users.detail.employment.productLines')}>
            <ProductLinesReadOnlyList lines={employment?.product_lines ?? EMPTY_PRODUCT_LINES} />
          </RecordField>
          <RecordField label={t('users.detail.employment.primaryOperationalSite')} icon={<MapPin />}>
            {employment?.primary_operational_site?.label ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('users.detail.employment.remoteOperationalSites')} icon={<MapPin />}>
            <SiteBadges sites={employment?.remote_operational_sites ?? EMPTY_SITES} />
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      {employment ? <UserProfileSection employment={employment} /> : null}
      {employment ? <UserContractSection employment={employment} /> : null}
      {employment ? <UserContractDataSection employment={employment} /> : null}

      <RecordSection title={t('users.form.sections.credentials.title')} icon={<Globe />}>
        <RecordFieldList>
          <RecordField label={t('users.form.email')}>{user.email}</RecordField>
          <RecordField label={t('users.form.locale')}>{localeLabel}</RecordField>
        </RecordFieldList>
      </RecordSection>
    </RecordSectionsGrid>
  )
}

/** Zero or more Sedi as chips; the shared empty marker when there is none. */
function SiteBadges({ sites }: { sites: EmploymentRelationRef[] }) {
  if (sites.length === 0) {
    return <DetailEmpty />
  }
  return (
    <div className="flex flex-wrap gap-1">
      {sites.map((site) => (
        <Badge key={site.id} variant="secondary" className="max-w-full truncate">
          {site.label}
        </Badge>
      ))}
    </div>
  )
}

/** What the person IS in the organization — the counterpart of the form's Profile section. */
function UserProfileSection({ employment }: { employment: EmploymentDetail }) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('users.form.sections.profile.title')} icon={<Briefcase />}>
      <RecordFieldList>
        <RecordField label={t('users.detail.employment.isManager')}>
          {t(employment.is_manager ? 'common.yes' : 'common.no')}
        </RecordField>
        <RecordField label={t('users.detail.employment.jobDescription')}>
          {employment.job_description || <DetailEmpty />}
        </RecordField>
        <RecordField label={t('users.detail.employment.reportsTo')}>
          {employment.reports_to?.label ?? <DetailEmpty />}
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** The terms of the contract — the counterpart of the form's Contract section. */
function UserContractSection({ employment }: { employment: EmploymentDetail }) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('users.form.sections.contract.title')} icon={<FileSignature />}>
      <RecordFieldList>
        <RecordField label={t('users.detail.employment.relationshipType')}>
          {employment.relationship_type ? (
            t(`enums.relationship_type.${employment.relationship_type}`)
          ) : (
            <DetailEmpty />
          )}
        </RecordField>
        <RecordField label={t('users.detail.employment.company')}>
          {employment.company?.label ?? <DetailEmpty />}
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** Qualification, dates and daily durations. */
function UserContractDataSection({ employment }: { employment: EmploymentDetail }) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('users.form.sections.contractData.title')} icon={<CalendarClock />}>
      <RecordFieldList>
        <RecordField label={t('users.detail.employment.qualificationType')}>
          {employment.qualification_type ? (
            t(`enums.qualification_type.${employment.qualification_type}`)
          ) : (
            <DetailEmpty />
          )}
        </RecordField>
        <RecordField label={t('users.detail.employment.hiredAt')}>
          {formatDate(employment.hired_at) || <DetailEmpty />}
        </RecordField>
        <RecordField label={t('users.detail.employment.terminatedAt')}>
          {formatDate(employment.terminated_at) || <DetailEmpty />}
        </RecordField>
        <RecordField label={t('users.detail.employment.standardDailyMinutes')}>
          {formatMinutes(employment.standard_daily_minutes) ?? <DetailEmpty />}
        </RecordField>
        <RecordField label={t('users.detail.employment.breakDailyMinutes')}>
          {formatMinutes(employment.break_daily_minutes) ?? <DetailEmpty />}
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** Formats a total-minutes duration as `H:MM`, or `null` when unset (spec 0015). */
function formatMinutes(value: number | null): string | null {
  if (value === null) {
    return null
  }
  const hours = Math.floor(value / 60)
  const minutes = value % 60
  return `${hours}:${String(minutes).padStart(2, '0')}`
}
