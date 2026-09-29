import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { EmailTemplateForm } from '@/features/email-templates/email-template-form'
import type { EmailTemplateWithPermissions } from '@/features/email-templates/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0175 AC-022: the form composes a select for the (single-valued)
 * `module`, a required `RichTextEditor` body (D-11) and the placeholder
 * picker, which inserts `{category.key}` into the subject caret and the body
 * editor selection via two independent instances.
 *
 * Labels are resolved through `i18n.t` rather than hardcoded English.
 * Tiptap itself is covered end to end by `rich-text-editor.test.tsx`; only
 * the wiring matters here (value/onChange/disabled/ref), same stand-in as
 * `task-templates/task-template-form-body.test.tsx`.
 */

const createEmailTemplateMock = vi.fn()
const updateEmailTemplateMock = vi.fn()

vi.mock('@/features/email-templates/api', () => ({
  createEmailTemplate: (...args: unknown[]) => createEmailTemplateMock(...args),
  updateEmailTemplate: (...args: unknown[]) => updateEmailTemplateMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const VARIABLE_CATEGORIES = [
  {
    key: 'work_order',
    label: 'Commessa',
    variables: [{ variable: '{work_order.code}', label: 'Codice', type: 'string' as const, example: 'CMS-1' }],
  },
]

vi.mock('@/features/email-templates/variables-api', () => ({
  useEmailTemplateVariables: () => ({ data: VARIABLE_CATEGORIES, isLoading: false, isError: false }),
}))

interface RichTextEditorMockProps {
  id?: string
  value: string | null
  onChange: (html: string | null) => void
  disabled?: boolean
}

vi.mock('@/components/rich-text/rich-text-editor', () => ({
  RichTextEditor: forwardRef<{ insertText: (text: string) => void; focus: () => void }, RichTextEditorMockProps>(
    function RichTextEditorMock({ id, value, onChange, disabled }, ref) {
      useImperativeHandle(ref, () => ({
        insertText: (text: string) => onChange(`${value ?? ''}${text}`),
        focus: () => {},
      }))
      return (
        <textarea
          id={id}
          disabled={disabled}
          value={value ?? ''}
          onChange={(event) => onChange(event.target.value === '' ? null : event.target.value)}
        />
      )
    },
  ),
}))

const EDITABLE: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

const ALL_EDITABLE: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {
    name: EDITABLE,
    module: EDITABLE,
    subject: EDITABLE,
    body: EDITABLE,
    description: EDITABLE,
    is_active: EDITABLE,
  },
  actions: {},
}

let metaPermissions: ResourcePermissions = ALL_EDITABLE

vi.mock('@/features/email-templates/use-email-template-form-meta', () => ({
  useEmailTemplateFormMeta: () => ({ status: 'ready', permissions: metaPermissions }),
}))

/** A QueryClient per test, never per render: a shared cache makes suites flaky. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** Anchors a translated label so a required field's trailing marker does not break the match. */
function labelFor(key: string): RegExp {
  return new RegExp(`^${i18n.t(key).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`)
}

function emailTemplate(
  overrides: Partial<EmailTemplateWithPermissions> = {},
): EmailTemplateWithPermissions {
  return {
    id: 9,
    name: 'Follow-up',
    module: 'work_orders',
    subject: 'Update on {work_order.code}',
    body: '<p>Hello</p>',
    description: 'Sent after a site visit',
    is_active: true,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: ALL_EDITABLE,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createEmailTemplateMock.mockReset()
  updateEmailTemplateMock.mockReset()
  metaPermissions = ALL_EDITABLE
})

describe('EmailTemplateForm — create (spec 0175)', () => {
  it('renders the name, module, subject and body controls', () => {
    render(<EmailTemplateForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(screen.getByLabelText(labelFor('emailTemplates.form.name'))).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: labelFor('emailTemplates.form.module') })).toBeInTheDocument()
    expect(screen.getByLabelText(labelFor('emailTemplates.form.subject'))).toBeInTheDocument()
    expect(
      screen.getAllByRole('button', { name: i18n.t('emailTemplates.form.variablesPicker') }),
    ).toHaveLength(2)
  })

  it('shows an accessible inline error and does not call the API when name is empty', async () => {
    render(<EmailTemplateForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(screen.getByRole('button', { name: i18n.t('emailTemplates.form.save') }))

    const nameInput = await screen.findByLabelText(labelFor('emailTemplates.form.name'))
    await waitFor(() => expect(nameInput).toHaveAttribute('aria-invalid', 'true'))
    const describedBy = nameInput.getAttribute('aria-describedby')
    expect(describedBy).toBeTruthy()
    const message = screen.getByText(i18n.t('emailTemplates.form.nameRequired'))
    expect(message).toHaveAttribute('role', 'alert')
    expect(describedBy).toContain(message.id)
    expect(createEmailTemplateMock).not.toHaveBeenCalled()
  })

  it('blocks the submit while the body is empty (D-11: required rich text)', async () => {
    render(<EmailTemplateForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(labelFor('emailTemplates.form.name')), {
      target: { value: 'Follow-up' },
    })
    fireEvent.change(screen.getByLabelText(labelFor('emailTemplates.form.subject')), {
      target: { value: 'Update' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('emailTemplates.form.save') }))

    await waitFor(() =>
      expect(screen.getByText(i18n.t('emailTemplates.form.bodyRequired'))).toBeInTheDocument(),
    )
    expect(createEmailTemplateMock).not.toHaveBeenCalled()
  })

  it('the subject picker inserts the token at the input caret', async () => {
    render(<EmailTemplateForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    const subjectInput = screen.getByLabelText(labelFor('emailTemplates.form.subject')) as HTMLInputElement
    fireEvent.change(subjectInput, { target: { value: 'Hello world' } })
    subjectInput.setSelectionRange(5, 5)
    fireEvent.select(subjectInput)

    const [subjectPickerTrigger] = screen.getAllByRole('button', {
      name: i18n.t('emailTemplates.form.variablesPicker'),
    })
    fireEvent.click(subjectPickerTrigger)
    fireEvent.click(await screen.findByRole('button', { name: /Codice/ }))

    expect(subjectInput).toHaveValue('Hello{work_order.code} world')
  })

  it('the body picker calls the editor ref `insertText`', async () => {
    render(<EmailTemplateForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    const [, bodyPickerTrigger] = screen.getAllByRole('button', {
      name: i18n.t('emailTemplates.form.variablesPicker'),
    })
    fireEvent.click(bodyPickerTrigger)
    fireEvent.click(await screen.findByRole('button', { name: /Codice/ }))

    expect(screen.getByLabelText(labelFor('emailTemplates.form.body'))).toHaveValue('{work_order.code}')
  })

  it('submits the create payload with the picked module and body', async () => {
    createEmailTemplateMock.mockResolvedValue(emailTemplate())
    const onSuccess = vi.fn()

    render(<EmailTemplateForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.change(screen.getByLabelText(labelFor('emailTemplates.form.name')), {
      target: { value: 'Follow-up' },
    })
    fireEvent.change(screen.getByLabelText(labelFor('emailTemplates.form.subject')), {
      target: { value: 'Update' },
    })
    fireEvent.change(screen.getByLabelText(labelFor('emailTemplates.form.body')), {
      target: { value: '<p>Hello</p>' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('emailTemplates.form.save') }))

    await waitFor(() => expect(createEmailTemplateMock).toHaveBeenCalledTimes(1))
    expect(createEmailTemplateMock).toHaveBeenCalledWith({
      name: 'Follow-up',
      module: 'work_orders',
      subject: 'Update',
      body: '<p>Hello</p>',
      description: null,
      is_active: true,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(emailTemplate()))
  })
})

describe('EmailTemplateForm — edit (spec 0175)', () => {
  it('hydrates every field from the loaded detail', () => {
    render(
      <EmailTemplateForm
        mode={{ type: 'edit', emailTemplate: emailTemplate() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(labelFor('emailTemplates.form.name'))).toHaveValue('Follow-up')
    expect(screen.getByLabelText(labelFor('emailTemplates.form.subject'))).toHaveValue(
      'Update on {work_order.code}',
    )
    expect(screen.getByLabelText(labelFor('emailTemplates.form.body'))).toHaveValue('<p>Hello</p>')
  })

  it('submits only the changed field on a partial update, never module', async () => {
    updateEmailTemplateMock.mockResolvedValue(emailTemplate({ subject: 'Renamed' }))

    render(
      <EmailTemplateForm
        mode={{ type: 'edit', emailTemplate: emailTemplate() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(labelFor('emailTemplates.form.subject')), {
      target: { value: 'Renamed' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('emailTemplates.form.save') }))

    await waitFor(() => expect(updateEmailTemplateMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateEmailTemplateMock.mock.calls[0]
    expect(id).toBe(9)
    expect(payload).toEqual({ subject: 'Renamed' })
    expect(payload).not.toHaveProperty('module')
  })

  it('maps a server 422 onto the matching form field', async () => {
    updateEmailTemplateMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: { name: ['Name already taken for this module.'] },
        },
      } as never),
    )

    render(
      <EmailTemplateForm
        mode={{ type: 'edit', emailTemplate: emailTemplate() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(labelFor('emailTemplates.form.name')), {
      target: { value: 'Duplicate' },
    })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('emailTemplates.form.save') }))

    await waitFor(() =>
      expect(screen.getByText('Name already taken for this module.')).toBeInTheDocument(),
    )
  })
})
