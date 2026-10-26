import { describe, expect, it } from 'vitest'
import { resolveQuickCreate } from '@/features/quick-create/quick-create-registry'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { PROJECTS_FOR_SELECT_RESOURCE } from '@/features/projects/for-select-api'
import { ROLES_FOR_SELECT_RESOURCE } from '@/features/roles/for-select-api'
import { STATES_FOR_SELECT_RESOURCE } from '@/features/geo/state-for-select-api'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import { TASK_STATUSES_FOR_SELECT_RESOURCE } from '@/features/task-statuses/for-select-api'
import { FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE } from '@/features/financial-accounts/for-select-api'
import { TASKS_FOR_SELECT_RESOURCE, WORK_ORDERS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'

/** Spec 0028 — resolveQuickCreate is the single derivation point for the "+". */
describe('resolveQuickCreate', () => {
  it('resolves a known async-module resource to its entry (AC-001 wiring)', () => {
    const entry = resolveQuickCreate(SOURCES_FOR_SELECT_RESOURCE)

    expect(entry).not.toBeNull()
    expect(entry?.permission).toBe('sources.create')
    expect(entry?.titleKey).toBe('sources.form.createTitle')
    expect(entry?.descriptionKey).toBe('sources.form.createSubtitle')
  })

  it('resolves every resource named in the contract, including the advanced ones', () => {
    expect(resolveQuickCreate(PROJECTS_FOR_SELECT_RESOURCE)?.permission).toBe('projects.create')
    expect(resolveQuickCreate(ROLES_FOR_SELECT_RESOURCE)?.permission).toBe('roles.create')
  })

  it('covers the lookup catalogs and records added after the first rollout (D3)', () => {
    expect(resolveQuickCreate(VAT_RATES_FOR_SELECT_RESOURCE)?.permission).toBe('vat-rates.create')
    expect(resolveQuickCreate(PAYMENT_METHODS_FOR_SELECT_RESOURCE)?.permission).toBe('payment-methods.create')
    expect(resolveQuickCreate(TASK_STATUSES_FOR_SELECT_RESOURCE)?.permission).toBe('task-statuses.create')
    expect(resolveQuickCreate(FINANCIAL_ACCOUNTS_FOR_SELECT_RESOURCE)?.titleKey).toBe('financialAccounts.form.newFinancialAccount')
    expect(resolveQuickCreate(TASKS_FOR_SELECT_RESOURCE)?.permission).toBe('tasks.create')
    expect(resolveQuickCreate(WORK_ORDERS_FOR_SELECT_RESOURCE)?.permission).toBe('work-orders.create')
  })

  it('returns null for the geo resource, out of scope by decision D3 (AC-011)', () => {
    expect(resolveQuickCreate(STATES_FOR_SELECT_RESOURCE)).toBeNull()
  })

  it('returns null for a resource unknown to the registry, without throwing (AC-011)', () => {
    expect(() => resolveQuickCreate('not-a-real-resource')).not.toThrow()
    expect(resolveQuickCreate('not-a-real-resource')).toBeNull()
  })
})
