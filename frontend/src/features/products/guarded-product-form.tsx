import { useTranslation } from 'react-i18next'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { ProductForm } from '@/features/products/product-form'
import type { ProductDetail } from '@/features/products/types'

interface GuardedProductFormProps {
  onSuccess: (product: ProductDetail) => void
  onCancel: () => void
}

/**
 * The product create form behind its leave guard: Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — leaving always asks first (spec 0195 D-9
 * applied to Prodotti, spec 0218). Shared by the modal screen and the
 * dedicated `/products/new` page.
 */
export function GuardedProductForm({ onSuccess, onCancel }: GuardedProductFormProps) {
  const { t } = useTranslation()
  const leaveGuard = useFormLeaveGuard({
    title: t('products.form.leaveConfirm.title'),
    description: t('products.form.leaveConfirm.description'),
    confirmLabel: t('products.form.leaveConfirm.confirm'),
    cancelLabel: t('products.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  const handleSuccess = (saved: ProductDetail) => {
    // Saved: the navigation to the new product's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(saved)
  }

  const handleCancel = async () => {
    if (await leaveGuard.confirmLeave()) {
      onCancel()
    }
  }

  return (
    <>
      {leaveGuard.navigationGuard}
      <ProductForm onSuccess={handleSuccess} onCancel={() => void handleCancel()} />
    </>
  )
}
