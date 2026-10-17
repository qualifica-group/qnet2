import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { OrgTree } from '@/features/org-chart/components/org-tree'
import { buildPeopleTree } from '@/features/org-chart/build-people-tree'
import { PEOPLE_FIXTURE } from '@/features/org-chart/fixtures.test-support'

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

describe('OrgTree row hooks (for the move feature)', () => {
  it('renders renderRowActions per row and lets wrapRow wrap the row', () => {
    render(
      <OrgTree
        nodes={buildPeopleTree(PEOPLE_FIXTURE)}
        collapsedKeys={new Set()}
        onToggle={() => {}}
        ariaLabel="test"
        renderRowActions={(node) => <button type="button">{`azioni ${node.key}`}</button>}
        wrapRow={(node, row) => <div data-testid={`wrap-${node.key}`}>{row}</div>}
      />,
    )
    expect(screen.getByRole('button', { name: 'azioni p:1/p:2/p:3' })).toBeInTheDocument()
    const wrapper = screen.getByTestId('wrap-p:4')
    expect(within(wrapper).getByRole('button', { name: 'azioni p:4' })).toBeInTheDocument()
  })
})
