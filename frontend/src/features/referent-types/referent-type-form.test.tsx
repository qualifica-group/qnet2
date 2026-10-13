import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ReferentTypeForm } from '@/features/referent-types/referent-type-form'
import type { ReferentTypeDetailWithPermissions } from '@/features/referent-types/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0237: the referent type form is create-only, a replica of the detail with its
 * rows closed (the in-place edits live in `referent-type-detail.test.tsx`). Covers the
 * metadata-driven behaviour (spec 0004: a hidden field is absent, a required
 * one is marked) and the custom fields wiring (spec 0021).
 */

const createReferentTypeMock = vi.fn()

vi.mock('@/features/referent-types/api', () => ({
  createReferentType: (...args: unknown[]) => createReferentTypeMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const FULL_ACCESS: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

const NOTES_FIELD: CustomFieldDescriptor = {
  key: 'custom.notes',
  type: 'text',
  label: 'Notes',
  group: null,
  mandatory: false,
  source: 'custom',
}

const EDITABLE = { visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false }

function permissions(fields: ResourcePermissions['fields'] = {}): ResourcePermissions {
  return { resource: FULL_ACCESS, fields: { 'custom.notes': EDITABLE, ...fields }, actions: {} }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function referentType(): ReferentTypeDetailWithPermissions {
  return { id: 9, name: 'Sponsor', created_at: '2026-01-01T00:00:00Z', permissions: permissions() }
}

/** Opens a closed row through its pencil. */
function openRow(label: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: i18n.t('referentTypes.form.save') })[0])

function renderForm(onSuccess = vi.fn()) {
  return render(<ReferentTypeForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createReferentTypeMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissions() })
})

describe('ReferentTypeForm — create (spec 0237)', () => {
  it('starts with every row closed; the name row opens on its own control', async () => {
    renderForm()

    await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) })
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()

    openRow(i18n.t('referentTypes.form.name'))
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
  })

  it('shows the error on the closed row, wires the aria triad and does not call the API when name is empty', async () => {
    renderForm()
    await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) })

    save()

    expect(await screen.findByText(i18n.t('referentTypes.form.nameRequired'))).toHaveAttribute('role', 'alert')

    openRow(i18n.t('referentTypes.form.name'))
    const nameInput = screen.getByLabelText(/^Name/)
    expect(nameInput).toHaveAttribute('aria-invalid', 'true')
    expect(nameInput.getAttribute('aria-describedby')).toBeTruthy()
    expect(createReferentTypeMock).not.toHaveBeenCalled()
  })

  it('submits the create payload on save', async () => {
    fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissions() })
    createReferentTypeMock.mockResolvedValue(referentType())
    const onSuccess = vi.fn()
    renderForm(onSuccess)
    await screen.findAllByRole('button', { name: i18n.t('referentTypes.form.save') })

    openRow(i18n.t('referentTypes.form.name'))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Sponsor' } })
    save()

    await waitFor(() => expect(createReferentTypeMock).toHaveBeenCalledTimes(1))
    expect(createReferentTypeMock).toHaveBeenCalledWith({ name: 'Sponsor' })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(referentType()))
  })

  it('includes the valued custom field in the create payload', async () => {
    createReferentTypeMock.mockResolvedValue(referentType())
    renderForm()
    await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) })

    openRow(i18n.t('referentTypes.form.name'))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Sponsor' } })
    openRow('Notes')
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'A note' } })
    save()

    await waitFor(() => expect(createReferentTypeMock).toHaveBeenCalledTimes(1))
    expect(createReferentTypeMock.mock.calls[0][0].custom_fields).toEqual({ notes: 'A note' })
  })

  it('hides a hidden field from the create-context metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: permissions({
        name: { visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false },
      }),
    })
    renderForm()

    await waitFor(() => expect(fetchResourceMetaMock).toHaveBeenCalled())
    await screen.findAllByRole('button', { name: i18n.t('referentTypes.form.save') })
    expect(screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t('referentTypes.form.name') }) })).not.toBeInTheDocument()
  })
})
