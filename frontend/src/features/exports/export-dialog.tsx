import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { GridApi } from 'ag-grid-community'
import { Download, FileSpreadsheet, FileText } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { buildExportGridState } from '@/features/exports/build-export-grid-state'
import { useExport } from '@/features/exports/use-export'
import { ExportProgress } from '@/features/exports/export-progress'
import type { ExportFormat } from '@/features/exports/types'
import type { TableColumn, TableRow } from '@/features/table/types'

/** The two formats enabled by the backend (`config('exports.formats')`). */
const FORMATS: readonly ExportFormat[] = ['csv', 'xlsx']

const FORMAT_ICON: Record<ExportFormat, typeof FileText> = {
  csv: FileText,
  xlsx: FileSpreadsheet,
}

/** Narrow form: the sheet opens at this width until the user resizes it. */
const EXPORT_SHEET_DEFAULT_WIDTH = 448

export interface ExportDialogProps {
  /** Resource key that selects the backend `TableDefinition` (`/exports/{domain}`). */
  domain: string
  open: boolean
  onOpenChange: (open: boolean) => void
  /** The live grid API, or null before the grid is ready. */
  gridApi: GridApi<TableRow> | null
  /** The domain's column catalog, for resolving each colId's i18n label. */
  columns: TableColumn[]
  /** The synthetic row-actions column id, excluded from the export. */
  actionsColumnId: string
  /** The applied global search term (may be empty). */
  search: string
  /**
   * Row-set scope to one parent record (spec 0067 D-5, e.g. an Opportunity's
   * Quotes panel), forwarded verbatim into the create payload. Omitted/null
   * ⇒ today's unscoped export, unchanged.
   */
  opportunityId?: number | null
}

/**
 * Generic per-table export wizard (spec 0014), parametrized on `domain`.
 * Orchestration (create/poll/download) lives in `useExport`; this component
 * only captures the format choice, shows a summary of the grid state that
 * will be sent, and routes to the progress/download step once a run exists.
 */
export function ExportDialog({
  domain,
  open,
  onOpenChange,
  gridApi,
  columns,
  actionsColumnId,
  search,
  opportunityId,
}: ExportDialogProps) {
  const { t } = useTranslation()
  const exportState = useExport({ domain })
  const [format, setFormat] = useState<ExportFormat>('csv')

  const gridState = useMemo(() => {
    if (!gridApi) return null
    return buildExportGridState({ gridApi, columns, actionsColumnId, search, t })
  }, [gridApi, columns, actionsColumnId, search, t])

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      exportState.reset()
      setFormat('csv')
    }
    onOpenChange(next)
  }

  const handleExport = () => {
    if (!gridState) return
    exportState.create({
      format,
      columns: gridState.columns,
      sortModel: gridState.sortModel.length > 0 ? gridState.sortModel : undefined,
      filterModel:
        Object.keys(gridState.filterModel).length > 0 ? gridState.filterModel : undefined,
      search: gridState.search !== '' ? gridState.search : undefined,
      ...(opportunityId != null ? { opportunityId } : {}),
    })
  }

  const exportRun = exportState.exportRun

  return (
    <Sheet open={open} onOpenChange={handleOpenChange}>
      <SheetContent
        className="gap-0"
        defaultWidth={EXPORT_SHEET_DEFAULT_WIDTH}
        storageKey="sheet-width:exports"
      >
        <SheetHeader>
          <SheetTitle>{t('exports.title')}</SheetTitle>
          <SheetDescription>{t('exports.subtitle')}</SheetDescription>
        </SheetHeader>

        <div className="flex flex-1 flex-col gap-4 overflow-auto p-4">
          {exportRun ? (
            <ExportProgress
              exportRun={exportRun}
              onDownload={exportState.download}
              isDownloading={exportState.isDownloading}
              downloadError={exportState.downloadError}
              onClose={() => handleOpenChange(false)}
            />
          ) : (
            <>
              <div
                role="radiogroup"
                aria-label={t('exports.fields.format')}
                className="flex gap-2"
              >
                {FORMATS.map((value) => {
                  const Icon = FORMAT_ICON[value]
                  const selected = format === value
                  return (
                    <button
                      key={value}
                      type="button"
                      role="radio"
                      aria-checked={selected}
                      onClick={() => setFormat(value)}
                      className={cn(
                        // bg-card (white) lifts each option off the gray dialog
                        // surface (bg-background); a bare border blended tone-on-tone.
                        'flex flex-1 items-center justify-center gap-2 rounded-lg border bg-card px-3 py-2 text-sm font-medium transition-colors',
                        selected
                          ? 'border-primary ring-1 ring-primary/40 text-foreground'
                          : 'border-border text-muted-foreground hover:text-foreground hover:bg-muted/60',
                      )}
                    >
                      <Icon aria-hidden="true" className="size-4" />
                      {t(`exports.formats.${value}`)}
                    </button>
                  )
                })}
              </div>

              {gridState ? (
                <dl className="grid grid-cols-2 gap-3 text-sm">
                  <div>
                    <dt className="text-muted-foreground">{t('exports.stateSummary.columns')}</dt>
                    <dd className="font-medium">{gridState.columns.length}</dd>
                  </div>
                  <div>
                    <dt className="text-muted-foreground">{t('exports.stateSummary.filters')}</dt>
                    <dd className="font-medium">{Object.keys(gridState.filterModel).length}</dd>
                  </div>
                  <div>
                    <dt className="text-muted-foreground">{t('exports.stateSummary.sort')}</dt>
                    <dd className="font-medium">
                      {gridState.sortModel.length > 0
                        ? t('exports.stateSummary.sortActive')
                        : t('exports.stateSummary.sortNone')}
                    </dd>
                  </div>
                  <div className="col-span-2">
                    <dt className="text-muted-foreground">{t('exports.stateSummary.search')}</dt>
                    <dd className="truncate font-medium">
                      {gridState.search !== ''
                        ? gridState.search
                        : t('exports.stateSummary.searchNone')}
                    </dd>
                  </div>
                </dl>
              ) : null}

              {exportState.createError ? (
                <p className="text-sm text-destructive" role="alert">
                  {exportState.createError}
                </p>
              ) : null}

              <div className="flex justify-end">
                <Button
                  type="button"
                  onClick={handleExport}
                  disabled={exportState.isCreating || !gridState}
                >
                  <Download aria-hidden="true" />
                  {exportState.isCreating ? t('exports.buttons.exporting') : t('exports.buttons.export')}
                </Button>
              </div>
            </>
          )}
        </div>
      </SheetContent>
    </Sheet>
  )
}
