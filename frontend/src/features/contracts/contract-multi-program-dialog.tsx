import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { ContractMultiProgramLinesPanel } from '@/features/contracts/contract-multi-program-lines-panel'
import { ContractProgramSharedFields } from '@/features/contracts/contract-program-shared-fields'
import { MULTI_GROUP_PREFIX } from '@/features/contracts/contract-multi-program-schema'
import { useContractMultiProgramDialog } from '@/features/contracts/use-contract-multi-program-dialog'

const FORM_ID = 'contract-multi-program-form'
const HINT_ID = 'contract-multi-program-hint'

interface ContractMultiProgramDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Contracts picked in the grid (2..50). */
  contractIds: number[]
}

/**
 * "Programma commessa unica" (spec 0267): ONE work order spanning lines of
 * several offers of the same catalog branch. Left = lines by contract, right =
 * the work order's own fields (same pieces as the single-contract "Programma").
 * The dynamic attributes are not asked here, as in the single dialog: they are
 * filled from the created work order's form.
 */
export function ContractMultiProgramDialog({ open, onOpenChange, contractIds }: ContractMultiProgramDialogProps) {
  const { t } = useTranslation()
  const dialog = useContractMultiProgramDialog({ contractIds, open, onOpenChange })
  const { form } = dialog
  const prefix = 'contracts.actions.multiProgramDialog'

  return (
    <Dialog open={open} onOpenChange={(next) => !next && dialog.requestClose()}>
      <DialogContent size="xl" className="flex max-h-[85vh] flex-col gap-0 p-0">
        <DialogHeader className="shrink-0 rounded-t-lg border-b bg-surface p-4">
          <DialogTitle>{t(`${prefix}.title`)}</DialogTitle>
          <DialogDescription>{t(`${prefix}.description`)}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            id={FORM_ID}
            className="grid min-h-0 flex-1 grid-cols-1 content-start gap-4 overflow-y-auto p-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]"
            onSubmit={(event) => void dialog.submit(event)}
          >
            <section
              aria-label={t(`${prefix}.linesLabel`)}
              className="flex min-w-0 flex-col gap-3 self-start rounded-lg border bg-surface p-3"
            >
              <h3 className="text-sm font-semibold">{t(`${prefix}.linesLabel`)}</h3>
              <p id={HINT_ID} className="text-xs text-muted-foreground" aria-live="polite">
                {dialog.selected.length > 0
                  ? t(`${prefix}.selectionSummary`, { lines: dialog.selected.length, offers: dialog.offersCount })
                  : t(`${prefix}.hint`)}
              </p>
              <FormField
                control={form.control}
                name={`${MULTI_GROUP_PREFIX}.quote_line_ids`}
                render={() => (
                  <FormItem>
                    <ContractMultiProgramLinesPanel
                      data={dialog.data}
                      isPending={dialog.linesQuery.isPending}
                      isError={dialog.linesQuery.isError}
                      refusal={dialog.linesRefusal}
                      onRetry={() => void dialog.linesQuery.refetch()}
                      selected={dialog.selected}
                      selectableIds={dialog.selectableIds}
                      onSelectedChange={dialog.setSelected}
                      describedBy={HINT_ID}
                    />
                    <FormMessage />
                  </FormItem>
                )}
              />
            </section>

            <section
              aria-label={t(`${prefix}.fieldsLabel`)}
              className="flex min-w-0 flex-col gap-3 self-start rounded-lg border bg-surface p-3"
            >
              <h3 className="text-sm font-semibold">{t(`${prefix}.fieldsLabel`)}</h3>
              <FormField
                control={form.control}
                name={`${MULTI_GROUP_PREFIX}.title`}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('workOrders.form.title')}</FormLabel>
                    <FormControl>
                      <Input autoComplete="off" placeholder={t(`${prefix}.titleAutomatic`)} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <ContractProgramSharedFields control={form.control} prefix={MULTI_GROUP_PREFIX} required />
            </section>
          </form>
        </Form>

        <DialogFooter className="shrink-0 rounded-b-lg border-t bg-surface p-4">
          <Button type="button" variant="outline" className="bg-card" onClick={dialog.requestClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" form={FORM_ID} disabled={!dialog.hasEnoughOffers || dialog.isSaving}>
            {dialog.isSaving ? t(`${prefix}.saving`) : t(`${prefix}.confirm`)}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
