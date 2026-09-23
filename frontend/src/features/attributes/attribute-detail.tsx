import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
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
import { Badge } from '@/components/ui/badge'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { formatDateTime } from '@/features/table/cell-renderers'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { FIELD_TYPE_ICONS } from '@/features/custom-fields/field-type-icons'
import type { AttributeDetailWithPermissions } from '@/features/attributes/types'

interface AttributeDetailViewProps {
  attribute: AttributeDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single attribute, rendered as an enterprise-CRM
 * record (Opportunita' reference kit): the identity/fields card on the left,
 * the Attivita' tab on the right when authorized, a metadata footer. ENUM
 * attributes additionally list their options (with color/icon), ordered by
 * `sort_order`.
 */
export function AttributeDetailView({ attribute, onEdit }: AttributeDetailViewProps) {
  const { t } = useTranslation()
  const canEdit = attribute.permissions.resource.update
  const createdAt = formatDateTime(attribute.created_at)
  const sortedOptions = [...attribute.options].sort((a, b) => a.sort_order - b.sort_order)
  const TypeIcon = FIELD_TYPE_ICONS[attribute.type]

  const tabs: RecordCollaborationTab[] = attribute.permissions.actions.view_activity
    ? [activityLogTab('attributes', attribute.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody side={tabs.length > 0 ? <RecordCollaborationCard tabs={tabs} /> : null}>
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={attribute.name} icon={<SlidersHorizontal />} />}
            title={attribute.name}
            subtitle={attribute.code}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('attributes.detail.details')}>
              <RecordFieldList>
                <RecordField label={t('attributes.columns.type')}>
                  <Badge variant="secondary" className="gap-1">
                    <TypeIcon className="size-3.5" aria-hidden="true" />
                    {t(`customFields.types.${attribute.type}`)}
                  </Badge>
                </RecordField>
              </RecordFieldList>
            </RecordSection>

            {attribute.type === 'enum' && (
              <RecordSection title={t('attributes.detail.options')} full>
                {sortedOptions.length === 0 ? (
                  <p className="text-sm text-muted-foreground">{t('attributes.form.optionsEmpty')}</p>
                ) : (
                  <ul className="flex flex-col gap-1.5">
                    {sortedOptions.map((option) => (
                      <li key={option.id} className="flex items-center gap-2 text-sm">
                        <DynamicIcon name={option.icon} className="size-3.5 text-muted-foreground" />
                        <Badge variant="outline" className="font-mono text-xs">
                          {option.value}
                        </Badge>
                        <span className="text-foreground">{option.label}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </RecordSection>
            )}
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('attributes.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
