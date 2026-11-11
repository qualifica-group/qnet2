import { afterEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { DocumentsTabCount } from '@/features/attachments/documents-tab-count'
import type { Attachment, AttachmentList } from '@/features/attachments/types'

const listAttachmentsMock = vi.fn<(resource: string, id: number, collection: string) => Promise<AttachmentList>>()

vi.mock('@/features/attachments/api', () => ({
  attachmentsQueryKey: (resource: string, id: number, collection: string) =>
    ['attachments', resource, id, collection] as const,
  listAttachments: (resource: string, id: number, collection: string) => listAttachmentsMock(resource, id, collection),
}))

function list(count: number): AttachmentList {
  return { documents: Array.from({ length: count }, (_, index) => ({ id: index + 1 }) as Attachment), legacyUrl: null }
}

function renderCount() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <DocumentsTabCount resource="task" id={7} />
    </QueryClientProvider>,
  )
}

afterEach(() => {
  listAttachmentsMock.mockReset()
})

describe('DocumentsTabCount', () => {
  it('counts the documents of the default collection, the list DocumentsSection shows', async () => {
    listAttachmentsMock.mockResolvedValue(list(4))
    renderCount()

    expect(await screen.findByText('4')).toBeInTheDocument()
    expect(listAttachmentsMock).toHaveBeenCalledWith('task', 7, 'documents')
  })

  it('shows nothing without documents', async () => {
    listAttachmentsMock.mockResolvedValue(list(0))
    const { container } = renderCount()

    await vi.waitFor(() => expect(listAttachmentsMock).toHaveBeenCalled())
    expect(container).toBeEmptyDOMElement()
  })
})
