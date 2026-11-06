import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { DocumentsDialog } from '@/features/attachments/documents-dialog'

/** Spec 0363: `readOnly` turns off upload and delete whatever the abilities; `title` renames the heading. */

vi.mock('@/features/auth/use-abilities', () => ({ useAbilities: () => ({ can: () => true }) }))

vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: ({ canUpload, canDelete }: { canUpload: boolean; canDelete: boolean }) => (
    <p>{`upload:${String(canUpload)} delete:${String(canDelete)}`}</p>
  ),
}))

describe('DocumentsDialog', () => {
  it('lets the actor upload and delete by default', () => {
    render(<DocumentsDialog resource="contract" id={3} onOpenChange={vi.fn()} />)

    expect(screen.getByText('upload:true delete:true')).toBeInTheDocument()
  })

  it('shows another record documents read-only, under the given title', () => {
    render(<DocumentsDialog resource="opportunity" id={3} onOpenChange={vi.fn()} readOnly title="Opportunity documents" />)

    expect(screen.getByText('upload:false delete:false')).toBeInTheDocument()
    expect(screen.getByRole('dialog', { name: 'Opportunity documents' })).toBeInTheDocument()
  })
})
