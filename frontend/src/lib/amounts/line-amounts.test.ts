import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import {
  documentAmountsPerRate,
  fromCents,
  lineAmounts,
  round2,
  toCents,
  type AmountsCents,
} from '@/lib/amounts/line-amounts'

interface CaseLine {
  quantity: string
  unit_price: string
  vat_rate: string | null
  scale: number
}

interface ExpectedAmounts {
  net: string
  vat: string
  total: string
}

interface AmountCase {
  name: string
  mode: 'per_rate' | 'per_line'
  lines: CaseLine[]
  expected: ExpectedAmounts & { lines: ExpectedAmounts[] }
}

/** Table shared with the backend engine test (spec 0253): same inputs, same expected values. */
const CASES_FILE = resolve(__dirname, '../../../../docs/specs/0253-amount-cases.json')
const { cases } = JSON.parse(readFileSync(CASES_FILE, 'utf8')) as { cases: AmountCase[] }

const toCentsOf = (amount: string): number => Math.round(Number(amount) * 100)
const centsOf = (expected: ExpectedAmounts): AmountsCents => ({
  net: toCentsOf(expected.net),
  vat: toCentsOf(expected.vat),
  total: toCentsOf(expected.total),
})

function compute(testCase: AmountCase): { lines: AmountsCents[]; totals: AmountsCents } {
  const input = testCase.lines.map((line) => ({
    quantity: Number(line.quantity),
    unitPrice: Number(line.unit_price),
    vatRate: line.vat_rate === null ? null : Number(line.vat_rate),
  }))
  if (testCase.mode === 'per_rate') {
    return documentAmountsPerRate(input, testCase.lines[0].scale)
  }
  const lines = input.map((line, i) => lineAmounts(line.quantity, line.unitPrice, line.vatRate, testCase.lines[i].scale))
  const net = lines.reduce((sum, line) => sum + line.net, 0)
  const vat = lines.reduce((sum, line) => sum + line.vat, 0)
  return { lines, totals: { net, vat, total: net + vat } }
}

describe('line-amounts engine (spec 0253 AC-010)', () => {
  it('loads a non-empty shared case table', () => {
    expect(cases.length).toBeGreaterThan(0)
  })

  it.each(cases.map((testCase) => [testCase.name, testCase] as const))('matches the backend: %s', (_name, testCase) => {
    const result = compute(testCase)

    expect(result.lines).toEqual(testCase.expected.lines.map(centsOf))
    expect(result.totals).toEqual(centsOf(testCase.expected))
  })

  it('keeps the document VAT equal to the sum of the line VATs', () => {
    const { lines, totals } = documentAmountsPerRate(
      [
        { quantity: 1, unitPrice: 10.01, vatRate: 22 },
        { quantity: 1, unitPrice: 10.01, vatRate: 22 },
        { quantity: 1, unitPrice: 10.01, vatRate: 22 },
      ],
      2,
    )

    expect(totals.vat).toBe(lines.reduce((sum, line) => sum + line.vat, 0))
  })

  it('returns zero amounts for no lines and ignores non-finite inputs', () => {
    expect(documentAmountsPerRate([], 2).totals).toEqual({ net: 0, vat: 0, total: 0 })
    expect(lineAmounts(Number.NaN, 5, 22, 2)).toEqual({ net: 0, vat: 0, total: 0 })
  })

  it('rounds half away from zero without float drift', () => {
    expect(toCents(1.005)).toBe(101)
    expect(toCents(-1.005)).toBe(-101)
    expect(round2(2.675)).toBe(2.68)
    expect(fromCents(101)).toBe(1.01)
  })
})
