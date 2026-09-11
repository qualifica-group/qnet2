import { useTranslation } from 'react-i18next'
import { Info } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { EMPTY_VALUE, SUMMARY_LIST_CLASS, SummaryRow } from '@/components/record-form/record-summary'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type { ForSelectItem } from '@/features/for-select/types'
import { UserAssignmentCallout } from '@/features/users/user-assignment-callout'
import {
  summarizeAssignment,
  useAssignmentFieldsVisibility,
} from '@/features/users/user-assignment'
import type { UserFormValues } from '@/features/users/use-user-form'

interface UserFormSummaryProps {
  control: Control<UserFormValues>
  /** Hydrated ref: the only source of a NAME for the physical-site id the form holds. */
  selectedPrimaryOperationalSiteItem: ForSelectItem | null
}

/**
 * The form's side-column aside, in the same card and the same `label / value`
 * rows as the Gestione Richieste and Opportunità recaps (`SummaryRow`), so the
 * record forms keep one shape.
 *
 * On top of it sits the assignment verdict, LIVE: the side column is sticky,
 * so whoever is filling the competence rows or the Sedi far below keeps seeing
 * whether the person will actually be reachable by a record. That is the whole
 * reason the band is here and not only inside the section it judges.
 *
 * Every row is read from the form, never from the persisted snapshot: it
 * recaps what is about to be saved. The physical site renders its name only
 * while the hydrated ref still matches the chosen id — after a change the
 * picker's own trigger already names the new one, and inventing a label here
 * would be a guess (same rule as `OpportunityFormSummary`).
 */
export function UserFormSummary({ control, selectedPrimaryOperationalSiteItem }: UserFormSummaryProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const assignmentFields = useAssignmentFieldsVisibility()
  const competenceRows = useWatch({ control, name: 'employment.product_lines' })
  const primarySiteId = useWatch({ control, name: 'employment.primary_operational_site_id' })
  const remoteSiteIds = useWatch({ control, name: 'employment.remote_operational_site_ids' })
  const roles = useWatch({ control, name: 'roles' })
  const isActive = useWatch({ control, name: 'is_active' })

  const summary = summarizeAssignment({ competenceRows, primarySiteId, remoteSiteIds })
  const primarySiteName =
    primarySiteId !== null && selectedPrimaryOperationalSiteItem?.id === primarySiteId
      ? selectedPrimaryOperationalSiteItem.label
      : null

  return (
    <>
      {assignmentFields.any ? <UserAssignmentCallout summary={summary} /> : null}

      <FormSection
        icon={Info}
        title={t('users.assignment.summary.title')}
        description={t('users.assignment.summary.description')}
        className="min-w-0"
      >
        {/* Each row is gated by the SAME field permission as the control it
            recaps: a label alone already tells the actor a hidden field
            exists, so a recap must never outlive its field. */}
        <dl className={SUMMARY_LIST_CLASS}>
          {assignmentFields.competence ? (
            <SummaryRow label={t('users.form.employment.productLines')}>
              {summary.competenceCount > 0 ? summary.competenceCount : EMPTY_VALUE}
            </SummaryRow>
          ) : null}
          {assignmentFields.primarySite ? (
            <SummaryRow label={t('users.form.employment.primaryOperationalSite')}>
              {primarySiteName ?? EMPTY_VALUE}
            </SummaryRow>
          ) : null}
          {assignmentFields.remoteSites ? (
            <SummaryRow label={t('users.form.employment.remoteOperationalSites')}>
              {summary.remoteSiteCount > 0 ? summary.remoteSiteCount : EMPTY_VALUE}
            </SummaryRow>
          ) : null}
          {fieldPermission('roles').visible ? (
            <SummaryRow label={t('users.form.roles')}>
              {roles.length > 0 ? roles.length : EMPTY_VALUE}
            </SummaryRow>
          ) : null}
          {fieldPermission('is_active').visible ? (
            <SummaryRow label={t('users.form.is_active')}>
              {t(isActive ? 'common.yes' : 'common.no')}
            </SummaryRow>
          ) : null}
        </dl>
      </FormSection>
    </>
  )
}
