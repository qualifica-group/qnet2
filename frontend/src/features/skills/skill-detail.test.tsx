import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { SkillDetailView } from '@/features/skills/skill-detail'
import { moduleScreen } from '@/features/skills/skill-screens'
import type { SkillDetail } from '@/features/skills/types'

/** The detail edits every row in place, each save a PATCH of that field alone. */

const updateMock = vi.fn()
vi.mock('@/features/skills/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/skills/api')>()),
  updateSkill: (...args: unknown[]) => updateMock(...args),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log-section</div>,
}))

function skill(update = true): SkillDetail {
  return {
    id: 4,
    name: 'Inglese',
    category: 'Lingue',
    description: null,
    is_active: true,
    job_roles: [{ id: 2, name: 'Operaio', min_level: 3 }],
    users_count: 6,
    created_at: '2026-10-19T08:00:00Z',
    updated_at: '2026-10-19T08:00:00Z',
    permissions: {
      resource: { view: true, create: false, update, delete: false, export: false, import: false },
      fields: {},
      actions: {},
    },
  }
}

function renderDetail(node: ReactNode) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>{node}</MemoryRouter>
    </QueryClientProvider>,
  )
}

const pencil = (key: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) })

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  updateMock.mockReset()
})

describe('SkillDetailView', () => {
  it('shows category, the job role with its minimum level in text, and the people count', () => {
    renderDetail(<SkillDetailView skill={skill()} />)

    expect(screen.getByText('Lingue')).toBeInTheDocument()
    expect(screen.getByText('Operaio')).toBeInTheDocument()
    expect(screen.getByText(`3 · ${i18n.t('skills.levels.3')}`)).toBeInTheDocument()
    expect(screen.getByText('6')).toBeInTheDocument()
  })

  it('hides the edit pencils without the update permission', () => {
    renderDetail(<SkillDetailView skill={skill(false)} />)

    expect(pencil('skills.form.name')).not.toBeInTheDocument()
    expect(pencil('skills.form.jobRoles')).not.toBeInTheDocument()
  })

  it('saves one field alone with a PATCH', async () => {
    updateMock.mockResolvedValue(skill())
    renderDetail(<SkillDetailView skill={skill()} />)

    fireEvent.click(pencil('skills.form.category') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Idiomi' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(4, { category: 'Idiomi' }))
  })

  it('registers as a module with the skills domain and no edit route', () => {
    expect(moduleScreen).toMatchObject({ domain: 'skills', basePath: '/skills', generateEditRoute: false })
  })
})
