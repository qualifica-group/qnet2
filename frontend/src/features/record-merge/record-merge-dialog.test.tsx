import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RecordMergeDialog } from '@/features/record-merge/record-merge-dialog'
import { PREVIEW_FIXTURE } from '@/features/record-merge/merge-fixtures'
import type { MergePayload, MergePreview } from '@/features/record-merge/types'

const previewMock = vi.fn<() => Promise<MergePreview>>()
const mergeMock = vi.fn<(module: string, payload: MergePayload) => Promise<void>>()
vi.mock('@/features/record-merge/api', () => ({
  previewMerge: () => previewMock(),
  mergeRecords: (module: string, payload: MergePayload) => mergeMock(module, payload),
}))

const toastSuccess = vi.fn()
const toastError = vi.fn()
vi.mock('sonner', () => ({
  toast: { success: (...args: unknown[]) => toastSuccess(...args), error: (...args: unknown[]) => toastError(...args) },
}))
vi.mock('@/features/stats/use-invalidate-module-stats', () => ({ useInvalidateModuleStats: () => () => {} }))

const onClose = vi.fn()
const onMerged = vi.fn()

function renderDialog() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <RecordMergeDialog module="registries" ids={[1, 2]} onClose={onClose} onMerged={onMerged} />
    </QueryClientProvider>,
  )
}

async function confirmMerge() {
  fireEvent.click(screen.getByRole('button', { name: 'Merge' }))
  const confirm = await screen.findByRole('alertdialog')
  fireEvent.click(within(confirm).getByRole('button', { name: 'Merge' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  previewMock.mockReset().mockResolvedValue(PREVIEW_FIXTURE)
  mergeMock.mockReset().mockResolvedValue(undefined)
  toastSuccess.mockReset()
  toastError.mockReset()
  onClose.mockReset()
  onMerged.mockReset()
})

describe('RecordMergeDialog (spec 0394 AC-010)', () => {
  it('shows the records, the differing fields, the duplicates collections and the links', async () => {
    renderDialog()

    expect(await screen.findByRole('radio', { name: /Keep this one\s*Acme Srl/ })).toBeChecked()
    expect(screen.getByRole('radio', { name: /Keep this one\s*Acme Beta/ })).not.toBeChecked()

    expect(screen.getByRole('radiogroup', { name: 'Company name' })).toBeInTheDocument()
    expect(screen.queryByRole('radiogroup', { name: 'VAT number' })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('switch', { name: 'Also show identical fields' }))
    expect(screen.getByRole('radiogroup', { name: 'VAT number' })).toBeInTheDocument()

    expect(screen.getByRole('checkbox', { name: /sales@acme\.it/ })).toBeChecked()
    expect(screen.getByText('Already present')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Opportunities\s*3/ })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('radio', { name: /Keep this one\s*Acme Beta/ }))
    expect(screen.getByRole('button', { name: /Opportunities\s*1/ })).toBeInTheDocument()
  })

  it('defaults an empty survivor field to the duplicate value and recomputes on survivor change', async () => {
    renderDialog()
    await screen.findByRole('radiogroup', { name: 'SDI recipient code' })

    const sdi = within(screen.getByRole('radiogroup', { name: 'SDI recipient code' }))
    expect(sdi.getAllByRole('radio')[1]).toBeChecked()

    fireEvent.click(screen.getByRole('radio', { name: /Keep this one\s*Acme Beta/ }))

    expect(screen.queryByRole('checkbox', { name: /sales@acme\.it/ })).not.toBeInTheDocument()
    // record 1's contact now matches one already on the chosen survivor: flagged, not selectable
    expect(screen.queryByRole('checkbox', { name: /own@acme\.it/ })).not.toBeInTheDocument()
    expect(screen.getByText('Already present')).toBeInTheDocument()
  })

  it('confirms with the contract payload: choices, combine and unchecked items', async () => {
    renderDialog()
    await screen.findByRole('radiogroup', { name: 'General notes' })

    fireEvent.click(within(screen.getByRole('radiogroup', { name: 'General notes' })).getByRole('radio', { name: 'Combine texts' }))
    fireEvent.click(screen.getByRole('checkbox', { name: /sales@acme\.it/ }))
    await confirmMerge()

    await waitFor(() =>
      expect(mergeMock).toHaveBeenCalledWith('registries', {
        survivor_id: 1,
        merged_ids: [2],
        fields: { sdi_code: 2, general_notes: 'combine' },
        discard: { contacts: [11] },
      }),
    )
    await waitFor(() => expect(onMerged).toHaveBeenCalled())
    expect(toastSuccess).toHaveBeenCalledWith('Records merged.')
    expect(onClose).toHaveBeenCalled()
  })

  it('names the deleted records and the survivor in the confirmation', async () => {
    renderDialog()
    await screen.findByRole('radiogroup', { name: 'Company name' })
    fireEvent.click(screen.getByRole('button', { name: 'Merge' }))

    const confirm = await screen.findByRole('alertdialog')
    expect(within(confirm).getByText(/Acme Beta will be deleted and their data will move to Acme Srl/)).toBeInTheDocument()
  })

  it('toasts an error and keeps the dialog open when the merge fails', async () => {
    mergeMock.mockRejectedValue(new Error('boom'))
    renderDialog()
    await screen.findByRole('radiogroup', { name: 'Company name' })

    await confirmMerge()

    await waitFor(() => expect(toastError).toHaveBeenCalledWith('Unable to merge the records. Try again.'))
    expect(onMerged).not.toHaveBeenCalled()
    expect(onClose).not.toHaveBeenCalled()
  })

  it('offers a retry when the preview fails to load', async () => {
    previewMock.mockRejectedValueOnce(new Error('down'))
    renderDialog()

    fireEvent.click(await screen.findByRole('button', { name: 'Retry' }))

    expect(await screen.findByRole('radiogroup', { name: 'Company name' })).toBeInTheDocument()
  })
})
