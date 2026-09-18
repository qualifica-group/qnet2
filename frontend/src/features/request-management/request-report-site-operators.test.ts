import { describe, expect, it } from 'vitest'
import {
  followSiteSelection,
  operatorsForSites,
} from '@/features/request-management/request-report-site-operators'
import type { RequestReportOperator } from '@/features/request-management/report-api'

const OPERATORS: RequestReportOperator[] = [
  { key: '7', label: 'Ada', site_keys: ['3'] },
  { key: '9', label: 'Zoe', site_keys: ['3', '5'] },
  { key: '11', label: 'Bruno', site_keys: ['5'] },
  { key: 'unassigned', label: 'Unassigned', site_keys: [] },
]

describe('operatorsForSites', () => {
  it('offers every operator while every site is chosen', () => {
    expect(operatorsForSites(OPERATORS, ['3', '5'], ['3', '5'])).toEqual(OPERATORS)
  })

  it('offers every operator when no site is on offer', () => {
    expect(operatorsForSites(OPERATORS, [], [])).toEqual(OPERATORS)
  })

  it('offers only the members of the chosen sites, never "unassigned"', () => {
    expect(operatorsForSites(OPERATORS, ['5'], ['3', '5']).map((operator) => operator.key)).toEqual(['9', '11'])
  })
})

describe('followSiteSelection', () => {
  it('keeps "all" as all of the new list', () => {
    expect(followSiteSelection(['7', '9', '11', 'unassigned'], ['7', '9', '11', 'unassigned'], ['9', '11'])).toEqual([
      '9',
      '11',
    ])
  })

  it('keeps only the partial picks still on offer', () => {
    expect(followSiteSelection(['7', '11'], ['7', '9', '11', 'unassigned'], ['9', '11'])).toEqual(['11'])
  })
})
