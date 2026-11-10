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
    updated_since: null,
    update_existing: false,
    updated_rows: 0,
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
    updated_since: null,
    update_existing: false,
    updated_rows: 0,
    created_at: '2026-07-03T00:00:00Z',
    ...overrides,
  }
}

function renderDialog(onOpenChange = vi.fn(), supportsUpdateExisting = false) {
  render(
    <ImportDialog
      source="roles"
      sourceLabel="Roles"
      supportsUpdateExisting={supportsUpdateExisting}
      open
      onOpenChange={onOpenChange}
    />,
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

    await waitFor(() => expect(startMigrationImportMock).toHaveBeenCalledWith('roles', { updatedSince: null, updateExisting: false }))
    await waitFor(() => expect(fetchMigrationRunMock).toHaveBeenCalledWith('roles', 1), {
      timeout: 3000,
    })

    expect(await screen.findByText('Completed', {}, { timeout: 3000 })).toBeInTheDocument()
    expect(screen.getByText('Parent role not found.')).toBeInTheDocument()
    expect(screen.getByText('Warnings and errors')).toBeInTheDocument()
  }, 10000)

  it('AC-011: any source shows the date field and starts with the chosen day', async () => {
    startMigrationImportMock.mockResolvedValue(createdRun({ updated_since: '2026-10-01' }))
    fetchMigrationRunMock.mockResolvedValue(polledRun({ updated_since: '2026-10-01' }))

    renderDialog()

    fireEvent.change(screen.getByLabelText('Import only records created or modified since'), {
      target: { value: '2026-10-01' },
    })
    fireEvent.click(screen.getByRole('button', { name: /^start import$/i }))

    await waitFor(() =>
      expect(startMigrationImportMock).toHaveBeenCalledWith('roles', {
        updatedSince: '2026-10-01',
        updateExisting: false,
      }),
    )
    // AC-014: the progress view shows the start day.
    expect(await screen.findByText(/^Since /)).toBeInTheDocument()
  })

  it('AC-011: an empty date starts a full import', async () => {
    startMigrationImportMock.mockResolvedValue(createdRun())
    fetchMigrationRunMock.mockResolvedValue(polledRun())

    renderDialog()
    fireEvent.click(screen.getByRole('button', { name: /^start import$/i }))

    await waitFor(() =>
      expect(startMigrationImportMock).toHaveBeenCalledWith('roles', {
        updatedSince: null,
        updateExisting: false,
      }),
    )
  })

  it('AC-024: the realign checkbox is hidden when the source does not support it', () => {
    renderDialog(vi.fn(), false)

    expect(
      screen.queryByRole('checkbox', { name: 'Also update records already imported' }),
    ).not.toBeInTheDocument()
  })

  it('AC-024: the realign checkbox is off by default and starts with update_existing', async () => {
    startMigrationImportMock.mockResolvedValue(createdRun({ update_existing: true }))
    fetchMigrationRunMock.mockResolvedValue(
      polledRun({ status: 'completed', update_existing: true, updated_rows: 3 }),
    )

    renderDialog(vi.fn(), true)

    const checkbox = screen.getByRole('checkbox', { name: 'Also update records already imported' })
    expect(checkbox).not.toBeChecked()
    expect(checkbox).toHaveAccessibleDescription(/nobody has worked on in QNet/)

    fireEvent.click(checkbox)
    fireEvent.click(screen.getByRole('button', { name: /^start import$/i }))

    await waitFor(() =>
      expect(startMigrationImportMock).toHaveBeenCalledWith('roles', {
        updatedSince: null,
        updateExisting: true,
      }),
    )
    // AC-026: with update_existing the Updated counter and the mode label show.
    expect(await screen.findByText('Updated', {}, { timeout: 3000 })).toBeInTheDocument()
    expect(screen.getByText('Update existing records')).toBeInTheDocument()
  })

  it('AC-026: without update_existing the Updated counter is not shown', async () => {
    startMigrationImportMock.mockResolvedValue(createdRun())
    fetchMigrationRunMock.mockResolvedValue(polledRun({ status: 'completed' }))

    renderDialog(vi.fn(), true)
    fireEvent.click(screen.getByRole('button', { name: /^start import$/i }))

    expect(await screen.findByText('Completed', {}, { timeout: 3000 })).toBeInTheDocument()
    expect(screen.queryByText('Updated')).not.toBeInTheDocument()
    expect(screen.queryByText('Update existing records')).not.toBeInTheDocument()
  })

  it('wires a server start error to the date field', async () => {
    startMigrationImportMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'x', errors: { updated_since: ['bad'] } },
      } as never),
    )

    renderDialog()
    fireEvent.click(screen.getByRole('button', { name: /^start import$/i }))

    const field = screen.getByLabelText('Import only records created or modified since')
    expect(await screen.findByRole('alert')).toHaveTextContent(/invalid parameters/i)
    expect(field).toHaveAttribute('aria-invalid', 'true')
  })

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
        data: {
          success: false,
          message: 'Forbidden',
          authorization: { reason: 'missing_permission', permission: 'leads.import' },
        },
      } as never),
    )

    renderDialog()
    fireEvent.click(screen.getByRole('button', { name: /^start import$/i }))

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'You do not have the «Import» permission on the «Leads» module.',
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
