import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ReferentDetailView } from '@/features/referents/referent-detail'
import {
  HIDDEN,
  READ_ONLY,
  permissionsFor,
  referentFixture,
  referentWrapper,
} from '@/features/referents/referent-test-fixtures'
import type { ReferentDetailWithPermissions } from '@/features/referents/types'

/**
 * The referente record card (spec 0016 AC-023, record kit of 2026-09-11):
 * identity, KPI strip and sections. REQUIREMENT CHANGED (spec 0216): the card
 * has no Edit action any more — its rows edit in place
 * (`referent-detail-inline-edit.test.tsx`) — and the linked user's row follows
 * the `user_id` field permission.
 */

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

function renderDetail(referent: ReferentDetailWithPermissions) {
  const { wrapper } = referentWrapper()
  return render(<ReferentDetailView referent={referent} />, { wrapper })
}

/** The value `RecordStat` renders right under its label, addressed by that label's text. */
function statValue(label: string): string | undefined {
  return screen.getByText(label).nextElementSibling?.textContent ?? undefined
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('ReferentDetailView (AC-023)', () => {
  it('renders the name, contact scope, referent type and notes', () => {
    renderDetail(referentFixture())

    expect(screen.getByRole('heading', { name: 'Ada Lovelace' })).toBeInTheDocument()
    expect(screen.getAllByText('Internal').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Sponsor').length).toBeGreaterThan(0)
    expect(screen.getByText('VIP sponsor')).toBeInTheDocument()
  })

  it('renders the contacts without any add affordance when the actor cannot edit them', () => {
    renderDetail(
      referentFixture({ permissions: permissionsFor({ 'personal_data.contacts': READ_ONLY }) }),
    )

    expect(screen.getByText('ada@work.com')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add contact' })).not.toBeInTheDocument()
  })

  it('falls back to an em dash when the referent has no type', () => {
    renderDetail(referentFixture({ referent_type: null, referent_type_id: null }))

    expect(screen.getAllByText('—').length).toBeGreaterThan(0)
  })
})

describe('ReferentDetailView — record card (user directive 2026-09-11)', () => {
  it('counts the contacts and addresses of the anagraphic card in the KPI strip', () => {
    renderDetail(referentFixture())

    expect(statValue('Contact details')).toBe('1')
    expect(statValue('Locations')).toBe('0')
    expect(statValue('Card type')).toBe('Individual')
  })

  it('names the linked system user ONCE, on its labelled row', () => {
    renderDetail(referentFixture({ user_id: 4, user: { id: 4, name: 'Ada (account)' } }))

    expect(screen.getByText('Linked user')).toBeInTheDocument()
    // Once, not twice: the identity band deliberately carries no user pill.
    expect(screen.getAllByText('Ada (account)')).toHaveLength(1)
  })

  it('omits the linked-user row when its field permission hides it (spec 0090 AC-005)', () => {
    // The API omits both keys (not `null`) when the field is hidden.
    const hidden = referentFixture({ permissions: permissionsFor({ user_id: HIDDEN }) })
    delete hidden.user_id
    delete hidden.user
    renderDetail(hidden)

    expect(screen.queryByText('Linked user')).not.toBeInTheDocument()
  })

  it('offers no Edit action: the record edits in place (spec 0216 AC-001)', () => {
    renderDetail(referentFixture())

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
  })
})
