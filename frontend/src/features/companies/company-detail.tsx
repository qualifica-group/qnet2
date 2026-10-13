import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { RecordCanvas, RecordMeta, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { CompanyDetailHeader, CompanyDetailStats } from '@/features/companies/company-detail-header'
import { CompanyRows } from '@/features/companies/company-rows'
import { useCompanyForm, type CompanyFormValues } from '@/features/companies/use-company-form'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { CompanyAddress, CompanyDetailWithPermissions, CompanyFormMode } from '@/features/companies/types'

/** Stable empty set: a fresh object per render would break the sections' memo. */
const NO_CUSTOM_FIELDS: Record<string, CustomFieldValue> = {}

/** The persisted address' place names, comune first (the order every address surface reads in). */
function placeOf(address: CompanyAddress | null): string[] {
  return address ? [address.city, address.province, address.region, address.country].filter((name) => name !== null) : []
}

interface CompanyDetailViewProps {
  company: CompanyDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the company (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single company, rendered as an enterprise-CRM record on the same
 * kit Opportunita', Lead, Campagne, Progetti e Sedi use: the identity/KPI/
 * sections card on the left, the activity card on the right, a metadata
 * footer. There is no edit page (wave 3 of the "no edit pages" plan): every
 * row — custom fields included — edits IN PLACE, one at a time, each save a
 * PATCH of that field alone. Container-query driven (`RecordCanvas`) so the
 * same tree renders correctly both inside a resizable Sheet and on a
 * full-bleed page. Purely presentational: the caller fetches the detail.
 */
export function CompanyDetailView(props: CompanyDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.company.permissions}>
      <CompanyDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function CompanyDetailContent({ company, onChanged }: CompanyDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<CompanyFormMode>(() => ({ type: 'edit', company }), [company])
  const { form, onSubmit, serverError, clearServerError } = useCompanyForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<CompanyFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('companies.form.genericError'),
  })
  const createdAt = formatDateTime(company.created_at)
  const collaborationTabs = company.permissions.actions.view_activity
    ? [activityLogTab('companies', company.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={collaborationTabs}
        header={
          <>
            <CompanyDetailHeader company={company} />
            <CompanyDetailStats company={company} />
          </>
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <CompanyRows form={form} inline={inline} place={placeOf(company.address)} />
            <CustomFieldRecordSections
              resource="companies"
              values={company.custom_fields ?? NO_CUSTOM_FIELDS}
              inline={inline}
              control={form.control}
            />
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('companies.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
