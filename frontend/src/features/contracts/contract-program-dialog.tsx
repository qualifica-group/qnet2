import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Form } from '@/components/ui/form'
import { ContractProgramGroupsPanel } from '@/features/contracts/contract-program-groups-panel'
import { ContractProgramLinesPanel } from '@/features/contracts/contract-program-lines-panel'
import { useContractProgramDialog } from '@/features/contracts/use-contract-program-dialog'
import type { WorkOrderDetail } from '@/features/work-orders/types'

interface ContractProgramDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  contractId: number
  /** Called after a successful save with the created work orders (group order): the caller refreshes the Commesse tab. */
  onCreated: (workOrders: WorkOrderDetail[]) => void
}

/**
 * "Programma" (spec 0215, evolving 0095/0096/0124): groups the contract
 * offer's REVENUE lines into N work orders in one session and saves them
 * all-or-nothing with a single "Crea N commesse". Left panel = the lines,
 * right panel = the groups; the logic lives in `useContractProgramDialog`.
 *
 * Le "Informazioni aggiuntive" (attributi dinamici) e i Partecipanti NON
 * compaiono qui (decisione utente 2026-09-04): si compilano dopo, dal form
 * della commessa generata.
 */
export function ContractProgramDialog({ open, onOpenChange, contractId, onCreated }: ContractProgramDialogProps) {
  const { t } = useTranslation()
  const dialog = useContractProgramDialog({ contractId, open, onOpenChange, onCreated })
  const { form, groups, lines, linesQuery, selected } = dialog

  return (
    <>
      <Dialog open={open} onOpenChange={(next) => !next && dialog.requestClose()}>
        {/* Flex column with a scrolling body: with many lines/groups the content would otherwise
            grow past the viewport. The popups of the selects portal into the content node, so the
            inner scroller never clips them. */}
        <DialogContent size="xl" className="flex max-h-[85vh] flex-col gap-0 p-0">
          <DialogHeader className="shrink-0 rounded-t-lg border-b bg-surface p-4">
            <DialogTitle>{t('contracts.actions.program')}</DialogTitle>
            <DialogDescription>{t('contracts.actions.programDialog.description')}</DialogDescription>
          </DialogHeader>

          <Form {...form}>
            <form
              id="contract-program-form"
              className="grid min-h-0 flex-1 grid-cols-1 content-start gap-4 overflow-y-auto p-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]"
              onSubmit={(event) => void dialog.submit(event)}
            >
              <ContractProgramLinesPanel
                lines={lines}
                isPending={linesQuery.isPending}
                isError={linesQuery.isError}
                onRetry={() => void linesQuery.refetch()}
                selected={selected}
                onSelectedChange={dialog.setSelected}
                groupOfLine={dialog.groupOfLine}
                toolbar={{
                  groupLabels: dialog.groupLabels,
                  atLimit: dialog.atLimit,
                  hasBulkTargets: dialog.targetLines(selected).length > 0,
                  onNewGroup: () => dialog.runAction(() => dialog.newGroupFromLines(selected)),
                  onAddToGroup: (index) => dialog.runAction(() => dialog.addLinesToGroup(index, selected)),
                  onOnePerLine: () => dialog.runAction(() => dialog.oneGroupPerLine(selected)),
                  onByCategory: () => dialog.runAction(() => dialog.groupByCategory(selected)),
                }}
              />
              <ContractProgramGroupsPanel
                control={form.control}
                errors={form.formState.errors}
                groups={groups}
                lines={lines}
                atLimit={dialog.atLimit}
                collapsedKeys={dialog.collapsedKeys}
                onToggleGroup={dialog.toggleGroup}
                onApplyToAll={dialog.applyCommonToAll}
                onDuplicate={dialog.duplicateGroup}
                onRemove={dialog.removeGroup}
                onRemoveLine={dialog.removeLineFromGroup}
              />
            </form>
          </Form>

          <DialogFooter className="shrink-0 rounded-b-lg border-t bg-surface p-4">
            <Button type="button" variant="outline" className="bg-card" onClick={dialog.requestClose}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" form="contract-program-form" disabled={groups.length === 0 || dialog.isSaving}>
              {dialog.isSaving
                ? t('contracts.actions.programDialog.saving')
                : t('contracts.actions.programDialog.confirm', { count: groups.length })}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={dialog.leaveOpen} onOpenChange={dialog.setLeaveOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('contracts.actions.programDialog.leaveTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('contracts.actions.programDialog.leaveDescription', { count: groups.length })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('contracts.actions.programDialog.leaveCancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={dialog.close}>{t('contracts.actions.programDialog.leaveConfirm')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
