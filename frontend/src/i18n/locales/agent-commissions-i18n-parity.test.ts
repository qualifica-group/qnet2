import { describe, expect, it } from 'vitest'
import {
  agentFiscalProfiles as enProfiles,
  commissionAccruals as enAccruals,
  enasarcoParameters as enEnasarco,
} from '@/i18n/locales/en-agent-commissions'
import {
  commissionSettlementRuns as enRuns,
  commissionSettlements as enSettlements,
} from '@/i18n/locales/en-commission-settlements'
import {
  commissionSettlementRuns as itRuns,
  commissionSettlements as itSettlements,
} from '@/i18n/locales/it-commission-settlements'
import {
  agentFiscalProfiles as itProfiles,
  commissionAccruals as itAccruals,
  enasarcoParameters as itEnasarco,
} from '@/i18n/locales/it-agent-commissions'
import { enums as enEnums } from '@/i18n/locales/en-enums'
import { enums as itEnums } from '@/i18n/locales/it-enums'
import { navigation as enNavigation } from '@/i18n/locales/en-navigation'
import { navigation as itNavigation } from '@/i18n/locales/it-navigation'
import { permissions as enPermissions } from '@/i18n/locales/en-permissions'
import { permissions as itPermissions } from '@/i18n/locales/it-permissions'

/** Specs 0212 AC-022 / 0213 AC-024: same leaf keys in IT and EN for the agent commissions modules, no empty value. */

type I18nTree = { [key: string]: string | I18nTree }

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

const PAIRS: [string, I18nTree, I18nTree][] = [
  ['agentFiscalProfiles', itProfiles, enProfiles],
  ['commissionAccruals', itAccruals, enAccruals],
  ['enasarcoParameters', itEnasarco, enEnasarco],
  ['commissionSettlementRuns', itRuns, enRuns],
  ['commissionSettlements', itSettlements, enSettlements],
]

describe('agent commissions i18n parity (spec 0212)', () => {
  it.each(PAIRS)('%s has the same set of keys in it and en', (_name, it_, en) => {
    const itKeys = leafEntries(it_).map(([path]) => path).sort()
    const enKeys = leafEntries(en).map(([path]) => path).sort()
    expect(itKeys).toEqual(enKeys)
  })

  it.each(PAIRS)('%s has no empty value in either language', (_name, it_, en) => {
    const empty = [...leafEntries(it_), ...leafEntries(en)].filter(([, value]) => value.trim() === '')
    expect(empty).toEqual([])
  })

  it('translates the new enums in both languages with the same values', () => {
    for (const key of [
      'enasarco_mandate_type',
      'commission_accrual_type',
      'commission_recipient_role',
      'commission_recipient_kind',
      'commission_accrual_status',
      'commission_settlement_status',
    ] as const) {
      expect(Object.keys(itEnums[key]).sort()).toEqual(Object.keys(enEnums[key]).sort())
    }
    expect(Object.keys(enEnums.enasarco_mandate_type).sort()).toEqual(['mono', 'pluri'])
    expect(Object.keys(enEnums.commission_accrual_type).sort()).toEqual(['ACCRUAL', 'ADJUSTMENT', 'REVERSAL'])
    expect(Object.keys(enEnums.commission_settlement_status).sort()).toEqual(['APPROVED', 'CANCELLED', 'DOCUMENT_RECEIVED', 'DRAFT', 'ISSUED'])
    expect(Object.keys(enEnums.commission_accrual_status).sort()).toEqual(['draft', 'issued', 'open', 'settled_externally'])
  })

  it('labels the menu entries and the permission resources in both languages', () => {
    for (const key of ['accountingCommissions', 'agentFiscalProfiles', 'commissionAccruals', 'commissionSettlementRuns', 'commissionSettlements', 'enasarcoParameters'] as const) {
      expect(enNavigation[key]).toBeTruthy()
      expect(itNavigation[key]).toBeTruthy()
    }
    for (const key of ['agent-fiscal-profiles', 'commission-accruals', 'commission-settlements', 'enasarco-parameters'] as const) {
      expect(enPermissions.resources[key]).toBeTruthy()
      expect(itPermissions.resources[key]).toBeTruthy()
    }
  })
})
