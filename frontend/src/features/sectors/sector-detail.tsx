import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { ListTree, Power } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { RecordCanvas, RecordCardHeader, RecordMeta } from '@/components/detail/record-panel'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Badge } from '@/components/ui/badge'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { SectorSections } from '@/features/sectors/sector-rows'
import { useSectorForm, type SectorFormValues } from '@/features/sectors/use-sector-form'
import type { SectorDetailWithPermissions, SectorFormMode } from '@/features/sectors/types'
import { formatDateTime } from '@/features/table/cell-renderers'

const NO_CUSTOM_FIELDS = {}

interface SectorDetailViewProps {
  sector: SectorDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single sector on the record kit: identity band (parent, code
 * and active badges), the "Dettagli" rows and the custom fields, the activity
 * in the side column, a metadata footer. There is no edit page (wave 3 of the
 * "no edit pages" plan): every row edits IN PLACE, one at a time, each save a
 * PATCH of that field alone.
 */
export function SectorDetailView(props: SectorDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.sector.permissions}>
      <SectorDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function SectorDetailContent({ sector, onChanged }: SectorDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<SectorFormMode>(() => ({ type: 'edit', sector }), [sector])
  const { form, onSubmit, serverError, clearServerError } = useSectorForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<SectorFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('sectors.form.genericError'),
  })
  const createdAt = formatDateTime(sector.created_at)
  const tabs: RecordCollaborationTab[] = sector.permissions.actions.view_activity
    ? [activityLogTab('sectors', sector.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={sector.name} icon={<ListTree />} />}
            title={sector.name}
            legacyId={sector.old_id}
            subtitle={sector.parent?.name}
            badges={
              sector.code !== null || !sector.is_active ? (
                <>
                  {sector.code !== null ? (
                    <Badge variant="secondary">
                      {t('sectors.columns.code')} {sector.code}
                    </Badge>
                  ) : null}
                  {!sector.is_active ? (
                    <Badge variant="outline">
                      <Power aria-hidden="true" />
                      {t('sectors.badges.notActive')}
                    </Badge>
                  ) : null}
                </>
              ) : null
            }
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <SectorSections
            form={form}
            inline={inline}
            canEdit={sector.permissions.resource.update}
            sectorId={sector.id}
            customFieldValues={sector.custom_fields ?? NO_CUSTOM_FIELDS}
          />
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('sectors.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
