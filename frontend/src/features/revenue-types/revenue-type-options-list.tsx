import { Check } from 'lucide-react'
import { cn } from '@/lib/utils'
import { SubAccountGroupedList } from '@/features/chart-of-accounts/sub-account-select/sub-account-grouped-list'
import { revenueTypeLabel } from '@/features/revenue-types/group-options'
import type { RevenueTypeGroup, RevenueTypeOption } from '@/features/revenue-types/types'

interface RevenueTypeOptionsListProps {
  groups: RevenueTypeGroup[]
  value: number | null
  onSelect: (option: RevenueTypeOption) => void
}

/** Revenue sub-accounts grouped mastro > conto (shared list); only the sub-accounts are options. */
export function RevenueTypeOptionsList({ groups, value, onSelect }: RevenueTypeOptionsListProps) {
  return (
    <SubAccountGroupedList
      groups={groups}
      renderOption={(option) => (
        <div
          role="option"
          aria-selected={option.id === value}
          tabIndex={0}
          onClick={() => onSelect(option)}
          onKeyDown={(event) => {
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault()
              onSelect(option)
            }
          }}
          className="flex cursor-pointer items-center gap-2 rounded-sm py-1 pl-6 pr-2 text-sm outline-none hover:bg-accent focus-visible:bg-accent focus-visible:ring-[2px] focus-visible:ring-ring/50"
        >
          <Check className={cn('size-3.5 shrink-0', option.id === value ? 'opacity-100' : 'opacity-0')} aria-hidden="true" />
          <span className="min-w-0 truncate">{revenueTypeLabel(option)}</span>
        </div>
      )}
    />
  )
}
