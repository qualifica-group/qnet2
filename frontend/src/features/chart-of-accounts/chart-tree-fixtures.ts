import type { ChartGroup, ChartTree, ChartTreeAbilities, SubAccount } from '@/features/chart-of-accounts/types'

const ALL = { create: true, update: true, delete: true, view_activity: true }
const NONE = { create: false, update: false, delete: false, view_activity: false }

export const ALL_ABILITIES: ChartTreeAbilities = {
  chart_accounts: { ...ALL, export: true, import: true },
  revenue_types: ALL,
  treasury_types: ALL,
  cost_types: ALL,
}
export const NO_ABILITIES: ChartTreeAbilities = {
  chart_accounts: { ...NONE, export: false, import: false },
  revenue_types: NONE,
  treasury_types: NONE,
  cost_types: NONE,
}

export function costSub(overrides: Partial<SubAccount> = {}): SubAccount {
  return {
    id: 1,
    source: 'cost_type',
    code: '61.03.001',
    name: 'Utenze',
    description: null,
    is_active: true,
    nature: 'indirect',
    color: 'blue',
    ...overrides,
  }
}

/** Spec 0360: a financial group with one account and one financial sub-account. */
export const FINANCIAL_GROUP: ChartGroup = {
  id: 3,
  code: '90',
  name: 'Movimenti finanziari',
  description: null,
  kind: 'financial',
  is_active: true,
  accounts: [
    {
      id: 30,
      code: '90.01',
      name: 'Giroconti',
      description: null,
      is_active: true,
      sub_accounts: [
        {
          id: 7,
          source: 'treasury_type',
          code: '90.01.001',
          name: 'Giroconto tra conti',
          description: null,
          is_active: true,
          nature: null,
          color: null,
        },
      ],
    },
  ],
}

/** Test fixture mirroring the frozen tree contract of spec 0348. */
export function buildTree(overrides: Partial<ChartTree> = {}): ChartTree {
  return {
    groups: [
      {
        id: 1,
        code: '61',
        name: 'Servizi',
        description: null,
        kind: 'cost',
        is_active: true,
        accounts: [
          {
            id: 10,
            code: '61.03',
            name: 'Utenze',
            description: 'Luce e gas',
            is_active: true,
            sub_accounts: [costSub()],
          },
          { id: 11, code: '61.04', name: 'Manutenzioni', description: null, is_active: false, sub_accounts: [] },
        ],
      },
      {
        id: 2,
        code: '70',
        name: 'Ricavi delle vendite',
        description: null,
        kind: 'revenue',
        is_active: true,
        accounts: [
          {
            id: 20,
            code: '70.01',
            name: 'Prestazioni di servizi',
            description: null,
            is_active: true,
            sub_accounts: [
              {
                id: 5,
                source: 'revenue_type',
                code: '70.01.001',
                name: 'Ricavi da consulenza',
                description: null,
                is_active: true,
                nature: null,
                color: null,
              },
            ],
          },
        ],
      },
    ],
    unplaced_cost_types: [],
    abilities: ALL_ABILITIES,
    ...overrides,
  }
}
