import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
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
import { SearchableSelect, type SearchableSelectOption } from '@/components/ui/searchable-select'
import { buildPendingMove, getMoveDestinations, nodeName } from '@/features/org-chart/move-destinations'
import type { MoveDestination, OrgView } from '@/features/org-chart/move-types'
import type { OrgTreeNode } from '@/features/org-chart/tree-nodes'
import { moveErrorMessage, useMoveOrgNode } from '@/features/org-chart/use-move-org-node'

/** Option id of the "become a root" destination (real ids are positive). */
const ROOT_OPTION_ID = 0

interface OrgMoveDialogProps {
  view: OrgView
  node: OrgTreeNode
  allNodes: OrgTreeNode[]
  /** Set when the move comes from a drop: the destination is already chosen. */
  initialDestination: MoveDestination | null
  onClose: () => void
}

function optionId(destination: MoveDestination): number {
  return destination.id ?? ROOT_OPTION_ID
}

/** Destination picker plus confirmation for a move, shared by "Sposta in…" and drag and drop. */
export function OrgMoveDialog({ view, node, allNodes, initialDestination, onClose }: OrgMoveDialogProps) {
  const { t } = useTranslation()
  const mutation = useMoveOrgNode()
  const [selectedId, setSelectedId] = useState<number | null>(initialDestination ? optionId(initialDestination) : null)

  const rootLabel = t(view === 'functions' ? 'orgChart.move.rootFunction' : 'orgChart.move.rootPerson')
  const destinations = useMemo(() => getMoveDestinations(view, node, allNodes), [view, node, allNodes])
  const options = useMemo<SearchableSelectOption[]>(
    () =>
      destinations.map((destination) => ({
        id: optionId(destination),
        name: destination.kind === 'root' ? rootLabel : destination.name,
        depth: destination.depth,
      })),
    [destinations, rootLabel],
  )

  const selected = destinations.find((destination) => optionId(destination) === selectedId) ?? null
  const pending = selected ? buildPendingMove(view, node, selected, allNodes) : null
  const name = nodeName(node)

  function confirm(): void {
    if (!pending) return
    mutation.mutate(pending.move, {
      onSuccess: () => {
        toast.success(t('orgChart.move.success', { name }))
        onClose()
      },
      onError: (error) => toast.error(moveErrorMessage(error, t)),
    })
  }

  return (
    <Dialog open onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{t('orgChart.move.title', { name })}</DialogTitle>
          <DialogDescription>{t('orgChart.move.description')}</DialogDescription>
        </DialogHeader>
        <SearchableSelect
          value={selectedId}
          onChange={setSelectedId}
          options={options}
          labels={{
            placeholder: t('orgChart.move.select.placeholder'),
            searchPlaceholder: t('orgChart.move.select.search'),
            empty: t('orgChart.move.select.empty'),
            noMatch: t('orgChart.move.select.noMatch'),
            error: t('orgChart.move.select.empty'),
            retry: t('orgChart.move.select.retry'),
            triggerLabel: t('orgChart.move.select.label'),
          }}
        />
        {pending ? (
          <div className="flex flex-col gap-2 text-sm">
            <p>
              {t('orgChart.move.summary', {
                subject: pending.subjectName,
                from: pending.fromName ?? rootLabel,
                to: pending.toName ?? rootLabel,
              })}
            </p>
            {pending.move.type === 'function' ? (
              <p role="note" className="text-xs text-muted-foreground">
                {t('orgChart.move.functionWarning')}
              </p>
            ) : null}
          </div>
        ) : null}
        <DialogFooter>
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('orgChart.move.cancel')}
          </Button>
          <Button type="button" onClick={confirm} disabled={!pending || mutation.isPending}>
            {t('orgChart.move.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
