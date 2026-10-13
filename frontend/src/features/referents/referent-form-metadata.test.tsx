import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ReferentForm } from '@/features/referents/referent-form'
import type { ResourceMeta } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import {
  EDITABLE,
  HIDDEN,
  pencilOf,
  permissionsFor,
  referentWrapper,
} from '@/features/referents/referent-test-fixtures'

/**
 * Acceptance criteria AC-020/AC-021 (spec 0016) on the create form: the
 * create-context metadata drives which rows exist and what their editors
 * mark. REQUIREMENT CHANGED (spec 0216): the rows start closed, so a required
 * marker is asserted inside the opened editor; the edit-mode cases (readonly
 * field, 422 mapping, cache seed) moved to `referent-detail-inline-edit.test.tsx`
 * with the in-place detail.
 */

vi.mock('@/features/referents/api', () => ({
  createReferent: vi.fn(),
  updateReferent: vi.fn(),
  referentDetailQueryKey: (id: number | null) => ['referents', 'detail', id] as const,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const enums: Record<string, EnumOption[]> = {
  personal_data_type: [
    { value: 'individual', label: 'Individual', color: null, icon: null, is_default: true, hidden_on_form: false },
  ],
  contact_type: [],
  referent_contact_scope: [
    { value: 'internal', label: 'Internal', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'external', label: 'External', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
}

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums } }),
  useEnumOptions: (key: string) => enums[key] ?? [],
}))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: () => <div />,
}))

function renderForm() {
  const { wrapper } = referentWrapper()
  render(<ReferentForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchResourceMetaMock.mockReset()
})

describe('ReferentForm — metadata-driven authorization (spec 0004)', () => {
  it('hides a hidden field and marks a required field from create-context metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: permissionsFor({
        referent_type_id: HIDDEN,
        contact_scope: { ...EDITABLE, required: true },
        notes: EDITABLE,
      }),
    })
    renderForm()

    await waitFor(() => expect(pencilOf('Notes')).toBeInTheDocument())
    expect(screen.queryByRole('button', { name: 'Edit Referent type' })).not.toBeInTheDocument()

    fireEvent.click(pencilOf('Contact scope'))
    expect(screen.getByText('Contact scope', { selector: 'label' }).textContent).toContain('*')
  })

  it('hides the linked-user row independently by its own field permission (AC-005, spec 0090)', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: permissionsFor({ user_id: HIDDEN }),
    })
    renderForm()

    await waitFor(() => expect(pencilOf('Referent type')).toBeInTheDocument())
    expect(screen.queryByRole('button', { name: 'Edit Linked user' })).not.toBeInTheDocument()
  })
})
