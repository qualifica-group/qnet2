import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ImportDialog } from '@/features/imports/import-dialog'
import type { ImportRun, ImportRunDetail } from '@/features/imports/types'

/**
 * Spec 0012 AC-019/AC-020/AC-021: the generic import dialog, driven entirely
 * through the frozen `/imports/{domain}` contract. The API module is mocked
 * (same convention as `company-form.test.tsx`); every assertion queries by
 * accessible role/text, never `data-testid`.
 */

const uploadImportMock = vi.fn()
const getImportRunMock = vi.fn()
const confirmImportMock = vi.fn()
const downloadImportTemplateMock = vi.fn()
const downloadImportErrorReportMock = vi.fn()

vi.mock('@/features/imports/api', () => ({
  uploadImport: (...args: unknown[]) => uploadImportMock(...args),
  getImportRun: (...args: unknown[]) => getImportRunMock(...args),
  confirmImport: (...args: unknown[]) => confirmImportMock(...args),
  downloadImportTemplate: (...args: unknown[]) => downloadImportTemplateMock(...args),
  downloadImportErrorReport: (...args: unknown[]) => downloadImportErrorReportMock(...args),
}))

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  downloadImportTemplateMock.mockResolvedValue(undefined)
  downloadImportErrorReportMock.mockResolvedValue(undefined)
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function baseRun(overrides: Partial<ImportRun> = {}): ImportRun {
  return {
    id: 1,
    resource: 'companies',
    status: 'validating',
    original_filename: 'companies.csv',
    total_rows: 0,
    valid_rows: 0,
    invalid_rows: 0,
    imported_rows: null,
    has_error_report: false,
    created_at: '2026-07-03T00:00:00Z',
    ...overrides,
  }
}

function renderDialog(onOpenChange = vi.fn()) {
  render(
    <ImportDialog domain="companies" resource="companies" open onOpenChange={onOpenChange} />,
    { wrapper: wrapper() },
  )
}

async function uploadCsv() {
  const file = new File(['denomination\nAcme'], 'companies.csv', { type: 'text/csv' })
  const input = screen.getByLabelText(/csv file/i)
  fireEvent.change(input, { target: { files: [file] } })
  fireEvent.click(screen.getByRole('button', { name: /^upload$/i }))
}

describe('ImportDialog', () => {
  it('downloads the template and, after upload, polls until awaiting_confirmation (AC-019)', async () => {
    uploadImportMock.mockResolvedValue(baseRun({ status: 'validating' }))
    const awaitingDetail: ImportRunDetail = {
      import_run: baseRun({ status: 'awaiting_confirmation', total_rows: 2, valid_rows: 1, invalid_rows: 1 }),
      preview: {
        columns: ['denomination'],
        valid_sample: [{ denomination: 'Acme' }],
        invalid_sample: [{ row_number: 3, values: { denomination: '' }, errors: ['Denomination is required'] }],
      },
    }
    getImportRunMock.mockResolvedValue(awaitingDetail)

    renderDialog()

    fireEvent.click(screen.getByRole('button', { name: /download template/i }))
    expect(downloadImportTemplateMock).toHaveBeenCalledWith('companies')

    await uploadCsv()

    await waitFor(() => expect(uploadImportMock).toHaveBeenCalledWith('companies', expect.any(File)))

    // Polling then surfaces the preview once the run reaches awaiting_confirmation.
    await waitFor(() => expect(getImportRunMock).toHaveBeenCalled(), { timeout: 3000 })
    expect(await screen.findByText('Awaiting confirmation', {}, { timeout: 3000 })).toBeInTheDocument()
  }, 10000)

  it('shows the preview and, on confirm, transitions to processing then completed (AC-020)', async () => {
    uploadImportMock.mockResolvedValue(baseRun({ status: 'validating' }))
    const awaitingDetail: ImportRunDetail = {
      import_run: baseRun({ status: 'awaiting_confirmation', total_rows: 2, valid_rows: 1, invalid_rows: 1, has_error_report: true }),
      preview: {
        columns: ['denomination'],
        valid_sample: [{ denomination: 'Acme' }],
        invalid_sample: [{ row_number: 3, values: { denomination: '' }, errors: ['Denomination is required'] }],
      },
    }
    getImportRunMock.mockResolvedValue(awaitingDetail)

    renderDialog()
    await uploadCsv()

    expect(await screen.findByText('Acme')).toBeInTheDocument()
    expect(screen.getByText('Denomination is required')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /download error report/i })).toBeInTheDocument()

    confirmImportMock.mockResolvedValue(baseRun({ status: 'processing', total_rows: 2, valid_rows: 1, invalid_rows: 1, imported_rows: null }))
    getImportRunMock.mockResolvedValue({
      import_run: baseRun({ status: 'completed', total_rows: 2, valid_rows: 1, invalid_rows: 1, imported_rows: 1 }),
      preview: null,
    } satisfies ImportRunDetail)

    fireEvent.click(screen.getByRole('button', { name: /^confirm$/i }))

    await waitFor(() => expect(confirmImportMock).toHaveBeenCalledWith('companies', 1))
    expect(await screen.findByText('Completed', {}, { timeout: 3000 })).toBeInTheDocument()
  }, 10000)

  it('surfaces a localized error on a 403 upload response without leaving an inconsistent state (AC-021)', async () => {
    uploadImportMock.mockRejectedValue(
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
    await uploadCsv()

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'You do not have the «Import» permission on the «Leads» module.',
    )
    // Stays on the upload step: the file field is still present, no validating badge leaked.
    expect(screen.getByLabelText(/csv file/i)).toBeInTheDocument()
    expect(screen.queryByText('Validating')).not.toBeInTheDocument()
  })

  it('surfaces a localized error on a 422 upload response (AC-021)', async () => {
    uploadImportMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Invalid file' },
      } as never),
    )

    renderDialog()
    await uploadCsv()

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The uploaded file could not be validated. Check the format and try again.',
    )
  })
})
