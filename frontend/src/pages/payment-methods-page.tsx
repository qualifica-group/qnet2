import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { PaymentMethodsTable } from '@/features/payment-methods/payment-methods-table'

/**
 * Payment methods page. Light composition only: gates access with
 * `payment-methods.viewAny` and mounts the thin Payment Methods adapter,
 * which in turn mounts the generic table (`domain="payment-methods"`). The
 * generic table owns config loading and loading/empty/error states; no
 * business logic or data fetching lives here.
 */
export default function PaymentMethodsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="payment-methods.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('paymentMethods.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <PaymentMethodsTable />
      </div>
    </Can>
  )
}
