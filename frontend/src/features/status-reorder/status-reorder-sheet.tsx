import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { SortableList, type SortableListItem } from '@/components/ui/sortable-list'
import { useStatusReorder } from '@/features/status-reorder/use-status-reorder'

interface StatusReorderRow extends SortableListItem {
  name: string
  systemKey: string | null
  /** `undefined` for every resource that projects no `is_active`: renders nothing. */
  isActive?: boolean
}

export interface StatusReorderSheetLabels {
  title: string
  subtitle: string
  dragHandleLabel: string
  /**
   * Marks a deactivated row. OPTIONAL on purpose: only the modules whose
   * for-select projects `is_active` (the five Task configurators, spec 0101)
   * ever receive such a row, and the others must not grow a required label
   * for a badge they can never render.
   */
  inactiveBadge?: string
  loadError: string
  saved: string
  forbidden: string
  genericError: string
}

interface StatusReorderSheetProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Resource segment of the endpoint: `pipeline-statuses` or `opportunity-statuses`. */
  resource: string
  /** Callers own their own i18n strings (mirrors `RelationSelectField`). */
  labels: StatusReorderSheetLabels
  /** Called after a successful reorder so the caller can refresh its own table view. */
  onReordered: () => void
}

/**
 * Drag & drop reorder sheet shared by `pipeline-statuses` and `opportunity-statuses`
 * (spec 0039 D-4): a thin wrapper over `<SortableList>` (`components/ui`,
 * @dnd-kit) fed by `useStatusReorder`. The system rows ("Nuovo"/"Chiuso")
 * pin to the leading/trailing edges — no handle, not draggable — and every
 * completed drag persists immediately (`POST /{resource}/reorder`),
 * optimistic on the visual order and reverted on a 403/422.
 *
 * A row the module has DEACTIVATED is listed too (the reorder endpoints
 * validate `ordered_ids` against the full set, so omitting it would 422 every
 * drag) and carries a badge explaining why it appears here but in no picker.
 * The test is `isActive === false`, never falsy: a resource projecting no
 * `is_active` leaves it `undefined` and must render no badge at all.
 */
export function StatusReorderSheet({
  open,
  onOpenChange,
  resource,
  labels,
  onReordered,
}: StatusReorderSheetProps) {
  const { t } = useTranslation()
  const { items, isLoading, isError, refetch, onReorder } = useStatusReorder({
    resource,
    enabled: open,
    labels,
    onReordered,
  })

  const rows = useMemo<StatusReorderRow[]>(
    () =>
      items.map((item) => ({
        id: String(item.id),
        name: item.name,
        systemKey: item.systemKey,
        isActive: item.isActive,
      })),
    [items],
  )

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="gap-0" storageKey={`sheet-width:${resource}-reorder`}>
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
            <SortableList
              items={rows}
              isPinned={(row) => row.systemKey !== null}
              dragHandleLabel={labels.dragHandleLabel}
              renderItem={(row) => (
                <span className="flex min-w-0 items-center gap-2">
                  <span className="truncate">{row.name}</span>
                  {row.isActive === false && labels.inactiveBadge ? (
                    <Badge variant="secondary" className="shrink-0">
                      {labels.inactiveBadge}
                    </Badge>
                  ) : null}
                </span>
              )}
              onReorder={onReorder}
            />
          )}
        </div>
      </SheetContent>
    </Sheet>
  )
}
