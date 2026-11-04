import { useCallback, useId, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Popover as PopoverPrimitive } from 'radix-ui'
import { ChevronsUpDown, X } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { revenueTypeLabel } from '@/features/revenue-types/group-options'
import { RevenueTypeOptionsList } from '@/features/revenue-types/revenue-type-options-list'
import {
  idList,
  useRevenueTypeLabels,
  useRevenueTypeOptions,
} from '@/features/revenue-types/use-revenue-types-for-select'
import type { RevenueTypeOption, RevenueTypeRef } from '@/features/revenue-types/types'

export interface RevenueTypeSelectProps {
  /** Selected sub-account id, `null` when none. */
  value: number | null
  onChange: (value: number | null) => void
  /** The current value's ref when the caller already holds it (skips the label lookup). */
  selected?: Pick<RevenueTypeRef, 'id' | 'code' | 'name'> | null
  disabled?: boolean
  /** Trigger text when empty; defaults to the generic placeholder. */
  placeholder?: string
  /** Accessible name of the trigger. */
  'aria-label'?: string
  className?: string
  id?: string
  'aria-describedby'?: string
  'aria-invalid'?: boolean
}

/**
 * Shared revenue sub-account picker (spec 0350 D-10): options grouped
 * mastro > conto, searchable by code or name, with a clear button. Only active
 * sub-accounts can be picked; an inactive current value is still shown.
 */
export function RevenueTypeSelect({
  value,
  onChange,
  selected = null,
  disabled,
  placeholder,
  'aria-label': ariaLabel,
  className,
  id,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
}: RevenueTypeSelectProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [portalContainer, setPortalContainer] = useState<HTMLElement | null>(null)
  const listboxId = useId()
  const debounced = useDebouncedValue(search.trim())
  const options = useRevenueTypeOptions({ search: debounced, enabled: open })
  const needsLabel = value !== null && selected?.id !== value
  const labels = useRevenueTypeLabels(idList(needsLabel ? value : null), needsLabel)

  const current = value === null ? null : selected?.id === value ? selected : (labels.get(value) ?? null)
  const triggerText = current ? revenueTypeLabel(current) : value !== null ? '…' : null

  const select = useCallback(
    (option: RevenueTypeOption) => {
      onChange(option.id)
      setOpen(false)
    },
    [onChange],
  )

  // Inside a modal the popup portals back into it, so wheel scrolling is not blocked as outside content.
  const setTrigger = useCallback((node: HTMLButtonElement | null) => {
    const modal = node?.closest('[data-slot="sheet-content"], [data-slot="dialog-content"]')
    setPortalContainer(modal instanceof HTMLElement ? modal : null)
  }, [])

  return (
    <PopoverPrimitive.Root open={open} onOpenChange={setOpen}>
      <PopoverPrimitive.Trigger asChild>
        <button
          ref={setTrigger}
          type="button"
          id={id}
          role="combobox"
          disabled={disabled}
          aria-label={ariaLabel ?? t('revenueTypes.select.label')}
          aria-haspopup="listbox"
          aria-expanded={open}
          aria-describedby={ariaDescribedBy}
          aria-invalid={ariaInvalid}
          className={cn(
            'flex min-h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border border-field-border bg-field px-3 py-1.5 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20',
            className,
          )}
        >
          <span className={cn('min-w-0 flex-1 truncate text-left', triggerText === null && 'text-muted-foreground')}>
            {triggerText ?? placeholder ?? t('revenueTypes.select.placeholder')}
          </span>
          <span className="flex shrink-0 items-center gap-1">
            {value !== null && !disabled ? (
              <span
                role="button"
                tabIndex={0}
                aria-label={t('revenueTypes.select.clear')}
                className="rounded-sm p-0.5 outline-none hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50"
                onClick={(event) => {
                  event.stopPropagation()
                  onChange(null)
                }}
                onKeyDown={(event) => {
                  if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault()
                    event.stopPropagation()
                    onChange(null)
                  }
                }}
              >
                <X className="size-3.5" aria-hidden="true" />
              </span>
            ) : null}
            <ChevronsUpDown className="size-4 opacity-50" aria-hidden="true" />
          </span>
        </button>
      </PopoverPrimitive.Trigger>
      <PopoverPrimitive.Portal container={portalContainer ?? undefined}>
        <PopoverPrimitive.Content
          align="start"
          sideOffset={4}
          className="z-50 w-(--radix-popover-trigger-width) min-w-64 max-w-[calc(100vw-2rem)] rounded-md border bg-popover p-1 text-popover-foreground shadow-md outline-none"
          onOpenAutoFocus={(event) => event.preventDefault()}
        >
          <div className="p-1">
            <Input
              autoFocus
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={t('revenueTypes.select.search')}
              aria-label={t('revenueTypes.select.search')}
              aria-controls={listboxId}
            />
          </div>
          <div id={listboxId} role="listbox" aria-label={t('revenueTypes.select.label')} className="max-h-64 overflow-y-auto p-1">
            {options.isPending ? (
              <div className="space-y-1 p-1">
                <Skeleton className="h-4 w-1/2" />
                <Skeleton className="h-4 w-3/4" />
                <Skeleton className="h-4 w-2/3" />
              </div>
            ) : options.isError ? (
              <div className="flex flex-col items-center gap-2 px-2 py-4 text-center">
                <p className="text-sm text-muted-foreground">{t('revenueTypes.select.error')}</p>
                <button
                  type="button"
                  onClick={() => void options.refetch()}
                  className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                  {t('common.retry')}
                </button>
              </div>
            ) : options.groups.length === 0 ? (
              <p className="px-2 py-4 text-center text-sm text-muted-foreground">{t('revenueTypes.select.empty')}</p>
            ) : (
              <RevenueTypeOptionsList groups={options.groups} value={value} onSelect={select} />
            )}
          </div>
        </PopoverPrimitive.Content>
      </PopoverPrimitive.Portal>
    </PopoverPrimitive.Root>
  )
}
