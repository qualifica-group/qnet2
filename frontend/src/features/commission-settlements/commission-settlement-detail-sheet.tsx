import { useTranslation } from 'react-i18next'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { CommissionSettlementDetailView } from '@/features/commission-settlements/commission-settlement-detail'
import { COMMISSION_SETTLEMENTS_DOMAIN } from '@/features/commission-settlements/types'

interface CommissionSettlementDetailSheetProps {
  settlementId: number | null
  onClose: () => void
  onChanged: () => void
}

/** "Visualizza" row action: the settlement in a resizable side sheet, without leaving the list. */
export function CommissionSettlementDetailSheet({ settlementId, onClose, onChanged }: CommissionSettlementDetailSheetProps) {
  const { t } = useTranslation()
  return (
    <Sheet open={settlementId !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent className="gap-0" showCloseButton={false} storageKey={`sheet-width:${COMMISSION_SETTLEMENTS_DOMAIN}`}>
        <SheetToolbar closeLabel={t('common.close')} />
        <SheetHeader className="sr-only">
          <SheetTitle>{t('commissionSettlements.title')}</SheetTitle>
          <SheetDescription>{t('commissionSettlements.subtitle')}</SheetDescription>
        </SheetHeader>
        {settlementId !== null ? (
          <CommissionSettlementDetailView settlementId={settlementId} onChanged={onChanged} onDeleted={onClose} />
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
