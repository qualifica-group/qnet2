import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import type { User } from '@/features/auth/types'
import { NavUserHeader } from './nav-user-header'

vi.mock('@/features/auth/use-auth', () => ({ useAuth: () => ({ logout: vi.fn() }) }))

const baseUser = {
  id: 1,
  name: 'Mario Rossi',
  email: 'mario.rossi@example.test',
  roles: [{ id: 7, name: 'Operatore base' }],
  avatar_url: null,
} as User

function renderHeader(user: User) {
  render(
    <MemoryRouter>
      <NavUserHeader user={user} />
    </MemoryRouter>,
  )
}

describe('NavUserHeader', () => {
  it('shows the job role under the name instead of the access role', () => {
    renderHeader({
      ...baseUser,
      employment: {
        primary_operational_site_id: null,
        job_roles: [{ id: 3, name: 'Consulente' }],
      },
    })

    expect(screen.getByText('Consulente')).toBeInTheDocument()
    expect(screen.queryByText('Operatore base')).not.toBeInTheDocument()
  })

  it('lists several job roles in alphabetical order', () => {
    renderHeader({
      ...baseUser,
      employment: {
        primary_operational_site_id: null,
        job_roles: [
          { id: 3, name: 'Tecnico' },
          { id: 4, name: 'Consulente' },
        ],
      },
    })

    expect(screen.getByText('Consulente, Tecnico')).toBeInTheDocument()
  })

  it.each([
    ['no job role', { primary_operational_site_id: null, job_roles: [] }],
    ['no employment profile', undefined],
  ])('falls back to the email with %s', (_label, employment) => {
    renderHeader({ ...baseUser, employment })

    expect(screen.getByText(baseUser.email)).toBeInTheDocument()
    expect(screen.queryByText('Operatore base')).not.toBeInTheDocument()
  })
})
