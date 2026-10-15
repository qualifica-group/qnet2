import { FilePlus2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useConfirm } from '@/components/confirm-dialog-context'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { CommissionAdjustmentSections } from '@/features/commission-settlements/commission-adjustment-rows'
import { useCommissionAdjustmentForm } from '@/features/commission-settlements/use-commission-adjustment-form'
import type { AdjustmentMode } from '@/features/commission-settlements/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const ADJUSTMENT_FORM_ID = 'commission-adjustment-form'

const SHEET_WIDTH_KEY = 'sheet-width:commission-adjustments'

interface OpenAdjustmentSheetProps {
  mode: AdjustmentMode
  onClose: () => void
  onSaved: () => void
}

/** Mounted only while open, so every way out (Annulla, X, overlay, Esc) reads the draft's own dirty state. */
function OpenAdjustmentSheet({ mode, onClose, onSaved }: OpenAdjustmentSheetProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const { form, submit, isPending } = useCommissionAdjustmentForm(mode, () => {
    onSaved()
    onClose()
  })
  const draft = useDraftInlineEdit(form)
  // Read during render: RHF only tracks the formState flags a render subscribed to.
  const { isDirty } = form.formState
  const title = t(mode.type === 'edit' ? 'commissionSettlements.adjustment.editTitle' : 'commissionSettlements.adjustment.title')

  const leave = async () => {
    if (isDirty) {
      const accepted = await confirm({
        title: t('commissionSettlements.adjustment.leaveConfirm.title'),
        description: t('commissionSettlements.adjustment.leaveConfirm.description'),
        confirmLabel: t('commissionSettlements.adjustment.leaveConfirm.confirm'),
        cancelLabel: t('commissionSettlements.adjustment.leaveConfirm.cancel'),
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
      <SheetContent className="gap-0" showCloseButton={false} storageKey={SHEET_WIDTH_KEY}>
        <SheetToolbar closeLabel={t('common.close')} />
        <SheetHeader className="sr-only">
          <SheetTitle>{title}</SheetTitle>
          <SheetDescription>{t('commissionSettlements.adjustment.description')}</SheetDescription>
        </SheetHeader>
        <Form {...form}>
          <RecordCreateLayout
            formId={ADJUSTMENT_FORM_ID}
            onSubmit={submit}
            title={title}
            subtitle={t('commissionSettlements.adjustment.description')}
            media={<DetailMonogram name={title} icon={<FilePlus2 />} />}
            isSubmitting={isPending}
            serverError={null}
            onCancel={() => void leave()}
            labels={{
              save: t('commissionSettlements.adjustment.save'),
              saving: t('commissionSettlements.adjustment.saving'),
              cancel: t('common.cancel'),
            }}
          >
            <CommissionAdjustmentSections inline={draft} mode={mode} />
          </RecordCreateLayout>
        </Form>
      </SheetContent>
    </Sheet>
  )
}

interface CommissionAdjustmentSheetProps {
  /** `null` keeps the sheet closed; a mode opens it (standalone, inside a draft, or editing a movement). */
  mode: AdjustmentMode | null
  onClose: () => void
  /** Called after a successful save so the caller refreshes its grid. */
  onSaved: () => void
}

/**
 * "Nuova rettifica" / "Modifica rettifica" as a record in a side sheet (spec
 * 0265 D-2), like every other creation: rows closed until clicked
 * (Fatto/Ripristina), Salva validates and saves everything at once.
 */
export function CommissionAdjustmentSheet({ mode, onClose, onSaved }: CommissionAdjustmentSheetProps) {
  return mode ? <OpenAdjustmentSheet mode={mode} onClose={onClose} onSaved={onSaved} /> : null
}
