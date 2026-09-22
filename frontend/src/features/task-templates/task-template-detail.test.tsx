import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { TaskTemplateDetailView } from '@/features/task-templates/task-template-detail'
import type { TaskTemplateDetailWithPermissions } from '@/features/task-templates/types'

/**
 * Spec 0128 AC-024: the template's header description and each row's own
 * description render through `RichTextContent`, never raw markup.
 */

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => null,
}))

vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: () => null,
}))

// The real Tiptap read-only renderer is covered by rich-text-content.test.tsx (AC-020).
vi.mock('@/components/rich-text/rich-text-content', () => ({
  RichTextContent: ({ html }: { html: string | null }) => (html ? <span>{html}</span> : null),
}))

function taskTemplate(
  overrides: Partial<TaskTemplateDetailWithPermissions> = {},
): TaskTemplateDetailWithPermissions {
  return {
    id: 1,
    name: 'Standard onboarding',
    description: null,
    is_active: true,
    items_count: 0,
    stages: [],
    items: [],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('TaskTemplateDetailView — description via RichTextContent (AC-024)', () => {
  it('hands the header HTML to RichTextContent', () => {
    render(<TaskTemplateDetailView taskTemplate={taskTemplate({ description: '<p><strong>Ciao</strong></p>' })} />)

    expect(screen.getByText('<p><strong>Ciao</strong></p>')).toBeInTheDocument()
  })

  it('hands each row own description HTML to RichTextContent', () => {
    render(
      <TaskTemplateDetailView
        taskTemplate={taskTemplate({
          items_count: 1,
          items: [
            {
              id: 11,
              title: 'Kickoff call',
              description: '<p>Chiamare il cliente</p>',
              estimated_minutes: null,
              task_status_id: null,
              task_status: null,
              due_offset_days: 0,
              sort_order: 1,
              task_template_stage_id: null,
              attachments: [],
            },
          ],
        })}
      />,
    )

    expect(screen.getByText('<p>Chiamare il cliente</p>')).toBeInTheDocument()
  })
})

describe('TaskTemplateDetailView — items grouped by fase (spec 0146 D-2)', () => {
  it('renders one group per stage, in stage order, "Senza fase" last', () => {
    render(
      <TaskTemplateDetailView
        taskTemplate={taskTemplate({
          items_count: 2,
          stages: [
            { id: 1, name: 'Analisi', sort_order: 0 },
            { id: 2, name: 'Sviluppo', sort_order: 1 },
          ],
          items: [
            {
              id: 20,
              title: 'Task senza fase',
              description: null,
              estimated_minutes: null,
              task_status_id: null,
              task_status: null,
              due_offset_days: 0,
              sort_order: 0,
              task_template_stage_id: null,
              attachments: [],
            },
            {
              id: 21,
              title: 'Task sviluppo',
              description: null,
              estimated_minutes: null,
              task_status_id: null,
              task_status: null,
              due_offset_days: 0,
              sort_order: 1,
              task_template_stage_id: 2,
              attachments: [],
            },
          ],
        })}
      />,
    )

    const headings = screen.getAllByText(/^(Analisi|Sviluppo|No phase)$/)
    expect(headings.map((heading) => heading.textContent)).toEqual(['Analisi', 'Sviluppo', 'No phase'])
    expect(screen.getByText('Task sviluppo')).toBeInTheDocument()
    expect(screen.getByText('Task senza fase')).toBeInTheDocument()
  })
})
