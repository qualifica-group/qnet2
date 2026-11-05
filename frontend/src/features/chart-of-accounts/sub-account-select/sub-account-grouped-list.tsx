import { Fragment, type ReactNode } from 'react'
import type { SubAccountGroup } from '@/features/chart-of-accounts/sub-account-select/sub-account-groups'

interface SubAccountGroupedListProps<T extends { id: number }> {
  groups: SubAccountGroup<T>[]
  renderOption: (option: T) => ReactNode
}

/**
 * Options grouped mastro > conto. Headings are labelled groups, never options:
 * only the sub-accounts (rendered by the caller) are selectable.
 */
export function SubAccountGroupedList<T extends { id: number }>({ groups, renderOption }: SubAccountGroupedListProps<T>) {
  return (
    <>
      {groups.map((group) => (
        <div key={group.key} role="group" aria-label={group.heading} className="py-1">
          <p aria-hidden="true" className="truncate px-2 pt-1 text-xs font-semibold text-foreground">
            {group.heading}
          </p>
          {group.accounts.map((account) => {
            const options = account.options.map((option) => <Fragment key={option.id}>{renderOption(option)}</Fragment>)
            return account.heading ? (
              <div key={account.key} role="group" aria-label={account.heading}>
                <p aria-hidden="true" className="truncate px-2 pl-4 pt-0.5 text-xs text-muted-foreground">
                  {account.heading}
                </p>
                {options}
              </div>
            ) : (
              <Fragment key={account.key}>{options}</Fragment>
            )
          })}
        </div>
      ))}
    </>
  )
}
