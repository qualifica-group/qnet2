import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import { ImportStepReview } from '@/features/imports/wizard/import-step-review'
import type { ImportRunDetail, ImportRunRowCounts, ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Spec 0033 AC-023: the review step shows the run's counters (highlighting
 * whatever needs attention), hosts the SSRM review grid, and only advances to
 * the summary step on an explicit "continue". `ReviewGrid` (the real AG Grid
 * mount) is mocked here — its own datasource/edit wiring is covered by
 * `use-review-rows.test.ts` and `review-columns.test.tsx`; this test only
 * verifies the step composes it correctly and reacts to its `onRowUpdated`
 * callback.
 */

let capturedOnRowUpdated: ((row: ImportRunRowItem, counts: ImportRunRowCounts) => void) | null = null

vi.mock('@/features/imports/wizard/review-grid', () => ({
  ReviewGrid: (props: {
    domain: string
    run: ImportRunDetail
    onRowUpdated: (row: ImportRunRowItem, counts: ImportRunRowCounts) => void
  }) => {
    capturedOnRowUpdated = props.onRowUpdated
    return (
      <div role="region" aria-label="review-grid-stub">
        {props.domain} / run #{props.run.id}
      </div>
    )
  },
}))

function baseRun(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    id: 42,
    resource: 'leads',
    status: 'reviewing',
    original_filename: 'leads.csv',
    total_rows: 5,
    valid_rows: 3,
    warning_rows: 1,
    error_rows: 1,
    duplicate_rows: 0,
    imported_rows: null,
    modified_rows: 0,
    has_error_report: false,
    created_at: '2026-07-15T00:00:00Z',
    error_count: 1,
    detected_columns: [{ key: 'Email', name: 'Email', index: 0, duplicate: false }],
    column_mapping: { Email: 'email' },
    global_config: null,
    dedup_strategy: null,
    suggested_mapping: null,
    fields: [{ id: 'email', label: 'Email', required: true, group: 'contact', type: 'string' }],
    global_fields: [],
    dedup_modes: ['create_new'],
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  capturedOnRowUpdated = null
})

describe('ImportStepReview', () => {
  it('shows a loading state before any run exists', () => {
    render(<ImportStepReview domain="leads" run={null} onContinue={vi.fn()} />)
    expect(screen.getByRole('status')).toHaveTextContent('Loading the review…')
  })

  it('shows the staging state while rows are still being staged, announcing it runs in the background', () => {
    render(
      <MemoryRouter>
        <ImportStepReview domain="leads" run={baseRun({ status: 'staging' })} onContinue={vi.fn()} />
      </MemoryRouter>,
    )
    const status = screen.getByRole('status')
    expect(status).toHaveTextContent('Applying mapping…')
    expect(status).toHaveTextContent('keeps running in the background')
    // Staging sends no notification — only the final commit phase does.
    expect(status).not.toHaveTextContent('you will get a notification')
    expect(screen.getByRole('link', { name: 'Go to the import list' })).toHaveAttribute('href', '/imports')
  })

  it('renders the run counters and the review grid once reviewing', () => {
    render(<ImportStepReview domain="leads" run={baseRun()} onContinue={vi.fn()} />)

    expect(screen.getByText('Review')).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'review-grid-stub' })).toHaveTextContent('leads / run #42')
    // Total / valid / modified render as plain numbers, warning/error as badges.
    expect(screen.getByText('5')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.getAllByText('1')).toHaveLength(2) // warning_rows and error_rows badges
  })

  it('flags rows needing attention when there is at least one warning/error/duplicate', () => {
    render(<ImportStepReview domain="leads" run={baseRun()} onContinue={vi.fn()} />)
    expect(screen.getByRole('alert')).toHaveTextContent('Some rows need attention')
  })

  it('does not flag attention once every row is valid', () => {
    render(
      <ImportStepReview
        domain="leads"
        run={baseRun({ warning_rows: 0, error_rows: 0, duplicate_rows: 0, valid_rows: 5 })}
        onContinue={vi.fn()}
      />,
    )
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('updates the displayed counters from an inline edit bubbled by the grid', () => {
    render(<ImportStepReview domain="leads" run={baseRun()} onContinue={vi.fn()} />)

    expect(capturedOnRowUpdated).not.toBeNull()
    act(() => {
      capturedOnRowUpdated!(
        {
          id: 1,
          row_number: 1,
          status: 'valid',
          is_edited: true,
          duplicate_of_id: null,
          operator_id: null,
          operator: null,
          operational_site_id: null,
          operational_site: null,
          product_ids: null,
          products: [],
          values: {},
          messages: [],
        },
        { total: 5, valid_rows: 4, warning_rows: 0, error_rows: 1, duplicate_rows: 0, modified_rows: 1 },
      )
    })

    expect(screen.getByText('4')).toBeInTheDocument()
    expect(screen.queryByText('Some rows need attention')).toBeInTheDocument() // error_rows still 1
  })

  it('calls onContinue when the user is done reviewing', () => {
    const onContinue = vi.fn()
    render(<ImportStepReview domain="leads" run={baseRun()} onContinue={onContinue} />)

    fireEvent.click(screen.getByRole('button', { name: 'Continue to summary' }))
    expect(onContinue).toHaveBeenCalledTimes(1)
  })
})
