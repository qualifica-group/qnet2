/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchProductTypology, productTypologyDetailQueryKey } from '@/features/product-typologies/api'
import { ProductTypologyForm } from '@/features/product-typologies/product-typology-form'
import { ProductTypologyDetailView } from '@/features/product-typologies/product-typology-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { ProductTypologyDetail } from '@/features/product-typologies/types'

/**
 * Content-only `product-typologies` screens for the module registry (spec
 * 0042): fetch + the presentational view/form, no page chrome. The detail
 * edits its rows in place, so `onEdit` is never used; each save reports
 * through `onChanged`.
 */
export function ProductTypologyDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: productTypology,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(productTypologyDetailQueryKey(id), () => fetchProductTypology(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('productTypologies.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !productTypology) {
    return <DetailLoading />
  }

  return <ProductTypologyDetailView productTypology={productTypology} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a typology being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function ProductTypologyFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('productTypologies.form.leaveConfirm.title'),
    description: t('productTypologies.form.leaveConfirm.description'),
    confirmLabel: t('productTypologies.form.leaveConfirm.confirm'),
    cancelLabel: t('productTypologies.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: ProductTypologyDetail) => {
    queryClient.invalidateQueries({ queryKey: productTypologyDetailQueryKey(saved.id) })
    // Saved: the navigation to the new record's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(saved.id)
  }

  const handleCancel = () => {
    void leaveGuard.confirmLeave().then((leave) => {
      if (leave) {
        onCancel()
      }
    })
  }

  return (
    <>
      {leaveGuard.navigationGuard}
      <ProductTypologyForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'product-typologies',
  basePath: '/product-typologies',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.productTypologies',
  DetailScreen: ProductTypologyDetailScreen,
  FormScreen: ProductTypologyFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
