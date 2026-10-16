import { describe, expect, it } from 'vitest'
import { makeCashFlow } from '@/features/management-control/cash-flow-fixtures'
import { applyBacklogScenario, horizonTotals } from '@/features/management-control/cash-flow-scenario'

describe('applyBacklogScenario', () => {
  it('AC-012 moves the backlog onto the first month and recomputes the cumulative', () => {
    const scenario = applyBacklogScenario(makeCashFlow())

    expect(scenario.months[0]).toMatchObject({ receivable: 1500, payable: 600, committed: 200, net: 700, cumulative: 700 })
    expect(scenario.months.map((month) => month.cumulative)).toEqual([700, 400, 900])
    expect(scenario.backlog).toEqual({ receivable: 0, payable: 0, committed: 0, net: 0 })
  })

  it('AC-012 leaves the other months and the beyond unchanged', () => {
    const base = makeCashFlow()
    const scenario = applyBacklogScenario(base)

    expect(scenario.months[1]).toEqual({ ...base.months[1], cumulative: 400 })
    expect(scenario.months[2]).toEqual({ ...base.months[2], cumulative: 900 })
    expect(scenario.beyond).toEqual(base.beyond)
  })

  it('AC-012 moves the backlog of every company onto its first month, keeping the sums', () => {
    const scenario = applyBacklogScenario(makeCashFlow())

    expect(scenario.by_company[0]).toMatchObject({ backlog_net: 0, months: [{ net: 550 }, { net: -100 }, { net: 300 }] })
    scenario.months.forEach((month, index) => {
      const sum = scenario.by_company.reduce((total, row) => total + (row.months[index]?.net ?? 0), 0)
      expect(sum).toBe(month.net)
    })
  })

  it('AC-012 does not mutate the input', () => {
    const base = makeCashFlow()
    applyBacklogScenario(base)

    expect(base).toEqual(makeCashFlow())
  })

  it('AC-012 returns the data as is when there are no months', () => {
    const base = makeCashFlow({ months: [] })

    expect(applyBacklogScenario(base)).toBe(base)
  })

  it('AC-012 rounds to the cent', () => {
    const base = makeCashFlow({
      backlog: { receivable: 0.1, payable: 0, committed: 0, net: 0.1 },
      months: [{ month: '2026-10', receivable: 0.2, payable: 0, committed: 0, net: 0.2, cumulative: 0.2 }],
    })

    expect(applyBacklogScenario(base).months[0]).toMatchObject({ receivable: 0.3, net: 0.3, cumulative: 0.3 })
  })
})

describe('horizonTotals', () => {
  it('AC-014 sums the months of the horizon, leaving backlog and beyond out', () => {
    expect(horizonTotals(makeCashFlow())).toEqual({ receivable: 1600, payable: 800, committed: 100, net: 700 })
  })
})
