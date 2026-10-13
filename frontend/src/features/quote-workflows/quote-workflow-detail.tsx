import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Workflow } from 'lucide-react'
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
import { Badge } from '@/components/ui/badge'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import {
  QuoteWorkflowCriteriaRow,
  QuoteWorkflowGeneralRows,
  QuoteWorkflowStatusesRow,
} from '@/features/quote-workflows/quote-workflow-rows'
import { useQuoteWorkflowForm, type QuoteWorkflowFormValues } from '@/features/quote-workflows/use-quote-workflow-form'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { QuoteWorkflowDetailWithPermissions, QuoteWorkflowFormMode } from '@/features/quote-workflows/types'

interface QuoteWorkflowDetailViewProps {
  quoteWorkflow: QuoteWorkflowDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single opportunity workflow on the record kit: identity band,
 * the "Dettagli" rows, its criteria and its status set as full-width rows
 * (the persisted view until their pencil opens the editor creation uses), the
 * activity card on the right, a metadata footer. There is no edit page (spec
 * 0242): every row edits IN PLACE, one at a time, each save a PATCH of that
 * row's fields alone.
 */
export function QuoteWorkflowDetailView(props: QuoteWorkflowDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.quoteWorkflow.permissions}>
      <QuoteWorkflowDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function QuoteWorkflowDetailContent({ quoteWorkflow, onChanged }: QuoteWorkflowDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<QuoteWorkflowFormMode>(() => ({ type: 'edit', quoteWorkflow }), [quoteWorkflow])
  const { form, onSubmit, serverError, clearServerError } = useQuoteWorkflowForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<QuoteWorkflowFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('quoteWorkflows.form.genericError'),
  })
  const canEdit = quoteWorkflow.permissions.resource.update
  const createdAt = formatDateTime(quoteWorkflow.created_at)
  const tabs: RecordCollaborationTab[] = quoteWorkflow.permissions.actions.view_activity
    ? [activityLogTab('quote-workflows', quoteWorkflow.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={quoteWorkflow.name} icon={<Workflow />} />}
            title={quoteWorkflow.name}
            badges={
              <Badge variant={quoteWorkflow.is_active ? 'default' : 'secondary'}>
                {t(quoteWorkflow.is_active ? 'quoteWorkflows.detail.active' : 'quoteWorkflows.detail.inactive')}
              </Badge>
            }
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('quoteWorkflows.form.sections.identity.title')} full>
              <RecordFieldList>
                <QuoteWorkflowGeneralRows inline={inline} canEdit={canEdit} />
              </RecordFieldList>
            </RecordSection>
            <RecordSection title={t('quoteWorkflows.detail.criteriaTitle')} full>
              <QuoteWorkflowCriteriaRow inline={inline} canEdit={canEdit} quoteWorkflow={quoteWorkflow} />
            </RecordSection>
            <RecordSection title={t('quoteWorkflows.detail.statusesTitle')} full>
              <QuoteWorkflowStatusesRow inline={inline} canEdit={canEdit} quoteWorkflow={quoteWorkflow} />
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('quoteWorkflows.detail.createdAt')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
