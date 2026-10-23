import { useTranslation } from 'react-i18next'
import { Boxes } from 'lucide-react'
import { RecordSection } from '@/components/detail/record-panel'
import { StockBalanceList } from '@/features/stock/stock-balance-list'
import { useStockBalances } from '@/features/stock/use-stock-balances'

/** "Giacenze" of a tracked product: where it sits and who holds it (spec 0307). The caller gates it on `stock.viewAny`. */
export function StockProductSection({ productId }: { productId: number }) {
  const { t } = useTranslation()
  const query = useStockBalances({ product_id: productId }, true)

  return (
    <RecordSection title={t('stock.productSection.title')} icon={<Boxes />} full>
      <StockBalanceList query={query} mode="product" />
    </RecordSection>
  )
}
