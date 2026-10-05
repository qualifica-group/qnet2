import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useFinancialAccountFormMeta } from '@/features/financial-accounts/use-financial-account-form-meta'
import { FinancialAccountFormBody } from '@/features/financial-accounts/financial-account-form-body'
import type {
  FinancialAccountDetail,
  FinancialAccountFormMode,
} from '@/features/financial-accounts/types'

interface FinancialAccountFormProps {
  mode: FinancialAccountFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (account: FinancialAccountDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for creating and editing a financial account.
 * Resolves the resource's `ResourcePermissions` (edit: from the loaded detail,
 * create: from `GET /meta/financial-accounts`), then hands off to the body.
 */
export function FinancialAccountForm(props: FinancialAccountFormProps) {
  const { t } = useTranslation()
  const meta = useFinancialAccountFormMeta(props.mode)

  if (meta.status === 'loading') {
    return (
      <div className="flex flex-col gap-4 p-4" aria-hidden="true">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
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
      <FinancialAccountFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
