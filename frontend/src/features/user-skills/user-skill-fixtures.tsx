import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { Coverage, UserSkill } from '@/features/user-skills/types'

/** An editable person skill at level 3; tests override what they need. */
export function buildUserSkill(overrides: Partial<UserSkill> = {}): UserSkill {
  return {
    id: 5,
    user: { id: 7, name: 'Anna Rossi' },
    skill: { id: 3, name: 'Inglese', category: 'Lingue' },
    level: 3,
    assessed_on: '2026-03-10',
    notes: null,
    assessed_by: { id: 9, name: 'Bruno Neri' },
    can_edit: true,
    created_at: '2026-10-19T09:00:00Z',
    updated_at: '2026-10-19T09:00:00Z',
    ...overrides,
  }
}

export const COVERAGE: Coverage = {
  rows: [
    { user: { id: 7, name: 'Anna Rossi' }, skill: { id: 3, name: 'Inglese', category: 'Lingue' }, required_level: 3, level: 4, status: 'covered', user_skill_id: 5 },
    { user: { id: 7, name: 'Anna Rossi' }, skill: { id: 4, name: 'Excel', category: null }, required_level: 3, level: 2, status: 'below', user_skill_id: 6 },
    { user: { id: 8, name: 'Carlo Bianchi' }, skill: { id: 3, name: 'Inglese', category: 'Lingue' }, required_level: 2, level: null, status: 'missing', user_skill_id: null },
  ],
}

export function httpError(status: number, data: object): AxiosError {
  return Object.assign(new AxiosError('request failed'), { response: { status, data } })
}

/** One QueryClient per test, plus the router and the confirm service the screens need. */
export function userSkillWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
        </MemoryRouter>
      </QueryClientProvider>
    )
  }
}
