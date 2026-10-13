import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, type ReactElement, type ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { EmailTemplateDetailView } from '@/features/email-templates/email-template-detail'
import type { EmailTemplateWithPermissions } from '@/features/email-templates/types'

/**
 * Spec 0175: the detail shows the name (hero title), subject as the header
 * subtitle, module label, description, active flag, the sanitized body
 * rendered through `RichTextContent` (never `dangerouslySetInnerHTML`) and
 * both timestamps. Every row but the module (immutable after create) edits
 * in place (wave 3 of the "no edit pages" plan), each save a PATCH of that
 * field alone; the body is one full-width row.
 */

const updateEmailTemplateMock = vi.fn()

vi.mock('@/features/email-templates/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/email-templates/api')>()),
  updateEmailTemplate: (...args: unknown[]) => updateEmailTemplateMock(...args),
}))

vi.mock('@/features/email-templates/variables-api', () => ({
  useEmailTemplateVariables: () => ({ data: [], isLoading: false, isError: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// Tiptap is covered by `rich-text-editor.test.tsx`; only the wiring matters here.
vi.mock('@/components/rich-text/rich-text-editor', () => ({
  RichTextEditor: forwardRef<unknown, { id?: string; value: string | null; onChange: (html: string | null) => void }>(
    function RichTextEditorMock({ id, value, onChange }) {
      return <textarea id={id} value={value ?? ''} onChange={(event) => onChange(event.target.value)} />
    },
  ),
}))

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function emailTemplate(
  overrides: Partial<EmailTemplateWithPermissions> = {},
): EmailTemplateWithPermissions {
  return {
    id: 4,
    name: 'Follow-up',
    module: 'work_orders',
    subject: 'Update on {work_order.code}',
    body: '<p>Thanks for your time</p>',
    description: 'Sent after a site visit',
    is_active: true,
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  }
}

/** One QueryClient per test: the in-place rows save through React Query. */
function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithoutClient(ui, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

/** The open row's control, by its accessible label (the closed body is a read-only rich-text surface, also a textbox). */
const controlOf = (key: string) => screen.getByLabelText(new RegExp(`^${i18n.t(key)}`))

const pencilOf = (key: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
  updateEmailTemplateMock.mockReset()
})

describe('EmailTemplateDetailView — detail fields', () => {
  it('shows the name, subject, module label, description, active flag, body and both timestamps', () => {
    render(<EmailTemplateDetailView emailTemplate={emailTemplate()} />)

    expect(screen.getByRole('heading', { name: 'Follow-up' })).toBeInTheDocument()
    expect(screen.getAllByText('Update on {work_order.code}').length).toBeGreaterThan(0)
    expect(screen.getByText('Work orders')).toBeInTheDocument()
    expect(screen.getByText('Sent after a site visit')).toBeInTheDocument()
    expect(screen.getByText('Yes')).toBeInTheDocument()
    expect(screen.getByText('Thanks for your time')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder for an empty description', () => {
    render(<EmailTemplateDetailView emailTemplate={emailTemplate({ description: null })} />)

    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('EmailTemplateDetailView — activity log section', () => {
  it('mounts the section for the viewed row when view_activity is granted', () => {
    render(
      <EmailTemplateDetailView
        emailTemplate={emailTemplate({
          permissions: { ...emailTemplate().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'email-templates', id: 4 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<EmailTemplateDetailView emailTemplate={emailTemplate()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('EmailTemplateDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil, except the immutable module', () => {
    render(<EmailTemplateDetailView emailTemplate={emailTemplate()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByLabelText(i18n.t('emailTemplates.form.name'))).not.toBeInTheDocument()
    expect(pencilOf('emailTemplates.form.name')).toBeInTheDocument()
    expect(pencilOf('emailTemplates.form.subject')).toBeInTheDocument()
    expect(pencilOf('emailTemplates.form.body')).toBeInTheDocument()
    expect(pencilOf('emailTemplates.form.module')).not.toBeInTheDocument()
  })

  it('Save sends only the changed field, never module', async () => {
    updateEmailTemplateMock.mockResolvedValue(emailTemplate({ name: 'Renamed' }))
    render(<EmailTemplateDetailView emailTemplate={emailTemplate()} />)

    fireEvent.click(pencilOf('emailTemplates.form.name') as HTMLElement)
    fireEvent.change(controlOf('emailTemplates.form.name'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateEmailTemplateMock).toHaveBeenCalledWith(4, { name: 'Renamed' }))
  })

  it('the body row opens the editor and saves the body alone', async () => {
    updateEmailTemplateMock.mockResolvedValue(emailTemplate({ body: '<p>New</p>' }))
    render(<EmailTemplateDetailView emailTemplate={emailTemplate()} />)

    fireEvent.click(pencilOf('emailTemplates.form.body') as HTMLElement)
    fireEvent.change(controlOf('emailTemplates.form.body'), { target: { value: '<p>New</p>' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateEmailTemplateMock).toHaveBeenCalledWith(4, { body: '<p>New</p>' }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<EmailTemplateDetailView emailTemplate={emailTemplate()} />)

    fireEvent.click(pencilOf('emailTemplates.form.name') as HTMLElement)
    fireEvent.change(controlOf('emailTemplates.form.name'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateEmailTemplateMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updateEmailTemplateMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<EmailTemplateDetailView emailTemplate={emailTemplate()} />)

    fireEvent.click(pencilOf('emailTemplates.form.name') as HTMLElement)
    fireEvent.change(controlOf('emailTemplates.form.name'), { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', () => {
    render(
      <EmailTemplateDetailView
        emailTemplate={emailTemplate({
          permissions: {
            ...emailTemplate().permissions,
            resource: { ...emailTemplate().permissions.resource, update: false },
          },
        })}
      />,
    )

    expect(pencilOf('emailTemplates.form.name')).not.toBeInTheDocument()
    expect(pencilOf('emailTemplates.form.body')).not.toBeInTheDocument()
  })
})
