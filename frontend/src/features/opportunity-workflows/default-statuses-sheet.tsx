import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { WorkflowStatusesEditor } from '@/features/opportunity-workflows/workflow-statuses-editor'
import { useDefaultStatuses } from '@/features/opportunity-workflows/use-default-statuses'

interface DefaultStatusesSheetProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Sheet managing the GLOBAL default status set (spec 0047 Lane C): the same
 * `<WorkflowStatusesEditor>` used by a workflow's own `statuses` section, fed
 * by the dedicated `GET`/`PUT /opportunity-workflows/default-statuses`
 * endpoints via `useDefaultStatuses`. Unlike the workflow form, this saves
 * explicitly (a "Save" button) since editing here never bundles with any
 * other change.
 */
export function DefaultStatusesSheet({ open, onOpenChange }: DefaultStatusesSheetProps) {
  const { t } = useTranslation()
  const {
    rows,
    isLoading,
    isError,
    refetch,
    isSaving,
    error,
    addCustom,
    removeCustom,
    updateRow,
    markValidated,
    reorder,
    save,
  } = useDefaultStatuses({
    enabled: open,
    labels: {
      saved: t('opportunityWorkflows.defaultStatuses.saved'),
      forbidden: t('opportunityWorkflows.defaultStatuses.forbidden'),
      genericError: t('opportunityWorkflows.defaultStatuses.genericError'),
      nameRequired: t('opportunityWorkflows.form.statuses.nameRequired'),
    },
  })

  const handleSave = async () => {
    if (await save()) {
      onOpenChange(false)
    }
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="gap-0" storageKey="sheet-width:opportunity-workflows-default-statuses">
        <SheetHeader>
          <SheetTitle>{t('opportunityWorkflows.defaultStatuses.title')}</SheetTitle>
          <SheetDescription>{t('opportunityWorkflows.defaultStatuses.subtitle')}</SheetDescription>
        </SheetHeader>

        <div className="flex flex-1 flex-col gap-3 overflow-y-auto p-4">
          {isLoading ? (
            <div className="flex flex-col gap-1.5" aria-hidden="true">
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-9 w-full" />
            </div>
          ) : isError ? (
            <div className="flex flex-col items-start gap-3">
              <p className="text-sm text-destructive" role="alert">
                {t('opportunityWorkflows.defaultStatuses.loadError')}
              </p>
              <Button variant="outline" size="sm" onClick={() => refetch()}>
                {t('common.retry')}
              </Button>
            </div>
          ) : (
            <WorkflowStatusesEditor
              rows={rows}
              onReorder={reorder}
              onAddCustom={addCustom}
              onRemoveCustom={removeCustom}
              onUpdateRow={updateRow}
              onMarkValidated={markValidated}
              disabled={isSaving}
              error={error}
            />
          )}
        </div>

        <div className="flex justify-end gap-2 border-t p-4">
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isSaving}>
            {t('opportunityWorkflows.form.cancel')}
          </Button>
          <Button onClick={() => void handleSave()} disabled={isLoading || isError || isSaving}>
            {isSaving ? t('opportunityWorkflows.form.saving') : t('opportunityWorkflows.form.save')}
          </Button>
        </div>
      </SheetContent>
    </Sheet>
  )
}
