import { describe, expect, it } from 'vitest'
import { buildNavBlocks, isSingleChildGroup } from '@/features/navigation/nav-blocks'
import type { NavigationItem } from '@/features/navigation/types'

function node(key: string, overrides: Partial<NavigationItem> = {}): NavigationItem {
  return { key, label: `navigation.${key}`, icon: null, route: `/${key}`, type: 'item', children: [], ...overrides }
}

const group = (key: string, children: NavigationItem[]) => node(key, { route: null, children })

describe('isSingleChildGroup', () => {
  it('is true only for a route-less, non-section parent with exactly one child', () => {
    expect(isSingleChildGroup(group('tasks-group', [node('tasks')]))).toBe(true)
    expect(isSingleChildGroup(group('tasks-group', [node('tasks'), node('task-types')]))).toBe(false)
    expect(isSingleChildGroup(node('leads', { children: [node('imports')] }))).toBe(false)
    expect(isSingleChildGroup(node('configuration', { type: 'section', route: null, children: [node('tags')] }))).toBe(false)
  })
})

describe('buildNavBlocks', () => {
  it('keeps menu order, batching plain entries and giving sections and single-child groups their own block', () => {
    const blocks = buildNavBlocks([
      node('dashboard'),
      group('tasks-group', [node('tasks')]),
      node('time-entries'),
      group('products-group', [node('products'), node('stock')]),
      node('configuration', { type: 'section', route: null, children: [node('tags')] }),
    ])

    expect(blocks.map((block) => (block.kind === 'items' ? block.items.map((item) => item.key) : `§${block.section.key}`))).toEqual([
      ['dashboard'],
      '§tasks-group',
      ['time-entries', 'products-group'],
      '§configuration',
    ])
  })
})
