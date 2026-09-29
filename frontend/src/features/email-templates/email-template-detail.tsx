import { useTranslation } from 'react-i18next'
import { Mail } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import {
  RecordCollaborationCard,
  type RecordCollaborationTab,
} from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { RichTextContent } from '@/components/rich-text/rich-text-content'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { EmailTemplateWithPermissions } from '@/features/email-templates/types'

interface EmailTemplateDetailViewProps {
  emailTemplate: EmailTemplateWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single email template, rendered as an enterprise-CRM
 * record (`task-importances` reference kit): the identity/fields card on the
 * left, the Attivita' tab on the right when authorized, a metadata footer.
 * `body` renders through `RichTextContent` (never `dangerouslySetInnerHTML`,
 * react-security.md), the same read-only surface a note/task description
 * uses.
 */
export function EmailTemplateDetailView({ emailTemplate, onEdit }: EmailTemplateDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = emailTemplate.permissions.resource.update
  const createdAt = formatDateTime(emailTemplate.created_at)
  const updatedAt = formatDateTime(emailTemplate.updated_at)

  const tabs: RecordCollaborationTab[] = emailTemplate.permissions.actions.view_activity
    ? [activityLogTab('email-templates', emailTemplate.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody side={tabs.length > 0 ? <RecordCollaborationCard tabs={tabs} /> : null}>
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={emailTemplate.name} icon={<Mail />} />}
            title={emailTemplate.name}
            subtitle={emailTemplate.subject}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('emailTemplates.form.sections.identity.title')} full>
              <RecordFieldList>
                <RecordField label={t('emailTemplates.detail.module')}>
                  {t(`emailTemplates.modules.${emailTemplate.module}`)}
                </RecordField>
                <RecordField label={t('emailTemplates.detail.subject')}>{emailTemplate.subject}</RecordField>
                <RecordField label={t('emailTemplates.detail.description')}>
                  {emailTemplate.description ? emailTemplate.description : <DetailEmpty />}
                </RecordField>
                <RecordField label={t('emailTemplates.detail.isActive')}>
                  {emailTemplate.is_active ? t('common.yes') : t('common.no')}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
            <RecordSection title={t('emailTemplates.detail.body')} full>
              <RichTextContent html={emailTemplate.body} />
            </RecordSection>
          </RecordSectionsGrid>
        </RecordCard>
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
