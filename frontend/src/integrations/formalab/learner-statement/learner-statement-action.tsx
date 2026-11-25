import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Calculator } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { ACTION_BUTTON_VARIANT } from '@/features/table/action-tone'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'
import { LearnerStatementDialog } from '@/integrations/formalab/learner-statement/learner-statement-dialog'
import { useFormalabWorkOrderLink } from '@/integrations/formalab/use-formalab-work-order-link'

/**
 * "Prospetto contabile discenti" in Amministrazione > Costi (spec 0427 AC-001):
 * mounted only while Formalab is active, shown only for a work order of a
 * Formalab category with a class chosen. The host gates it on `view_costs`.
 */
export function LearnerStatementAction({ workOrder }: { workOrder: WorkOrderDetailWithPermissions }) {
  const { t } = useTranslation('formalab')
  const [open, setOpen] = useState(false)
  const link = useFormalabWorkOrderLink(workOrder.id, true)

  if (!link.data?.eligible || link.data.link === null) {
    return null
  }

  return (
    <div className="flex justify-end px-4 pt-4">
      <Button type="button" variant={ACTION_BUTTON_VARIANT.success} size="sm" onClick={() => setOpen(true)}>
        <Calculator className="size-3.5" aria-hidden="true" />
        {t('learnerStatement.open')}
      </Button>
      <LearnerStatementDialog workOrderId={workOrder.id} open={open} onOpenChange={setOpen} />
    </div>
  )
}
