import { useState } from 'react'
import { Play } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useConfirm } from '@/components/confirm-dialog-context'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { RunRecord } from '@/features/commission-settlement-runs/commission-settlement-run-detail-sheet'
import { CommissionSettlementRunSections } from '@/features/commission-settlement-runs/commission-settlement-run-rows'
import { useCommissionSettlementRunForm } from '@/features/commission-settlement-runs/use-commission-settlement-run-form'
import {
  COMMISSION_SETTLEMENT_RUNS_DOMAIN,
  type CommissionSettlementRunResult,
} from '@/features/commission-settlement-runs/types'

/** DOM id bridging the header's and the footer's launch actions to the RHF `<form>`. */
const RUN_FORM_ID = 'commission-settlement-run-form'

interface OpenRunSheetProps {
  onClose: () => void
  onLaunched: () => void
}

/** Mounted only while open: the draft first, then the run it created, as the record "Visualizza" shows. */
function OpenRunSheet({ onClose, onLaunched }: OpenRunSheetProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [result, setResult] = useState<CommissionSettlementRunResult | null>(null)
  const { form, submit, isPending } = useCommissionSettlementRunForm((created) => {
    setResult(created)
    onLaunched()
  })
  const draft = useDraftInlineEdit(form)
  // Read during render: RHF only tracks the formState flags a render subscribed to.
  const { isDirty } = form.formState
  const title = t(result ? 'commissionSettlementRuns.result.title' : 'commissionSettlementRuns.form.title')
  const description = t(result ? 'commissionSettlementRuns.result.description' : 'commissionSettlementRuns.form.description')

  // Every way out (Annulla, X, overlay, Esc) asks while an unlaunched draft holds changes.
  const leave = async () => {
    if (result === null && isDirty) {
      const accepted = await confirm({
        title: t('commissionSettlementRuns.form.leaveConfirm.title'),
        description: t('commissionSettlementRuns.form.leaveConfirm.description'),
        confirmLabel: t('commissionSettlementRuns.form.leaveConfirm.confirm'),
        cancelLabel: t('commissionSettlementRuns.form.leaveConfirm.cancel'),
        tone: 'warning',
      })
      if (!accepted) {
        return
      }
    }
    onClose()
  }

  return (
    <Sheet open onOpenChange={(open) => (open ? undefined : void leave())}>
      <SheetContent className="gap-0" showCloseButton={false} storageKey={`sheet-width:${COMMISSION_SETTLEMENT_RUNS_DOMAIN}`}>
        <SheetToolbar closeLabel={t('common.close')} />
        <SheetHeader className="sr-only">
          <SheetTitle>{title}</SheetTitle>
          <SheetDescription>{description}</SheetDescription>
        </SheetHeader>
        {result ? (
          <RunRecord result={result} />
        ) : (
          <Form {...form}>
            <RecordCreateLayout
              formId={RUN_FORM_ID}
              onSubmit={submit}
              title={title}
              subtitle={description}
              media={<DetailMonogram name={title} icon={<Play />} />}
              isSubmitting={isPending}
              serverError={null}
              onCancel={() => void leave()}
              labels={{
                save: t('commissionSettlementRuns.form.launch'),
                saving: t('commissionSettlementRuns.form.launching'),
                cancel: t('common.cancel'),
              }}
            >
              <CommissionSettlementRunSections inline={draft} />
            </RecordCreateLayout>
          </Form>
        )}
      </SheetContent>
    </Sheet>
  )
}

interface CommissionSettlementRunSheetProps {
  open: boolean
  onClose: () => void
  /** Called once the run has been executed, so the caller refreshes its grid. */
  onLaunched: () => void
}

/**
 * "Nuovo lancio" as a record in a side sheet (spec 0265 D-4), like every other
 * creation: rows closed until clicked (Fatto/Ripristina), Lancia runs it, and
 * the sheet then shows the run it created.
 */
export function CommissionSettlementRunSheet({ open, onClose, onLaunched }: CommissionSettlementRunSheetProps) {
  return open ? <OpenRunSheet onClose={onClose} onLaunched={onLaunched} /> : null
}
