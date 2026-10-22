import { useTranslation } from 'react-i18next'
import { CalendarX, Pencil } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import { formatDate } from '@/lib/formatting/date-display'
import type { CompanyClosure } from '@/features/company-closures/types'

interface CompanyClosureDetailViewProps {
  closure: CompanyClosure
  /** Swaps the host to the edit form; absent when the host offers none. */
  onEdit?: () => void
}

/** Read-only detail of a closure; Edit is offered only while the server would accept it (not started, or repeating yearly). */
export function CompanyClosureDetailView({ closure, onEdit }: CompanyClosureDetailViewProps) {
  const { t } = useTranslation()
  const editable = !closure.is_started || closure.repeats_yearly
  const yes = t('common.yes')
  const no = t('common.no')

  return (
    <RecordCanvas>
      <RecordCard>
        <RecordCardHeader
          media={<DetailMonogram name={closure.name} icon={<CalendarX />} />}
          title={closure.name}
          actions={
            onEdit && editable ? (
              <Can permission="company-closures.update">
                <Button variant="secondary" onClick={onEdit}>
                  <Pencil aria-hidden="true" />
                  {t('companyClosures.detail.edit')}
                </Button>
              </Can>
            ) : null
          }
        />
        <RecordSectionsGrid>
          <RecordSection title={t('companyClosures.form.sections.details')} full>
            <RecordFieldList>
              <RecordField label={t('companyClosures.form.startsOn')}>{formatDate(closure.starts_on)}</RecordField>
              <RecordField label={t('companyClosures.form.endsOn')}>{formatDate(closure.ends_on)}</RecordField>
              <RecordField label={t('companyClosures.form.kind')}>{t(`companyClosures.kinds.${closure.kind}`)}</RecordField>
              {closure.absence_type ? (
                <RecordField label={t('companyClosures.form.absenceType')}>{closure.absence_type.name}</RecordField>
              ) : null}
              <RecordField label={t('companyClosures.form.sites')}>
                {closure.applies_to_all_sites ? (
                  t('companyClosures.detail.allSites')
                ) : closure.operational_sites.length > 0 ? (
                  closure.operational_sites.map((site) => site.name).join(', ')
                ) : (
                  <DetailEmpty />
                )}
              </RecordField>
              <RecordField label={t('companyClosures.form.repeatsYearly')}>{closure.repeats_yearly ? yes : no}</RecordField>
              {closure.kind === 'collective_leave' ? (
                <RecordField label={t('companyClosures.detail.generatedRequests')}>{closure.generated_requests_count}</RecordField>
              ) : null}
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
        {closure.is_started && !closure.repeats_yearly ? (
          <p className="px-4 pb-4 text-xs text-muted-foreground">{t('companyClosures.form.startedLocked')}</p>
        ) : null}
      </RecordCard>
    </RecordCanvas>
  )
}
