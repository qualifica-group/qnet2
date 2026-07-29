import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { ImportStepUpload } from '@/features/imports/wizard/import-step-upload'
import '@/features/imports/wizard/i18n'
import type { ImportRunDetail } from '@/features/imports/wizard/types'

/**
 * Spec 0033 AC-020: submitting a file calls `onUpload`; once the run's
 * analysis completes, the step shows columns/rows/duplicate-columns counts
 * before the user explicitly continues.
 */

function baseRun(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    id: 1,
    resource: 'leads',
    status: 'configuring',
    original_filename: 'leads.csv',
    total_rows: 42,
    valid_rows: 0,
    warning_rows: 0,
    error_rows: 0,
    duplicate_rows: 0,
    imported_rows: null,
    modified_rows: 0,
    has_error_report: false,
    created_at: '2026-07-15T00:00:00Z',
    error_count: 0,
    detected_columns: [
      { key: 'Full name', name: 'Full name', index: 0, duplicate: false },
      { key: 'Email', name: 'Email', index: 1, duplicate: false },
      { key: 'Email#2', name: 'Email', index: 2, duplicate: true },
    ],
    column_mapping: null,
    global_config: null,
    dedup_strategy: null,
    suggested_mapping: { 'Full name': 'full_name' },
    fields: [],
    global_fields: [],
    dedup_modes: ['create_new'],
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ImportStepUpload', () => {
  it('submits the selected file via onUpload', async () => {
    const onUpload = vi.fn()
    render(
      <ImportStepUpload run={null} isUploading={false} uploadError={null} onUpload={onUpload} onContinue={vi.fn()} />,
    )

    const file = new File(['a,b\n1,2'], 'leads.csv', { type: 'text/csv' })
    fireEvent.change(screen.getByLabelText(/File \(\.csv, \.xlsx\)/), { target: { files: [file] } })
    fireEvent.click(screen.getByRole('button', { name: 'Analyze file' }))

    await waitFor(() => expect(onUpload).toHaveBeenCalledWith(file))
  })

  it('shows a validation error and does not upload without a file', async () => {
    const onUpload = vi.fn()
    render(
      <ImportStepUpload run={null} isUploading={false} uploadError={null} onUpload={onUpload} onContinue={vi.fn()} />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Analyze file' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Select a file to upload.')
    expect(onUpload).not.toHaveBeenCalled()
  })

  it('shows the analyzing state while the run is still analyzing, announcing it runs in the background', () => {
    render(
      <MemoryRouter>
        <ImportStepUpload
          run={baseRun({ status: 'analyzing' })}
          isUploading={false}
          uploadError={null}
          onUpload={vi.fn()}
          onContinue={vi.fn()}
        />
      </MemoryRouter>,
    )

    const status = screen.getByRole('status')
    expect(status).toHaveTextContent('Analyzing the file…')
    expect(status).toHaveTextContent('keeps running in the background')
    expect(screen.getByRole('link', { name: 'Go to the import list' })).toHaveAttribute('href', '/imports')
  })

  it('shows the analysis summary and continues on demand once configuring', () => {
    const onContinue = vi.fn()
    render(
      <ImportStepUpload run={baseRun()} isUploading={false} uploadError={null} onUpload={vi.fn()} onContinue={onContinue} />,
    )

    expect(screen.getByText('3')).toBeInTheDocument() // columns detected
    expect(screen.getByText('42')).toBeInTheDocument() // rows detected
    expect(screen.getByText('1')).toBeInTheDocument() // duplicate columns

    fireEvent.click(screen.getByRole('button', { name: 'Continue to configuration' }))
    expect(onContinue).toHaveBeenCalledTimes(1)
  })
})
