import type { Column, FilterOpenedEvent, ProvidedColumnGroup } from 'ag-grid-community'
import { describe, expect, it, vi } from 'vitest'
import { createFilterTruncationNotice } from '@/components/data-table/filter-truncation-notice'

function openedEvent(column: Column | ProvidedColumnGroup): FilterOpenedEvent {
  return { column } as FilterOpenedEvent
}

const column = (colId: string) => ({ getColId: () => colId }) as unknown as Column

describe('createFilterTruncationNotice', () => {
  it('stays silent for a column whose filter the user never opened (saved filter restored on reload)', () => {
    const show = vi.fn()
    const notice = createFilterTruncationNotice(show)

    notice.notifyTruncated('company_name')

    expect(show).not.toHaveBeenCalled()
  })

  it('shows the notice once per opened column, not on every reopen', () => {
    const show = vi.fn()
    const notice = createFilterTruncationNotice(show)

    notice.markOpened(openedEvent(column('company_name')))
    notice.notifyTruncated('company_name')
    notice.markOpened(openedEvent(column('company_name')))
    notice.notifyTruncated('company_name')

    expect(show).toHaveBeenCalledOnce()
  })

  it('notifies each opened column on its own', () => {
    const show = vi.fn()
    const notice = createFilterTruncationNotice(show)

    notice.markOpened(openedEvent(column('company_name')))
    notice.markOpened(openedEvent(column('email')))
    notice.notifyTruncated('company_name')
    notice.notifyTruncated('email')

    expect(show).toHaveBeenCalledTimes(2)
  })

  it('ignores a column group opening', () => {
    const show = vi.fn()
    const notice = createFilterTruncationNotice(show)

    notice.markOpened(openedEvent({ getGroupId: () => 'group' } as unknown as ProvidedColumnGroup))
    notice.notifyTruncated('group')

    expect(show).not.toHaveBeenCalled()
  })
})
