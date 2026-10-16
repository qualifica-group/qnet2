import type { CashFlow, Flow } from '@/features/management-control/cash-flow-types'
import { roundMoney, sumMoney } from '@/features/management-control/money-math'

const ZERO_FLOW: Flow = { receivable: 0, payable: 0, committed: 0, net: 0 }

function addFlows(a: Flow, b: Flow): Flow {
  return {
    receivable: roundMoney(a.receivable + b.receivable),
    payable: roundMoney(a.payable + b.payable),
    committed: roundMoney(a.committed + b.committed),
    net: roundMoney(a.net + b.net),
  }
}

/**
 * Scenario "the backlog is collected and paid within 30 days" (spec 0270 D-4): the backlog moves to
 * the first month of the horizon, the cumulative is recomputed, the other months stay as they are.
 * Per-company rows follow, so the two tables keep adding up to the same figures.
 */
export function applyBacklogScenario(data: CashFlow): CashFlow {
  const [first, ...rest] = data.months
  if (!first) {
    return data
  }

  let running = 0
  const months = [{ ...first, ...addFlows(first, data.backlog) }, ...rest].map((month) => {
    running = roundMoney(running + month.net)
    return { ...month, cumulative: running }
  })

  return {
    ...data,
    backlog: ZERO_FLOW,
    months,
    by_company: data.by_company.map((row) => ({
      ...row,
      backlog_net: 0,
      months: row.months.map((month, index) =>
        index === 0 ? { ...month, net: roundMoney(month.net + row.backlog_net) } : month,
      ),
    })),
  }
}

export interface HorizonTotals {
  receivable: number
  payable: number
  committed: number
  net: number
}

/** Figures of the months of the horizon (backlog and beyond excluded). */
export function horizonTotals(data: CashFlow): HorizonTotals {
  return {
    receivable: sumMoney(data.months.map((month) => month.receivable)),
    payable: sumMoney(data.months.map((month) => month.payable)),
    committed: sumMoney(data.months.map((month) => month.committed)),
    net: sumMoney(data.months.map((month) => month.net)),
  }
}
