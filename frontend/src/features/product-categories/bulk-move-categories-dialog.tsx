import { useCallback, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { SearchableSelect } from '@/components/ui/searchable-select'
import {
  ROOT_PARENT_VALUE,
  collectSubtreeIds,
  flattenCategoryTree,
} from '@/features/product-categories/flatten-tree'
import { useBulkMoveCategories } from '@/features/product-categories/use-bulk-move-categories'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { BulkMoveConflictError } from '@/features/product-categories/types'

export interface BulkMoveCategoriesDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** The categories to move; also what the destination picker excludes. */
  selectedIds: number[]
  /** Ran after the move succeeded, with how many rows actually changed parent. */
  onMoved: (moved: number) => void
}

/**
 * "Move under…" popup for the product-categories bulk action (spec 0063).
 *
 * The destination picker reuses the same flattened, indented tree as the
 * category form, minus the selected categories and their descendants — a
 * client-side cycle block that mirrors, never replaces, the server guard.
 *
 * The move is all-or-nothing: a refused batch (nested selection, cycle,
 * business-function override) answers 422 with the offending rows, which are
 * listed inline while the dialog stays open so the user can pick another
 * destination or fix the selection.
 */
export function BulkMoveCategoriesDialog({
  open,
  onOpenChange,
  selectedIds,
  onMoved,
}: BulkMoveCategoriesDialogProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()
  const [destination, setDestination] = useState<number>(ROOT_PARENT_VALUE)
  const [conflict, setConflict] = useState<BulkMoveConflictError | null>(null)

  const moveMutation = useBulkMoveCategories({
    onSuccess: (result) => {
      onMoved(result.moved)
      onOpenChange(false)
    },
  })

  const destinationOptions = useMemo(() => {
    const nodes = treeQuery.data ?? []
    const excluded = new Set<number>()

    for (const id of selectedIds) {
      for (const subtreeId of collectSubtreeIds(nodes, id)) {
        excluded.add(subtreeId)
      }
    }

    return [
      { id: ROOT_PARENT_VALUE, name: t('productCategories.form.noParent') },
      ...flattenCategoryTree(nodes).filter((option) => !excluded.has(option.id)),
    ]
  }, [treeQuery.data, selectedIds, t])

  const handleOpenChange = useCallback(
    (next: boolean) => {
      if (!next) {
        setDestination(ROOT_PARENT_VALUE)
        setConflict(null)
      }
      onOpenChange(next)
    },
    [onOpenChange],
  )

  const handleConfirm = useCallback(async () => {
    setConflict(null)

    try {
      await moveMutation.mutateAsync({
        category_ids: selectedIds,
        parent_id: destination === ROOT_PARENT_VALUE ? null : destination,
      })
    } catch (error) {
      const conflictError = axios.isAxiosError(error)
        ? (error.response?.data?.errors as BulkMoveConflictError | undefined)
        : undefined

      if (conflictError?.reason) {
        setConflict(conflictError)
        return
      }

      toast.error(t('productCategories.bulkMove.genericError'))
    }
  }, [moveMutation, selectedIds, destination, t])

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('productCategories.bulkMove.title')}</DialogTitle>
          <DialogDescription>
            {t('productCategories.bulkMove.description', { count: selectedIds.length })}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-2">
          <Label htmlFor="bulk-move-destination" className="text-xs">
            {t('productCategories.bulkMove.destination')}
          </Label>
          <SearchableSelect
            id="bulk-move-destination"
            value={destination}
            onChange={setDestination}
            options={destinationOptions}
            isPending={treeQuery.isPending}
            isError={treeQuery.isError}
            onRetry={() => void treeQuery.refetch()}
            labels={{
              placeholder: t('productCategories.form.parentPlaceholder'),
              searchPlaceholder: t('productCategories.form.parentSearch'),
              empty: t('productCategories.form.parentEmpty'),
              noMatch: t('productCategories.form.parentNoMatch'),
              error: t('productCategories.form.parentError'),
              retry: t('common.retry'),
            }}
          />
        </div>

        {conflict ? (
          <div
            role="alert"
            className="flex flex-col gap-1.5 rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-xs"
          >
            <p className="flex items-center gap-1.5 font-medium text-destructive">
              <AlertTriangle aria-hidden="true" className="size-3.5" />
              {t(`productCategories.bulkMove.reasons.${conflict.reason}`)}
            </p>
            <ul className="flex flex-col gap-1 text-muted-foreground">
              {conflict.conflicts.map((row) => (
                <li key={row.id}>
                  <span className="font-medium text-foreground">{row.name}</span> — {row.detail}
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        <DialogFooter>
          <Button variant="outline" className="bg-card" onClick={() => handleOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button onClick={() => void handleConfirm()} disabled={moveMutation.isPending}>
            {t('productCategories.bulkMove.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
