import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { CustomFieldDefinitionFormBody } from '@/features/custom-fields/custom-field-definition-form-body'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'

/** Spec 0390 AC-010: the row event notification inside the `table` columns editor. */

const createDefinitionMock = vi.fn()

vi.mock('@/features/custom-fields/api', () => ({
  createCustomFieldDefinition: (...args: unknown[]) => createDefinitionMock(...args),
  updateCustomFieldDefinition: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/custom-fields/use-custom-field-entities', () => ({
  useCustomFieldEntities: () => ({
    data: [{ entity_type: 'companies', label: 'customFields.entities.companies' }],
    isLoading: false,
    isError: false,
  }),
  useCustomFieldRelationTargets: () => ({
    data: [{ entity_type: 'companies', label: 'customFields.entities.companies' }],
    isLoading: false,
    isError: false,
  }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createDefinitionMock.mockReset()
  // The live preview renders the table control, which reads the viewport.
  window.matchMedia = vi.fn().mockImplementation((query: string) => ({
    matches: false,
    media: query,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  })) as unknown as typeof window.matchMedia
})

function pick(combobox: HTMLElement, option: string) {
  fireEvent.click(combobox)
  fireEvent.click(screen.getByRole('option', { name: option }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

/** The create form's rows are closed until their pencil is clicked (spec 0231). */
function openRow(field: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field }) }))
}

function renderTableForm() {
  render(
    <ResourcePermissionsProvider permissions={null}>
      <CustomFieldDefinitionFormBody onSuccess={vi.fn()} onCancel={vi.fn()} />
    </ResourcePermissionsProvider>,
    { wrapper: wrapper() },
  )
  openRow('Module')
  pick(screen.getByRole('combobox', { name: 'Module' }), 'Companies')
  openRow('Key')
  fireEvent.change(screen.getByLabelText('Key'), { target: { value: 'inspections' } })
  openRow('Label')
  fireEvent.change(screen.getByLabelText('Label'), { target: { value: 'Inspections' } })
  openRow('Type')
  pick(screen.getByRole('combobox', { name: 'Type' }), 'Table')
}

function addColumn(label: string) {
  fireEvent.click(screen.getByRole('button', { name: 'Add column' }))
  const labels = screen.getAllByLabelText('Column label')
  fireEvent.change(labels[labels.length - 1], { target: { value: label } })
}

describe('DefinitionTableNotifySettings in the custom field form (AC-010)', () => {
  it('submits the chosen trigger and recipients as config.notify', { timeout: 20_000 }, async () => {
    createDefinitionMock.mockResolvedValue({})
    renderTableForm()
    addColumn('Inspection date')
    addColumn('Alert')
    pick(screen.getAllByRole('combobox', { name: 'Column type' })[1], 'Yes/No')

    fireEvent.click(screen.getByRole('checkbox', { name: 'Send a notification when a row is added or switched on' }))
    pick(screen.getByRole('combobox', { name: 'When' }), '"Alert" is switched on in a row')
    const emails = screen.getByLabelText('Other email addresses')
    fireEvent.change(emails, { target: { value: 'audit@example.com' } })
    fireEvent.keyDown(emails, { key: 'Enter' })

    save()

    await waitFor(() => expect(createDefinitionMock).toHaveBeenCalledTimes(1))
    expect(createDefinitionMock.mock.calls[0][0].config.notify).toEqual({
      trigger: 'alert',
      email_template_id: null,
      record_team: true,
      user_ids: [],
      role_ids: [],
      emails: ['audit@example.com'],
    })
  })

  it('blocks the submit without recipients and falls back to "a row is added" when the trigger column goes', async () => {
    renderTableForm()
    addColumn('Alert')
    pick(screen.getByRole('combobox', { name: 'Column type' }), 'Yes/No')
    fireEvent.click(screen.getByRole('checkbox', { name: 'Send a notification when a row is added or switched on' }))
    pick(screen.getByRole('combobox', { name: 'When' }), '"Alert" is switched on in a row')

    pick(screen.getByRole('combobox', { name: 'Column type' }), 'Text')
    expect(screen.getByRole('combobox', { name: 'When' })).toHaveTextContent('A row is added')

    fireEvent.click(screen.getByRole('checkbox', { name: 'People assigned to the record' }))
    save()

    expect(await screen.findByText('Pick at least one recipient.')).toBeInTheDocument()
    expect(createDefinitionMock).not.toHaveBeenCalled()
  })
})
