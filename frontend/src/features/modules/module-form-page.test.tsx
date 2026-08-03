import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import i18n from '@/i18n'
import ModuleFormPage from '@/features/modules/module-form-page'

/**
 * AC-005/AC-006 (spec 0045) — `ModuleFormPage` is the create form's only
 * params channel: it turns the current query string into `mode.params` so
 * `FormScreen` never needs its own `useSearchParams()`. A regression check
 * for the edit branch (untouched by spec 0045) closes the file.
 */

vi.mock('@/features/modules/module-registry', () => ({
  getModuleRegistryEntry: (domain: string) =>
    domain === 'projects' || domain === 'reward-types' || domain === 'request-management'
      ? {
          // The one module whose FormScreen renders its own visible heading
          // (user directive 2026-08-03): the host must not render a second.
          formOwnsHeader: domain === 'request-management',
          domain,
          basePath: `/${domain}`,
          defaultMode: 'page',
          labelKey: 'navigation.projects',
          DetailScreen: ({ id }: { id: number }) => <div>detail-{id}</div>,
          FormScreen: ({
            mode,
          }: {
            mode: { type: string; id?: number; params?: Record<string, string | number> }
          }) => (
            <div>
              <div>{`form-${mode.type}${mode.type === 'edit' ? `-${mode.id}` : ''}`}</div>
              {mode.type === 'create' && <div>{`params:${JSON.stringify(mode.params ?? null)}`}</div>}
            </div>
          ),
        }
      : undefined,
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: () => null,
}))

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/projects/new" element={<ModuleFormPage domain="projects" />} />
        <Route path="/projects/:id/edit" element={<ModuleFormPage domain="projects" />} />
      </Routes>
    </MemoryRouter>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ModuleFormPage', () => {
  it('AC-005: a query string on /new is turned into mode.params', () => {
    renderAt('/projects/new?lead_id=7')

    expect(screen.getByText('form-create')).toBeInTheDocument()
    expect(screen.getByText('params:{"lead_id":"7"}')).toBeInTheDocument()
  })

  it('AC-006: /new with no query string yields mode.params undefined', () => {
    renderAt('/projects/new')

    expect(screen.getByText('form-create')).toBeInTheDocument()
    expect(screen.getByText('params:null')).toBeInTheDocument()
  })

  it('regression: the edit branch is untouched by the params channel', () => {
    renderAt('/projects/5/edit')

    expect(screen.getByText('form-edit-5')).toBeInTheDocument()
  })

  /**
   * Strings are keyed by the camelCase i18n namespace, while the registry
   * domain (and the permission) stay kebab-case. Interpolating the raw domain
   * rendered `reward-types.form.createTitle` verbatim as the page title for
   * every multi-word module. The pre-existing cases above all use `projects`,
   * a single-word domain where the two spellings coincide — which is exactly
   * why the defect survived: it is invisible unless the domain has a dash.
   */
  it('renders translated title/subtitle for a kebab-case domain, not the raw key', () => {
    render(
      <MemoryRouter initialEntries={['/reward-types/new']}>
        <Routes>
          <Route path="/reward-types/new" element={<ModuleFormPage domain="reward-types" />} />
        </Routes>
      </MemoryRouter>,
    )

    expect(screen.getByRole('heading', { name: 'Create reward type' })).toBeInTheDocument()
    expect(screen.getByText('Add a new voucher, reward or incentive type.')).toBeInTheDocument()
    expect(screen.queryByText(/reward-types\.form\./)).not.toBeInTheDocument()
  })

  /**
   * User directive 2026-08-03: the request-management create form carries its
   * own heading WITH the save/cancel actions on the same row, so the host's
   * title/subtitle block would be a visible duplicate of it.
   */
  it('omits its own heading for a formOwnsHeader module', () => {
    render(
      <MemoryRouter initialEntries={['/request-management/new']}>
        <Routes>
          <Route path="/request-management/new" element={<ModuleFormPage domain="request-management" />} />
        </Routes>
      </MemoryRouter>,
    )

    expect(screen.getByText('form-create')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'New request' })).not.toBeInTheDocument()
    expect(
      screen.queryByText('Client details and product lines of the new request.'),
    ).not.toBeInTheDocument()
  })
})
