import { useCallback } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Power, PowerOff } from 'lucide-react'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { setProductsActivation } from '@/features/products/api'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'

interface UseProductBulkActivationArgs {
  /** Refreshes the grid and whatever else lists the products. */
  onChanged: () => void
}

/**
 * "Activate" / "Deactivate" of the selected products (spec 0366 D-6): one
 * `activation` call, no per-row refusal. The backend re-authorizes.
 */
export function useProductBulkActivation({ onChanged }: UseProductBulkActivationArgs) {
  const { t, i18n } = useTranslation()

  const mutation = useMutation({
    mutationFn: setProductsActivation,
    onSuccess: (result, variables) => {
      toast.success(t(`products.bulkActivation.${variables.is_active ? 'activated' : 'deactivated'}`, { count: result.updated }))
      onChanged()
    },
    onError: (error) => toast.error(apiErrorMessage(error, t('products.bulkActivation.error'), i18n)),
  })

  const getBulkActions = useCallback(
    (selection: TableSelection): BulkAction[] => {
      const ids = selection.ids.map(Number)
      return [
        {
          key: 'activate',
          label: t('products.bulkActivation.activate'),
          icon: Power,
          disabled: mutation.isPending,
          onSelect: () => mutation.mutate({ product_ids: ids, is_active: true }),
        },
        {
          key: 'deactivate',
          label: t('products.bulkActivation.deactivate'),
          icon: PowerOff,
          disabled: mutation.isPending,
          onSelect: () => mutation.mutate({ product_ids: ids, is_active: false }),
        },
      ]
    },
    [mutation, t],
  )

  return { getBulkActions }
}
