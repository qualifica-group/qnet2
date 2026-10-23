import { useTranslation } from 'react-i18next'
import { PackageOpen, Undo2 } from 'lucide-react'
import { toast } from 'sonner'
import { useConfirm } from '@/components/confirm-dialog-context'
import { RecordSection } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { useAbilities } from '@/features/auth/use-abilities'
import { formatQuantityWithUnit } from '@/features/stock/quantity'
import { StockBalanceList } from '@/features/stock/stock-balance-list'
import { STOCK_MANAGE_PERMISSION, type StockBalance } from '@/features/stock/types'
import { useRecordStockMovement } from '@/features/stock/use-record-stock-movement'
import { useStockBalances } from '@/features/stock/use-stock-balances'

/**
 * "Dotazioni" of a person: the stock rows they hold, with "Restituisci" (RETURN of the whole quantity after a
 * confirmation) for an actor with `stock.manage`. The caller mounts it only for `stock.viewAny`.
 */
export function StockUserSection({ userId }: { userId: number }) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const { can } = useAbilities()
  const query = useStockBalances({ user_id: userId }, true)
  const record = useRecordStockMovement()
  const canManage = can(STOCK_MANAGE_PERMISSION)

  const handleReturn = async (balance: StockBalance) => {
    const confirmed = await confirm({
      title: t('stock.userSection.returnConfirmTitle'),
      description: t('stock.userSection.returnConfirm', {
        quantity: formatQuantityWithUnit(balance),
        product: balance.product.name,
        site: balance.operational_site.label,
      }),
      confirmLabel: t('stock.userSection.return'),
    })
    if (!confirmed) {
      return
    }
    try {
      await record.mutateAsync({
        type: 'RETURN',
        product_id: balance.product.id,
        operational_site_id: balance.operational_site.id,
        user_id: userId,
        quantity: balance.quantity,
      })
      toast.success(t('stock.userSection.returned'))
    } catch {
      toast.error(t('stock.userSection.returnError'))
    }
  }

  return (
    <RecordSection title={t('stock.userSection.title')} icon={<PackageOpen />} full>
      <StockBalanceList
        query={query}
        mode="user"
        renderAction={
          canManage
            ? (balance) => (
                <Button type="button" variant="secondary" size="sm" disabled={record.isPending} onClick={() => void handleReturn(balance)}>
                  <Undo2 aria-hidden="true" />
                  {t('stock.userSection.return')}
                </Button>
              )
            : undefined
        }
      />
    </RecordSection>
  )
}
