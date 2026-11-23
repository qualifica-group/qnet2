import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Flag } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { QuoteSystemStatusRows } from '@/features/quote-system-statuses/quote-system-status-rows'
import { useQuoteSystemStatusForm } from '@/features/quote-system-statuses/use-quote-system-status-form'
import type { QuoteSystemStatusFormValues } from '@/features/quote-system-statuses/quote-system-status-schema'
import type {
  QuoteSystemStatusDetailWithPermissions,
  QuoteSystemStatusFormMode,
} from '@/features/quote-system-statuses/types'

interface QuoteSystemStatusDetailViewProps {
  quoteSystemStatus: QuoteSystemStatusDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/**
 * Detail of a single system status on the record kit: identity band, the
 * "Dettagli" rows (edited in place, one at a time), the linked working
 * statuses count and the activity in the side column.
 */
export function QuoteSystemStatusDetailView(props: QuoteSystemStatusDetailViewProps) {
  return (
    <ResourcePermissionsProvider permissions={props.quoteSystemStatus.permissions}>
      <QuoteSystemStatusDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function QuoteSystemStatusDetailContent({ quoteSystemStatus, onChanged }: QuoteSystemStatusDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  const mode = useMemo<QuoteSystemStatusFormMode>(() => ({ type: 'edit', quoteSystemStatus }), [quoteSystemStatus])
  const { form, onSubmit, serverError, clearServerError } = useQuoteSystemStatusForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<QuoteSystemStatusFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('quoteSystemStatuses.form.genericError'),
  })
  const createdAt = formatDateTime(quoteSystemStatus.created_at)
  const tabs = quoteSystemStatus.permissions.actions.view_activity
    ? [activityLogTab('quote-system-statuses', quoteSystemStatus.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={quoteSystemStatus.name} icon={<Flag />} />}
            title={quoteSystemStatus.name}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('quoteSystemStatuses.form.sections.identity.title')} full>
              <RecordFieldList>
                <QuoteSystemStatusRows
                  inline={inline}
                  canEdit={quoteSystemStatus.permissions.resource.update}
                  isSystemRow={quoteSystemStatus.system_key !== null}
                />
                <RecordField label={t('quoteSystemStatuses.detail.workflowStatusesCount')}>
                  {quoteSystemStatus.workflow_statuses_count}
                </RecordField>
                <RecordField label={t('quoteSystemStatuses.detail.sortOrder')}>{quoteSystemStatus.sort_order}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('quoteSystemStatuses.detail.createdAt')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
