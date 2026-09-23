import { useTranslation } from 'react-i18next'
import { Puzzle } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
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
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'
import { RecordEditButton } from '@/components/detail/record-edit-button'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { Badge } from '@/components/ui/badge'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { CustomFieldDefinitionDetailWithPermissions } from '@/features/custom-fields/types'

interface CustomFieldDetailViewProps {
  definition: CustomFieldDefinitionDetailWithPermissions
  /** Opens the module's existing edit surface (sheet or page); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single custom field definition, rendered as an
 * enterprise-CRM record on the same kit Opportunita' uses: the identity/
 * fields card on the left, the activity card on the right, a metadata
 * footer. ENUM definitions additionally list their options; RELATION
 * definitions show their target.
 */
export function CustomFieldDetailView({ definition, onEdit }: CustomFieldDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(definition.created_at)
  const sortedOptions = [...definition.options].sort((a, b) => a.sort_order - b.sort_order)
  const canEdit = definition.permissions.resource.update
  const canViewActivity = definition.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        side={
          canViewActivity ? (
            <RecordCollaborationCard
              tabs={[activityLogTab('custom-fields', definition.id, t('activityLog.title'))]}
            />
          ) : null
        }
      >
        <RecordCard>
          <RecordCardHeader
            media={<DetailMonogram name={definition.label} icon={<Puzzle />} />}
            title={definition.label}
            subtitle={definition.key}
            actions={canEdit && onEdit ? <RecordEditButton onClick={onEdit} /> : null}
          />
          <RecordSectionsGrid>
            <RecordSection title={t('customFields.detail.details')}>
              <RecordFieldList>
                <RecordField label={t('customFields.columns.entity_type')}>
                  <Badge variant="outline">{t(`customFields.entities.${definition.entity_type}`)}</Badge>
                </RecordField>
                <RecordField label={t('customFields.columns.type')}>
                  <Badge variant="secondary">{t(`customFields.types.${definition.type}`)}</Badge>
                </RecordField>
                {definition.group ? (
                  <RecordField label={t('customFields.columns.group')}>{definition.group}</RecordField>
                ) : null}
                <RecordField label={t('customFields.columns.is_active')}>
                  {definition.is_active ? t('common.yes') : t('common.no')}
                </RecordField>
              </RecordFieldList>
            </RecordSection>

            {definition.type === 'enum' && (
              <RecordSection title={t('customFields.detail.options')}>
                {sortedOptions.length === 0 ? (
                  <p className="text-sm text-muted-foreground">{t('customFields.form.optionsEmpty')}</p>
                ) : (
                  <ul className="flex flex-col gap-1.5">
                    {sortedOptions.map((option) => (
                      <li key={option.id} className="flex items-center gap-2 text-sm">
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

            {definition.type === 'relation' && definition.relation_target ? (
              <RecordSection title={t('customFields.detail.relation')}>
                <RecordFieldList>
                  <RecordField label={t('customFields.form.relationEntityType')}>
                    {t(`customFields.entities.${definition.relation_target.entity_type}`)}
                  </RecordField>
                  <RecordField label={t('customFields.form.relationCardinality')}>
                    {definition.relation_target.cardinality === 'many'
                      ? t('customFields.form.relationCardinalityMany')
                      : t('customFields.form.relationCardinalityOne')}
                  </RecordField>
                </RecordFieldList>
              </RecordSection>
            ) : null}
          </RecordSectionsGrid>
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('customFields.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
