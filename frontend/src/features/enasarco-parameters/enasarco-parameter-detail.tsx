import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Percent } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCanvas, RecordCardHeader, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { EnasarcoParameterSections } from '@/features/enasarco-parameters/enasarco-parameter-rows'
import type {
  EnasarcoParameterFormValues,
  EnasarcoParameterSchemaOutput,
} from '@/features/enasarco-parameters/enasarco-parameter-schema'
import { useEnasarcoParameterForm } from '@/features/enasarco-parameters/use-enasarco-parameter-form'
import {
  ENASARCO_PARAMETERS_DOMAIN,
  type EnasarcoParameterDetailWithPermissions,
  type EnasarcoParameterFormMode,
} from '@/features/enasarco-parameters/types'

interface EnasarcoParameterDetailViewProps {
  parameter: EnasarcoParameterDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/**
 * Detail of the ENASARCO parameters of one year on the record kit (spec 0264
 * D-5): there is no edit page, every row edits IN PLACE and saves alone.
 */
export function EnasarcoParameterDetailView(props: EnasarcoParameterDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.parameter.permissions}>
      <EnasarcoParameterDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function EnasarcoParameterDetailContent({ parameter, onChanged }: EnasarcoParameterDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<EnasarcoParameterFormMode>(() => ({ type: 'edit', parameter }), [parameter])
  const { form, onSubmit, serverError, clearServerError } = useEnasarcoParameterForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<EnasarcoParameterFormValues, EnasarcoParameterSchemaOutput>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('enasarcoParameters.form.genericError'),
  })
  const createdAt = formatDateTime(parameter.created_at)
  const updatedAt = formatDateTime(parameter.updated_at)

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={
          parameter.permissions.actions.view_activity
            ? [activityLogTab(ENASARCO_PARAMETERS_DOMAIN, parameter.id, t('activityLog.title'))]
            : []
        }
        header={
          <RecordCardHeader
            media={<DetailMonogram name={String(parameter.year)} icon={<Percent />} />}
            title={t('enasarcoParameters.detail.title', { year: parameter.year })}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <EnasarcoParameterSections inline={inline} canEdit={parameter.permissions.resource.update} />
        </Form>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('enasarcoParameters.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('enasarcoParameters.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
