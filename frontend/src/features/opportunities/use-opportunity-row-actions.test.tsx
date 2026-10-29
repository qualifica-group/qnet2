import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { useOpportunityRowActions } from '@/features/opportunities/use-opportunity-row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Delete failures of the Opportunita' row actions (spec 0327 AC-014): a 403 shows
 * the specific permission sentence composed by `lib/forbidden-message`, any other
 * failure keeps the generic delete error.
 */

const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: (...args: unknown[]) => toastErrorMock(...args) } }))

const deleteOpportunityMock = vi.fn()
vi.mock('@/features/opportunities/api', () => ({
  deleteOpportunity: (...args: unknown[]) => deleteOpportunityMock(...args),
  OPPORTUNITIES_DOMAIN: 'opportunities',
  OPPORTUNITY_ATTACHABLE_ALIAS: 'opportunity',
}))

vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: () => ({ openCreate: vi.fn(), openCreateWith: vi.fn(), openView: vi.fn(), sheet: null }),
}))

const ROW: TableRow = { id: 7, actions: ['delete'] }
const DELETE_ACTION = { key: 'delete', label: 'actions.delete', icon: 'trash', type: 'danger', confirm: true, count_field: null } as TableActionDefinition

function httpError(status: number, data?: unknown): AxiosError {
  return new AxiosError('failed', undefined, undefined, undefined, { status, data } as AxiosResponse)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  toastErrorMock.mockReset()
  deleteOpportunityMock.mockReset()
})

async function runDelete() {
  const { result } = renderHook(() => useOpportunityRowActions({ onMutated: vi.fn() }))
  await act(async () => {
    result.current.handleAction(DELETE_ACTION, ROW)
  })
}

describe('useOpportunityRowActions — delete failures', () => {
  it('names the missing permission on a 403', async () => {
    deleteOpportunityMock.mockRejectedValue(
      httpError(403, {
        success: false,
        message: 'Forbidden',
        authorization: { reason: 'missing_permission', permission: 'opportunities.delete' },
      }),
    )

    await runDelete()

    expect(toastErrorMock).toHaveBeenCalledWith('You do not have the «Delete» permission on the «Opportunities» module.')
  })

  it('keeps the generic delete error on a 500', async () => {
    deleteOpportunityMock.mockRejectedValue(httpError(500, { success: false, message: 'Server error' }))

    await runDelete()

    expect(toastErrorMock).toHaveBeenCalledWith(i18n.t('opportunities.form.deleteError'))
  })
})
