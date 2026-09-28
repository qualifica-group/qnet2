import { fireEvent, render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { UserDetailSheetContext } from '@/features/users/user-detail-sheet-context'
import { UserCell, UserStackCell, type UserSummary } from '@/features/table/user-cell'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function params(value: unknown): ICellRendererParams {
  return { value } as unknown as ICellRendererParams
}

/** Renders a cell with a spy opener so the "open detail" click is observable. */
function renderWithOpener(node: React.ReactElement, canOpenUserDetail = true) {
  const openUserDetail = vi.fn()
  const view = render(
    <UserDetailSheetContext.Provider value={{ openUserDetail, canOpenUserDetail }}>{node}</UserDetailSheetContext.Provider>,
  )
  return { openUserDetail, view }
}

const ADA: UserSummary = { id: 7, name: 'Ada Lovelace', avatar_url: null }

describe('UserCell', () => {
  it('renders the avatar initials and the name', () => {
    renderWithOpener(<UserCell {...params(ADA)} />)
    expect(screen.getByText('Ada Lovelace')).toBeInTheDocument()
    expect(screen.getByText('AL')).toBeInTheDocument()
  })

  it('opens the user detail with the user id when clicked', () => {
    const { openUserDetail } = renderWithOpener(<UserCell {...params(ADA)} />)
    fireEvent.click(screen.getByRole('button', { name: "View Ada Lovelace's profile" }))
    expect(openUserDetail).toHaveBeenCalledWith(7)
  })

  it('shows the person without a profile link when the actor cannot view users', () => {
    renderWithOpener(<UserCell {...params(ADA)} />, false)
    expect(screen.getByText('Ada Lovelace')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('renders an em dash when there is no user', () => {
    renderWithOpener(<UserCell {...params(null)} />)
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('renders an em dash when the value lacks an id', () => {
    renderWithOpener(<UserCell {...params({ name: 'No Id' })} />)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('UserStackCell', () => {
  const USERS: UserSummary[] = [
    { id: 1, name: 'Ada Lovelace', avatar_url: null },
    { id: 2, name: 'Grace Hopper', avatar_url: null },
    { id: 3, name: 'Katherine Johnson', avatar_url: null },
  ]

  it('renders one avatar per user', () => {
    renderWithOpener(<UserStackCell {...params(USERS)} />)
    expect(screen.getByText('AL')).toBeInTheDocument()
    expect(screen.getByText('GH')).toBeInTheDocument()
    expect(screen.getByText('KJ')).toBeInTheDocument()
  })

  it('opens the clicked user detail', () => {
    const { openUserDetail } = renderWithOpener(<UserStackCell {...params(USERS)} />)
    fireEvent.click(screen.getByRole('button', { name: "View Grace Hopper's profile" }))
    expect(openUserDetail).toHaveBeenCalledWith(2)
  })

  it('collapses users beyond the visible cap into a "+N" chip', () => {
    const many: UserSummary[] = Array.from({ length: 7 }, (_, index) => ({
      id: index + 1,
      name: `User ${index + 1}`,
      avatar_url: null,
    }))
    renderWithOpener(<UserStackCell {...params(many)} />)
    expect(screen.getByText('+2')).toBeInTheDocument()
  })

  it('renders an em dash when there is no user', () => {
    renderWithOpener(<UserStackCell {...params([])} />)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('UserCell on an editable cell (spec 0055 follow-up)', () => {
  /** ICellRendererParams stub carrying AG Grid's own per-cell editability decision. */
  function editableParams(value: unknown, editable: boolean) {
    return {
      value,
      node: {},
      column: { isCellEditable: () => editable },
    } as unknown as Parameters<typeof UserCell>[0]
  }

  it('drops the profile button so the click can start the cell editor', () => {
    renderWithOpener(<UserCell {...editableParams(ADA, true)} />)

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
    expect(screen.getByText(ADA.name)).toBeInTheDocument()
  })

  it('keeps the profile button on a NON-editable cell (no regression)', () => {
    renderWithOpener(<UserCell {...editableParams(ADA, false)} />)

    expect(screen.getByRole('button')).toBeInTheDocument()
  })
})
