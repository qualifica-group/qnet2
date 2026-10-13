import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Ruler } from 'lucide-react'
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
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { UnitOfMeasureRows } from '@/features/units-of-measure/unit-of-measure-rows'
import {
  useUnitOfMeasureForm,
  type UnitOfMeasureFormValues,
} from '@/features/units-of-measure/use-unit-of-measure-form'
import type {
  UnitOfMeasureDetailWithPermissions,
  UnitOfMeasureFormMode,
} from '@/features/units-of-measure/types'

interface UnitOfMeasureDetailViewProps {
  unitOfMeasure: UnitOfMeasureDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single unit of measure on the record kit: identity band
 * (`code` as the subtitle), the rows, the activity card, a metadata footer.
 * There is no edit page (wave 3 of the "no edit pages" plan): every row
 * edits IN PLACE, one at a time, each save a PATCH of that field alone.
 */
export function UnitOfMeasureDetailView(props: UnitOfMeasureDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.unitOfMeasure.permissions}>
      <UnitOfMeasureDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function UnitOfMeasureDetailContent({ unitOfMeasure, onChanged }: UnitOfMeasureDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<UnitOfMeasureFormMode>(() => ({ type: 'edit', unitOfMeasure }), [unitOfMeasure])
  const { form, onSubmit, serverError, clearServerError } = useUnitOfMeasureForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<UnitOfMeasureFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('unitsOfMeasure.form.genericError'),
  })
  const canEdit = unitOfMeasure.permissions.resource.update
  const createdAt = formatDateTime(unitOfMeasure.created_at)
  const updatedAt = formatDateTime(unitOfMeasure.updated_at)
  const canViewActivity = unitOfMeasure.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={canViewActivity ? [activityLogTab('units-of-measure', unitOfMeasure.id, t('activityLog.title'))] : []}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={unitOfMeasure.name} icon={<Ruler />} />}
            title={unitOfMeasure.name}
            subtitle={unitOfMeasure.code}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('unitsOfMeasure.form.sections.identity.title')} full>
              <RecordFieldList>
                <UnitOfMeasureRows inline={inline} canEdit={canEdit} />
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('unitsOfMeasure.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('unitsOfMeasure.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
