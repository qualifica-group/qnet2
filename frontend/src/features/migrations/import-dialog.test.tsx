import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ImportDialog } from '@/features/migrations/import-dialog'
import type { MigrationRun, MigrationRunCreated } from '@/features/migrations/types'

/**
 * Spec 0013 AC-021: confirming the import starts the queued run, the dialog
 * polls `GET .../runs/{id}` until a terminal status and shows the
 * created/skipped/failed summary and report warnings; a server error surfaces
 * a localized, detail-free message. The API module is mocked (same
 * convention as `features/imports/import-dialog.test.tsx`); assertions query
 * by accessible role/text.
 */

const startMigrationImportMock = vi.fn()
const fetchMigrationRunMock = vi.fn()

vi.mock('@/features/migrations/api', () => ({
  startMigrationImport: (...args: unknown[]) => startMigrationImportMock(...args),
  fetchMigrationRun: (...args: unknown[]) => fetchMigrationRunMock(...args),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  startMigrationImportMock.mockReset()
  fetchMigrationRunMock.mockReset()
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function createdRun(overrides: Partial<MigrationRunCreated> = {}): MigrationRunCreated {
  return {
    id: 1,
    source: 'roles',
    status: 'pending',
    total_rows: 0,
    created_rows: 0,
    skipped_rows: 0,
    failed_rows: 0,
    has_report: false,
    created_at: '2026-07-03T00:00:00Z',
    ...overrides,
  }
}

function polledRun(overrides: Partial<MigrationRun> = {}): MigrationRun {
  return {
    id: 1,
    source: 'roles',
    status: 'processing',
    total_rows: 2,
    created_rows: 0,
    skipped_rows: 0,
    failed_rows: 0,
    report: null,
    created_at: '2026-07-03T00:00:00Z',
    ...overrides,
  }
}

function renderDialog(onOpenChange = vi.fn()) {
  render(
    <ImportDialog source="roles" sourceLabel="Roles" open onOpenChange={onOpenChange} />,
    { wrapper: wrapper() },
  )
}

describe('ImportDialog (migrations)', () => {
  it('starts the run and polls until completed, showing the summary and report warnings', async () => {
    startMigrationImportMock.mockResolvedValue(createdRun())
    fetchMigrationRunMock.mockResolvedValue(
      polledRun({
        status: 'completed',
        created_rows: 1,
        skipped_rows: 1,
        report: [{ old_id: 7, level: 'warning', message: 'Parent role not found.' }],
      }),
    )

    renderDialog()

    fireEvent.click(screen.getByRole('button', { name: /^start import$/i }))

    await waitFor(() => expect(startMigrationImportMock).toHaveBeenCalledWith('roles'))
    await waitFor(() => expect(fetchMigrationRunMock).toHaveBeenCalledWith('roles', 1), {
      timeout: 3000,
    })

    expect(await screen.findByText('Completed', {}, { timeout: 3000 })).toBeInTheDocument()
    expect(screen.getByText('Parent role not found.')).toBeInTheDocument()
    expect(screen.getByText('Warnings and errors')).toBeInTheDocument()
  }, 10000)

  it('tells the user the run keeps going server-side while it is still active', async () => {
    startMigrationImportMock.mockResolvedValue(createdRun())
    fetchMigrationRunMock.mockResolvedValue(polledRun({ status: 'processing' }))

    renderDialog()
    fireEvent.click(screen.getByRole('button', { name: /^start import$/i }))

    expect(
      await screen.findByText(/keeps running on the server even if you close this dialog/i),
    ).toBeInTheDocument()
  })

  it('surfaces a localized error on a 403 start response, staying on the confirm step', async () => {
    startMigrationImportMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderDialog()
    fireEvent.click(screen.getByRole('button', { name: /^start import$/i }))

    expect(await screen.findByRole('alert')).toHaveTextContent(
      "You don't have permission to access migrations.",
    )
    expect(screen.getByRole('button', { name: /^start import$/i })).toBeInTheDocument()
    expect(fetchMigrationRunMock).not.toHaveBeenCalled()
  })

  it('surfaces a localized error on a 502 external failure', async () => {
    startMigrationImportMock.mockRejectedValue(
      new AxiosError('Bad Gateway', '502', undefined, undefined, {
        status: 502,
        data: { success: false, message: 'Bad Gateway' },
      } as never),
    )

    renderDialog()
    fireEvent.click(screen.getByRole('button', { name: /^start import$/i }))

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The external system is currently unavailable. Please try again.',
    )
  })
})
