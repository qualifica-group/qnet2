import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { FormProvider, useForm } from 'react-hook-form'
import i18n from '@/i18n'
import { GENERAL_NOTES_CALLOUT_CLASS } from '@/components/record-form/layout'
import { NotesCalloutRow } from '@/components/record-form/notes-callout-row'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import type { ResourcePermissions } from '@/features/authorization/types'

const TITLE = 'Internal notes'
const PLACEHOLDER = 'Write a note…'

function permissions(editable: boolean): ResourcePermissions {
  return {
    resource: { view: true, create: true, update: editable, delete: false, export: false, import: false },
    fields: {
      internal_notes: { visible: true, hidden: false, editable, readonly: !editable, required: false, disabled: false },
    },
    actions: {},
  }
}

function inlineEdit(editingField: string | null = null): InlineEdit {
  return {
    editingField,
    start: vi.fn(),
    cancel: vi.fn(),
    save: vi.fn(),
    dismiss: vi.fn(),
    isSaving: false,
    error: null,
    confirmLabel: 'Save',
    cancelLabel: 'Cancel',
  }
}

function Harness({ notes, editable, inline }: { notes: string | null; editable: boolean; inline: InlineEdit }) {
  const form = useForm({ defaultValues: { internal_notes: notes } })
  return (
    <ResourcePermissionsProvider permissions={permissions(editable)}>
      <FormProvider {...form}>
        <NotesCalloutRow
          field="internal_notes"
          title={TITLE}
          notes={notes}
          placeholder={PLACEHOLDER}
          inline={inline}
          editor={<textarea aria-label={TITLE} />}
        />
      </FormProvider>
    </ResourcePermissionsProvider>
  )
}

/**
 * The request work panel's notes look on every record detail (user directive
 * 2026-10-07): the callout is the frame, the text, the placeholder and the
 * editor all live inside it.
 */
describe('NotesCalloutRow', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('shows the note inside the callout, line breaks preserved', () => {
    render(<Harness notes={'First line\nSecond line'} editable inline={inlineEdit()} />)

    const region = screen.getByRole('region', { name: TITLE })
    expect(region).toHaveClass(...GENERAL_NOTES_CALLOUT_CLASS.split(' '))
    expect(within(region).getByText(/First line/)).toHaveClass('whitespace-pre-wrap')
  })

  it('shows the placeholder in the callout when an editable note is empty', () => {
    render(<Harness notes={null} editable inline={inlineEdit()} />)

    expect(within(screen.getByRole('region', { name: TITLE })).getByText(PLACEHOLDER)).toBeInTheDocument()
  })

  it('starts the inline edit from the pencil', () => {
    const inline = inlineEdit()
    render(<Harness notes={null} editable inline={inline} />)

    fireEvent.click(screen.getByRole('button', { name: 'Edit Internal notes' }))

    expect(inline.start).toHaveBeenCalledWith('internal_notes')
  })

  it('renders the editor inside the callout while editing', () => {
    render(<Harness notes="A note" editable inline={inlineEdit('internal_notes')} />)

    const region = screen.getByRole('region', { name: TITLE })
    expect(within(region).getByRole('textbox', { name: TITLE })).toBeInTheDocument()
    expect(within(region).getByRole('button', { name: 'Save' })).toBeInTheDocument()
  })

  it('renders nothing for an empty read-only note', () => {
    render(<Harness notes={null} editable={false} inline={inlineEdit()} />)

    expect(screen.queryByRole('region', { name: TITLE })).not.toBeInTheDocument()
  })

  it('shows a read-only note without the edit affordance', () => {
    render(<Harness notes="A note" editable={false} inline={inlineEdit()} />)

    expect(within(screen.getByRole('region', { name: TITLE })).getByText('A note')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})
