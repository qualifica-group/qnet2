import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useCompanyFormMeta } from '@/features/companies/use-company-form-meta'
import { CompanyFormBody } from '@/features/companies/company-form-body'
import type { CompanyDetail, CompanyDetailWithPermissions } from '@/features/companies/types'

export type CompanyFormMode =
  | { type: 'create' }
  | { type: 'edit'; company: CompanyDetailWithPermissions }

interface CompanyFormProps {
  mode: CompanyFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (company: CompanyDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a company.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/companies` — then hands off to `CompanyFormBody`, which reads
 * every field from that context via `MetaField`/`useResourcePermissions()`.
 */
export function CompanyForm(props: CompanyFormProps) {
  const { t } = useTranslation()
  const meta = useCompanyFormMeta(props.mode)

  if (meta.status === 'loading') {
    return <RecordFormSkeleton />
  }

  if (meta.status === 'error') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={meta.retry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <ResourcePermissionsProvider permissions={meta.permissions}>
      <CompanyFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
