import { describe, expect, it } from 'vitest'
import { groupDistributionSeries } from '@/features/stats/distribution-series'
import type { DistributionItem } from '@/features/stats/types'

const OTHERS_LABEL = 'Others'

function item(key: string, value: number, color: string | null = null): DistributionItem {
  return { key, label: key, value, color }
}

describe('groupDistributionSeries', () => {
  it('assigns the fixed --chart-1..5 order to items without a color, never cycled', () => {
    const items = [item('a', 10), item('b', 20), item('c', 30)]

    const result = groupDistributionSeries(items, OTHERS_LABEL)

    expect(result.map((entry) => entry.color)).toEqual([
      'var(--chart-1)',
      'var(--chart-2)',
      'var(--chart-3)',
    ])
  })

  it('keeps a DB color token as-is and does not consume a --chart-N slot for it', () => {
    const items = [item('active', 10, 'teal'), item('draft', 5), item('archived', 2)]

    const result = groupDistributionSeries(items, OTHERS_LABEL)

    expect(result).toEqual([
      { key: 'active', label: 'active', value: 10, color: 'var(--color-teal-500)' },
      { key: 'draft', label: 'draft', value: 5, color: 'var(--chart-1)' },
      { key: 'archived', label: 'archived', value: 2, color: 'var(--chart-2)' },
    ])
  })

  it('falls back to the theme slot for an unrecognized color token', () => {
    const result = groupDistributionSeries([item('x', 1, 'not-a-real-token')], OTHERS_LABEL)

    expect(result).toEqual([{ key: 'x', label: 'x', value: 1, color: 'var(--chart-1)' }])
  })

  it('folds 7+ uncolored items into a single "Others" bucket carrying the summed value (AC-003)', () => {
    const items = Array.from({ length: 7 }, (_, index) => item(`s${index}`, index + 1))

    const result = groupDistributionSeries(items, OTHERS_LABEL)

    // 5 own theme colors + one "Others" bucket: the palette never cycles past slot 5.
    expect(result).toHaveLength(6)
    expect(result.slice(0, 5).map((entry) => entry.color)).toEqual([
      'var(--chart-1)',
      'var(--chart-2)',
      'var(--chart-3)',
      'var(--chart-4)',
      'var(--chart-5)',
    ])
    const others = result[5]
    expect(others.label).toBe(OTHERS_LABEL)
    expect(others.color).toBe('var(--muted-foreground)')
    // Sum of items 6 and 7 (values 6 + 7, 0-indexed s5/s6).
    expect(others.value).toBe(13)
  })

  it('caps at 6 total slices even when every item carries its own DB color token', () => {
    const items = [
      item('s1', 1, 'teal'),
      item('s2', 2, 'amber'),
      item('s3', 3, 'slate'),
      item('s4', 4, 'teal'),
      item('s5', 5, 'amber'),
      item('s6', 6, 'slate'),
      item('s7', 7, 'teal'),
    ]

    const result = groupDistributionSeries(items, OTHERS_LABEL)

    expect(result).toHaveLength(7) // 6 kept + "Others"
    expect(result[6]).toMatchObject({ label: OTHERS_LABEL, value: 7, color: 'var(--muted-foreground)' })
  })

  it('adds no "Others" entry when every item fits', () => {
    const result = groupDistributionSeries([item('a', 1), item('b', 2)], OTHERS_LABEL)

    expect(result.some((entry) => entry.label === OTHERS_LABEL)).toBe(false)
  })

  it('returns an empty array for an empty widget', () => {
    expect(groupDistributionSeries([], OTHERS_LABEL)).toEqual([])
  })
})
