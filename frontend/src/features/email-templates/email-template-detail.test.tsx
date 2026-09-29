import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { EmailTemplateDetailView } from '@/features/email-templates/email-template-detail'
import type { EmailTemplateWithPermissions } from '@/features/email-templates/types'

/**
 * Spec 0175: the detail shows the name (hero title), subject as the header
 * subtitle, module label, description, active flag, the sanitized body
 * rendered through `RichTextContent` (never `dangerouslySetInnerHTML`) and
 * both timestamps.
 */

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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
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
