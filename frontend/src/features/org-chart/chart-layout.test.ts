import { describe, expect, it } from 'vitest'
import { CARD_WIDTH, layoutChart, type ChartCard } from '@/features/org-chart/chart-layout'
import { buildFunctionTree } from '@/features/org-chart/build-function-tree'
import { buildPeopleTree } from '@/features/org-chart/build-people-tree'
import { directoryPerson, FUNCTIONS_FIXTURE, PEOPLE_FIXTURE } from '@/features/org-chart/fixtures.test-support'
import { chartChildren, memberPeople } from '@/features/org-chart/tree-nodes'
import { collectExpandableKeys, flattenVisible } from '@/features/org-chart/tree-utils'

const NONE: ReadonlySet<string> = new Set()

function byKey(cards: ChartCard[], key: string): ChartCard {
  const card = cards.find((item) => item.node.key === key)
  if (!card) throw new Error(`missing card ${key}`)
  return card
}

function center(card: ChartCard): number {
  return card.x + CARD_WIDTH / 2
}

describe('chart children vs members (spec 0274 D-2)', () => {
  const functions = buildFunctionTree(FUNCTIONS_FIXTURE)

  it('draws people as cards before the sub-functions and still lists them as members', () => {
    const [company, all] = functions
    expect(chartChildren(company).map((node) => node.key)).toEqual(['f:1/p:10', 'f:1/f:2', 'f:1/f:3'])
    expect(memberPeople(company).map((node) => node.key)).toEqual(['f:1/p:10'])
    expect(chartChildren(all).map((node) => node.key)).toEqual(['unassigned/p:13'])
    expect(memberPeople(all).map((node) => node.key)).toEqual(['unassigned/p:13'])
  })

  it('counts the cards with children as expandable and visible', () => {
    expect(collectExpandableKeys(functions)).toEqual(['f:1', 'f:1/f:2', 'unassigned'])
    expect(flattenVisible(functions, NONE).map((entry) => entry.node.key)).toEqual(['f:1', 'f:1/p:10', 'f:1/f:2', 'f:1/f:2/p:11', 'f:1/f:2/p:12', 'f:1/f:3', 'unassigned', 'unassigned/p:13'])
  })
})

describe('layoutChart (AC-001)', () => {
  it('puts children one level below, centres the parent over them and never overlaps cards', () => {
    const people = [
      ...PEOPLE_FIXTURE,
      directoryPerson(5, 'Elena Riva', [2]),
      directoryPerson(6, 'Fabio Sala', [2]),
    ]
    const { cards, edges, width, height } = layoutChart(buildPeopleTree(people), NONE)
    const anna = byKey(cards, 'p:1')
    const bruno = byKey(cards, 'p:1/p:2')
    const carla = byKey(cards, 'p:1/p:3')

    expect(bruno.y).toBeGreaterThan(anna.y)
    expect(center(anna)).toBe((center(bruno) + center(carla)) / 2)
    expect(bruno).toMatchObject({ level: 2, posInSet: 1, setSize: 2, parentKey: 'p:1' })

    const sameRow = (a: ChartCard, b: ChartCard) => a.y === b.y && a !== b
    cards.forEach((a) => cards.filter((b) => sameRow(a, b)).forEach((b) => {
      expect(Math.abs(a.x - b.x)).toBeGreaterThanOrEqual(CARD_WIDTH)
    }))
    cards.forEach((card) => expect(card.x + CARD_WIDTH).toBeLessThanOrEqual(width))
    // One stem + one curve per child, for every expanded parent.
    const expandedParents = cards.filter((card) => card.expanded)
    const childCount = cards.filter((card) => card.parentKey !== null).length
    expect(edges).toHaveLength(expandedParents.length + childCount)
    expect(height).toBeGreaterThan(byKey(cards, 'p:1/p:2/p:5').y)
  })

  it('hides the subtree of a collapsed card and reports how many cards are hidden', () => {
    const { cards } = layoutChart(buildPeopleTree(PEOPLE_FIXTURE), new Set(['p:1']))
    expect(cards.map((card) => card.node.key)).toEqual(['p:1', 'p:4'])
    expect(byKey(cards, 'p:1')).toMatchObject({ expanded: false, hasChildren: true, hiddenCount: 3 })
    expect(byKey(cards, 'p:4')).toMatchObject({ hasChildren: false, hiddenCount: 0 })
  })

  it('stacks more than four leaf children in a four-column grid joined by straight lines', () => {
    const reports = [2, 3, 4, 5, 6, 7].map((id) => directoryPerson(id, `Report ${id}`, [1]))
    const { cards, edges } = layoutChart(buildPeopleTree([directoryPerson(1, 'Boss'), ...reports]), NONE)
    const row0 = ['p:1/p:2', 'p:1/p:3', 'p:1/p:4', 'p:1/p:5'].map((key) => byKey(cards, key))
    const fifth = byKey(cards, 'p:1/p:6')

    expect(new Set(row0.map((card) => card.y)).size).toBe(1)
    expect(fifth.x).toBe(row0[0].x)
    expect(fifth.y).toBeGreaterThan(row0[0].y)
    expect(fifth).toMatchObject({ stackedOnKey: 'p:1/p:2', level: 2, posInSet: 5, setSize: 6 })
    expect(center(byKey(cards, 'p:1'))).toBe((center(row0[0]) + center(row0[3])) / 2)
    expect(edges.find((edge) => edge.key === 'p:1/p:2|p:1/p:6')?.path).toMatch(/^M [\d.]+ [\d.]+ V [\d.]+$/)
  })

  it('wraps unconnected roots onto new shelves instead of one endless row', () => {
    const loners = Array.from({ length: 12 }, (_, index) => directoryPerson(index + 1, `Person ${String(index).padStart(2, '0')}`))
    const { cards, width, height } = layoutChart(buildPeopleTree(loners), NONE)
    const rows = new Set(cards.map((card) => card.y))

    expect(rows.size).toBeGreaterThan(1)
    expect(width).toBeLessThan(12 * CARD_WIDTH)
    expect(height).toBeGreaterThan(Math.max(...cards.map((card) => card.y)))
    expect(cards.map((card) => card.posInSet)).toEqual(Array.from({ length: 12 }, (_, index) => index + 1))
  })

  it('returns an empty canvas for no nodes', () => {
    expect(layoutChart([], NONE)).toEqual({ cards: [], edges: [], width: 0, height: 0 })
  })
})
