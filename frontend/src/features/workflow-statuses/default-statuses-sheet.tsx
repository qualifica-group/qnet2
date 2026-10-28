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
import {
  WorkflowStatusesEditor,
  type WorkflowStatusesEditorFeatures,
} from '@/features/workflow-statuses/workflow-statuses-editor'
import type {
  WorkflowStatusFormRow,
  WorkflowStatusGroupOption,
  WorkflowStatusRowPatch,
} from '@/features/workflow-statuses/types'

/** What the domain's default-statuses hook (fetch + explicit save) hands the sheet. */
export interface DefaultStatusesState<R extends WorkflowStatusFormRow> {
  rows: R[]
  isLoading: boolean
  isError: boolean
  refetch: () => unknown
  isSaving: boolean
  error: string | null
  addCustom: () => void
  removeCustom: (id: string) => void
  updateRow: (id: string, patch: WorkflowStatusRowPatch<R>) => void
  reorder: (orderedIds: string[]) => void
  save: () => Promise<boolean>
}

export interface DefaultStatusesSheetLabels {
  title: string
  subtitle: string
  loadError: string
  cancel: string
  save: string
  saving: string
}

interface DefaultStatusesSheetProps<R extends WorkflowStatusFormRow> extends WorkflowStatusesEditorFeatures {
  open: boolean
  onOpenChange: (open: boolean) => void
  state: DefaultStatusesState<R>
  labels: DefaultStatusesSheetLabels
  groups: WorkflowStatusGroupOption[]
  storageKey: string
}

/**
 * Sheet managing the GLOBAL default status set (spec 0047 Lane C): the same
 * `<WorkflowStatusesEditor>` used by a workflow's own `statuses` section, fed
 * by the domain's dedicated default-statuses hook. Unlike the workflow form,
 * this saves explicitly (a "Save" button) since editing here never bundles
 * with any other change.
 */
export function DefaultStatusesSheet<R extends WorkflowStatusFormRow>({
  open,
  onOpenChange,
  state,
  labels,
  groups,
  storageKey,
  showRequiresNote,
  showProgressPercentage,
}: DefaultStatusesSheetProps<R>) {
  const { t } = useTranslation()
  const { rows, isLoading, isError, refetch, isSaving, error, addCustom, removeCustom, updateRow, reorder, save } =
    state

  const handleSave = async () => {
    if (await save()) {
      onOpenChange(false)
    }
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="gap-0" storageKey={storageKey}>
        <SheetHeader>
          <SheetTitle>{labels.title}</SheetTitle>
          <SheetDescription>{labels.subtitle}</SheetDescription>
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
                {labels.loadError}
              </p>
              <Button variant="outline" size="sm" onClick={() => refetch()}>
                {t('common.retry')}
              </Button>
            </div>
          ) : (
            <WorkflowStatusesEditor
              rows={rows}
              groups={groups}
              showRequiresNote={showRequiresNote}
              showProgressPercentage={showProgressPercentage}
              onReorder={reorder}
              onAddCustom={addCustom}
              onRemoveCustom={removeCustom}
              onUpdateRow={updateRow}
              disabled={isSaving}
              error={error}
            />
          )}
        </div>

        <div className="flex justify-end gap-2 border-t p-4">
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isSaving}>
            {labels.cancel}
          </Button>
          <Button onClick={() => void handleSave()} disabled={isLoading || isError || isSaving}>
            {isSaving ? labels.saving : labels.save}
          </Button>
        </div>
      </SheetContent>
    </Sheet>
  )
}
