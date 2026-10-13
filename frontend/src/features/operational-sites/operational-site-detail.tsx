import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { RecordCanvas, RecordMeta, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import {
  OperationalSiteDetailHeader,
  OperationalSiteDetailStats,
} from '@/features/operational-sites/operational-site-detail-header'
import { OperationalSiteRows } from '@/features/operational-sites/operational-site-rows'
import {
  useOperationalSiteForm,
  type OperationalSiteFormValues,
} from '@/features/operational-sites/use-operational-site-form'
import { formatDateTime } from '@/features/table/cell-renderers'
import type {
  OperationalSiteDetailWithPermissions,
  OperationalSiteFormMode,
} from '@/features/operational-sites/types'

/** Stable empty set: a fresh object per render would break the sections' memo. */
const NO_CUSTOM_FIELDS: Record<string, CustomFieldValue> = {}

interface OperationalSiteDetailViewProps {
  operationalSite: OperationalSiteDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the site (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single operational site, rendered as an enterprise-CRM record on
 * the same kit Opportunita', Lead, Campagne e Progetti use: the
 * identity/KPI/sections card on the left, the activity card on the right, a
 * metadata footer. There is no edit page (wave 3 of the "no edit pages"
 * plan): every row edits IN PLACE, one at a time, each save a PATCH of that
 * field alone. Purely presentational: the caller fetches the fresh,
 * re-authorized detail.
 */
export function OperationalSiteDetailView(props: OperationalSiteDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.operationalSite.permissions}>
      <OperationalSiteDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function OperationalSiteDetailContent({ operationalSite, onChanged }: OperationalSiteDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<OperationalSiteFormMode>(() => ({ type: 'edit', operationalSite }), [operationalSite])
  const { form, onSubmit, serverError, clearServerError } = useOperationalSiteForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<OperationalSiteFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('operationalSites.form.genericError'),
  })
  const createdAt = formatDateTime(operationalSite.created_at)
  const collaborationTabs = operationalSite.permissions.actions.view_activity
    ? [activityLogTab('operational-sites', operationalSite.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={collaborationTabs}
        header={
          <>
            <OperationalSiteDetailHeader operationalSite={operationalSite} />
            <OperationalSiteDetailStats operationalSite={operationalSite} />
          </>
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <OperationalSiteRows
              form={form}
              inline={inline}
              geo={{
                city: operationalSite.city,
                province: operationalSite.province,
                state: operationalSite.region,
                country: operationalSite.country,
              }}
            />
            <CustomFieldRecordSections
              resource="operational-sites"
              values={operationalSite.custom_fields ?? NO_CUSTOM_FIELDS}
              inline={inline}
              control={form.control}
            />
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('operationalSites.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
