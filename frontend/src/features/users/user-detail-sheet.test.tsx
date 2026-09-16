import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { UserDetailSheetProvider } from '@/features/users/user-detail-sheet'
import { useUserDetailSheet } from '@/features/users/user-detail-sheet-context'

const navigate = vi.hoisted(() => vi.fn())

vi.mock('react-router-dom', () => ({ useNavigate: () => navigate }))

vi.mock('@/features/users/user-detail', () => ({
  UserDetailView: ({ userId }: { userId: number }) => <div>{`user-${userId}`}</div>,
}))

vi.mock('@/features/modules/module-registry', () => ({
  getModuleRegistryEntry: (domain: string) => (domain === 'users' ? { domain: 'users', basePath: '/users' } : undefined),
}))

function OpenUserButton() {
  const { openUserDetail } = useUserDetailSheet()
  return <button onClick={() => openUserDetail(12)}>open-user</button>
}

function renderProvider() {
  return render(
    <UserDetailSheetProvider>
      <OpenUserButton />
    </UserDetailSheetProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  navigate.mockClear()
})

describe('UserDetailSheetProvider toolbar', () => {
  it('opens the user detail page and closes the Sheet', () => {
    renderProvider()

    fireEvent.click(screen.getByRole('button', { name: 'open-user' }))
    expect(screen.getByText('user-12')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Open detail page' }))

    expect(navigate).toHaveBeenCalledWith('/users/12')
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('closes the Sheet from the toolbar close button', () => {
    renderProvider()

    fireEvent.click(screen.getByRole('button', { name: 'open-user' }))
    fireEvent.click(screen.getByRole('button', { name: 'Close' }))

    expect(screen.queryByText('user-12')).not.toBeInTheDocument()
    expect(navigate).not.toHaveBeenCalled()
  })
})
