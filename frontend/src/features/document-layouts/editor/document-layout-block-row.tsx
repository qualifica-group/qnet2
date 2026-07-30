import { Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { BLOCK_TYPE_ICONS, blockSummary } from '@/features/document-layouts/editor/block-type-catalog'
import type { Block } from '@/features/document-layouts/layout-config'

interface DocumentLayoutBlockRowProps {
  block: Block
  selected: boolean
  hasError: boolean
  disabled: boolean
  onSelect: () => void
  onRemove: () => void
}

/** One block row inside a zone's `SortableList` — select + remove, the drag handle is added by `SortableList` itself. */
export function DocumentLayoutBlockRow({ block, selected, hasError, disabled, onSelect, onRemove }: DocumentLayoutBlockRowProps) {
  const { t } = useTranslation()
  const Icon = BLOCK_TYPE_ICONS[block.type]

  return (
    <div
      className={cn(
        'flex w-full min-w-0 items-center gap-1 rounded-md',
        selected && 'ring-2 ring-primary',
        hasError && 'ring-2 ring-destructive',
      )}
    >
      <button
        type="button"
        onClick={onSelect}
        aria-pressed={selected}
        className="flex min-w-0 flex-1 items-center gap-2 rounded-md px-1 py-0.5 text-left text-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
      >
        <Icon className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
        <span className="min-w-0 flex-1 truncate">{blockSummary(block, t)}</span>
      </button>
      <Button
        type="button"
        variant="ghost"
        size="icon-xs"
        className="shrink-0 text-muted-foreground hover:text-destructive"
        aria-label={t('documentLayouts.editor.removeBlock')}
        disabled={disabled}
        onClick={onRemove}
      >
        <Trash2 aria-hidden="true" />
      </Button>
    </div>
  )
}
