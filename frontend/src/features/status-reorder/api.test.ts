import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import { fetchStatusesForReorder } from '@/features/status-reorder/api'

/**
 * Spec 0101 AC-047/AC-049. The reorder endpoints validate `ordered_ids`
 * against the FULL set of reorderable rows, while a for-select answers with
 * the ACTIVE ones only — so the sheet MUST ask for the inactive rows too, or a
 * single deactivated row makes every drag 422 with an incomplete set. This
 * suite pins that request parameter: it is one line, invisible in the UI, and
 * exactly the kind of thing a refactor drops silently.
 */

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn() },
}))

const getMock = vi.mocked(apiClient.get)

/** The query object `fetchForSelect` actually put on the wire. */
function sentParams(): Record<string, unknown> {
  const config = getMock.mock.calls[0][1] as { params: Record<string, unknown> }
  return config.params
}

beforeEach(() => {
  getMock.mockReset()
  getMock.mockResolvedValue({ data: { items: [], pagination: {} } })
})

describe('fetchStatusesForReorder — request', () => {
  it('asks for the inactive rows too, so ordered_ids can carry the complete set', async () => {
    await fetchStatusesForReorder('task-priorities')

    expect(getMock).toHaveBeenCalledWith('/task-priorities/for-select', expect.anything())
    expect(sentParams()).toMatchObject({ include_inactive: 1 })
  })

  it('reads the whole lookup in one page', async () => {
    await fetchStatusesForReorder('task-statuses')

    expect(sentParams()).toMatchObject({ offset: 0, limit: 100 })
  })
})

describe('fetchStatusesForReorder — mapping', () => {
  it('maps label to name and keeps the system key that pins a row', async () => {
    getMock.mockResolvedValue({
      data: {
        items: [
          { id: 1, label: 'Aperto', meta: { system_key: 'open' } },
          { id: 2, label: 'Custom', meta: { system_key: null } },
        ],
        pagination: {},
      },
    })

    await expect(fetchStatusesForReorder('task-statuses')).resolves.toEqual([
      { id: 1, name: 'Aperto', systemKey: 'open' },
      { id: 2, name: 'Custom', systemKey: null },
    ])
  })

  it('falls back to a null system key when the resource projects no meta', async () => {
    // The four pure lookups (spec 0101 D-4): no system row exists, so every
    // row must come back unpinned and land in `ordered_ids`.
    getMock.mockResolvedValue({
      data: { items: [{ id: 5, label: 'Alta' }], pagination: {} },
    })

    await expect(fetchStatusesForReorder('task-priorities')).resolves.toEqual([
      { id: 5, name: 'Alta', systemKey: null },
    ])
  })
})
