import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Mail } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { EmailTemplateBodyRow, EmailTemplateRows } from '@/features/email-templates/email-template-rows'
import {
  useEmailTemplateForm,
  type EmailTemplateFormValues,
} from '@/features/email-templates/use-email-template-form'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { EmailTemplateFormMode, EmailTemplateWithPermissions } from '@/features/email-templates/types'

interface EmailTemplateDetailViewProps {
  emailTemplate: EmailTemplateWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single email template on the record kit: identity band, the
 * "Dettagli" rows, the body as one full-width row, the Attivita' tab on the
 * right when authorized, a metadata footer. There is no edit page (wave 3 of
 * the "no edit pages" plan): every row edits IN PLACE, one at a time, each
 * save a PATCH of that field alone. `body` renders through `RichTextContent`
 * (never `dangerouslySetInnerHTML`, react-security.md).
 */
export function EmailTemplateDetailView(props: EmailTemplateDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.emailTemplate.permissions}>
      <EmailTemplateDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function EmailTemplateDetailContent({ emailTemplate, onChanged }: EmailTemplateDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<EmailTemplateFormMode>(() => ({ type: 'edit', emailTemplate }), [emailTemplate])
  const { form, onSubmit, serverError, clearServerError } = useEmailTemplateForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<EmailTemplateFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('emailTemplates.form.genericError'),
  })
  const canEdit = emailTemplate.permissions.resource.update
  const createdAt = formatDateTime(emailTemplate.created_at)
  const updatedAt = formatDateTime(emailTemplate.updated_at)

  const tabs: RecordCollaborationTab[] = emailTemplate.permissions.actions.view_activity
    ? [activityLogTab('email-templates', emailTemplate.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={emailTemplate.name} icon={<Mail />} />}
            title={emailTemplate.name}
            legacyId={emailTemplate.old_id}
            subtitle={emailTemplate.subject}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('emailTemplates.form.sections.identity.title')} full>
              <RecordFieldList>
                <EmailTemplateRows inline={inline} canEdit={canEdit} moduleLocked />
              </RecordFieldList>
            </RecordSection>
            <RecordSection title={t('emailTemplates.detail.body')} full>
              <EmailTemplateBodyRow inline={inline} canEdit={canEdit} />
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('emailTemplates.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('emailTemplates.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
