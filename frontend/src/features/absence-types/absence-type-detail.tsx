import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { CalendarOff } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { AbsenceTypeRows } from '@/features/absence-types/absence-type-rows'
import { useAbsenceTypeForm, type AbsenceTypeFormValues } from '@/features/absence-types/use-absence-type-form'
import { ABSENCE_TYPES_DOMAIN, type AbsenceTypeDetail, type AbsenceTypeFormMode } from '@/features/absence-types/types'

interface AbsenceTypeDetailViewProps {
  absenceType: AbsenceTypeDetail
  /** Called after an in-place save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/**
 * Detail of a single absence type on the record kit: identity band, the rows edited IN PLACE
 * one at a time (each save a PATCH of that field alone), the activity log and a metadata footer.
 */
export function AbsenceTypeDetailView(props: AbsenceTypeDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.absenceType.permissions}>
      <AbsenceTypeDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function AbsenceTypeDetailContent({ absenceType, onChanged }: AbsenceTypeDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<AbsenceTypeFormMode>(() => ({ type: 'edit', absenceType }), [absenceType])
  const { form, onSubmit, serverError, clearServerError } = useAbsenceTypeForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<AbsenceTypeFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('absenceTypes.form.genericError'),
  })
  const canEdit = absenceType.permissions.resource.update
  const createdAt = formatDateTime(absenceType.created_at)
  const updatedAt = formatDateTime(absenceType.updated_at)
  const tabs: RecordCollaborationTab[] = absenceType.permissions.actions.view_activity
    ? [activityLogTab(ABSENCE_TYPES_DOMAIN, absenceType.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={absenceType.name} icon={<CalendarOff />} />}
            title={absenceType.name}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('absenceTypes.form.sections.identity.title')} full>
              <RecordFieldList>
                <AbsenceTypeRows inline={inline} canEdit={canEdit} />
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('absenceTypes.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('absenceTypes.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
