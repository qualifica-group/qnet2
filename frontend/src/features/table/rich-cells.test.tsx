import { render } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { Building2 } from 'lucide-react'
import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  BooleanBadgeCell,
  CodeBadgeCell,
  ColorSwatchCell,
  CurrencyCell,
  DateCell,
  GroupCell,
  RelationCell,
  StatusBadgeCell,
} from '@/features/table/rich-cells'

/**
 * The shared cross-module cell library: one component per data kind, reused by
 * every table (projects/campaigns/leads/imports/status configurators). These
 * tests pin the visual contract each cell exposes — the value shown, the empty
 * fallback, and the accessibility-relevant markup (status dot, avatar initials).
 */
beforeAll(async () => {
  await i18n.changeLanguage('en')
})

/** Builds an AG Grid renderer params stub carrying just the cell `value`. */
function params(value: unknown): ICellRendererParams {
  return { value } as unknown as ICellRendererParams
}

describe('RelationCell', () => {
  it('renders the relation name', () => {
    const { getByText } = render(<RelationCell {...params({ name: 'Acme Corp' })} />)
    expect(getByText('Acme Corp')).toBeInTheDocument()
  })

  it('falls back to the composed label when there is no name (operational site)', () => {
    const { getByText } = render(<RelationCell {...params({ label: 'Via Roma 1 - Milano' })} />)
    expect(getByText('Via Roma 1 - Milano')).toBeInTheDocument()
  })

  it('renders the optional leading kind icon', () => {
    const { container, getByText } = render(
      <RelationCell {...params({ name: 'Acme Corp' })} icon={Building2} />,
    )
    expect(getByText('Acme Corp')).toBeInTheDocument()
    expect(container.querySelector('svg')).not.toBeNull()
  })

  it('renders an em dash when the relation is null', () => {
    const { getByText } = render(<RelationCell {...params(null)} />)
    expect(getByText('—')).toBeInTheDocument()
  })
})

describe('CodeBadgeCell', () => {
  it('renders the code inside a badge', () => {
    const { getByText } = render(<CodeBadgeCell {...params('PRJ-001')} />)
    expect(getByText('PRJ-001')).toBeInTheDocument()
  })

  it('renders an em dash when the code is empty', () => {
    const { getByText } = render(<CodeBadgeCell {...params('')} />)
    expect(getByText('—')).toBeInTheDocument()
  })
})

describe('StatusBadgeCell', () => {
  it('renders the status name with its color class and a leading status dot', () => {
    const { getByText, container } = render(
      <StatusBadgeCell {...params({ name: 'Won', color: 'blue' })} />,
    )
    expect(getByText('Won')).toBeInTheDocument()
    // The soft badge class sits on the pill, the strong shade on the status dot.
    expect(container.querySelector('.bg-blue-100')).not.toBeNull()
    expect(container.querySelector('.bg-blue-500')).not.toBeNull()
  })

  it('renders the configured icon INSTEAD of the dot when the row carries one', () => {
    const { getByText, container } = render(
      <StatusBadgeCell {...params({ name: 'Won', color: 'blue', icon: 'badge-check' })} />,
    )
    expect(getByText('Won')).toBeInTheDocument()
    expect(container.querySelector('svg')).not.toBeNull()
    // The icon replaces the status dot: the two marks never show together.
    expect(container.querySelector('.bg-blue-500')).toBeNull()
  })

  it('keeps the dot when the icon name is unknown to the catalog', () => {
    const { container } = render(
      <StatusBadgeCell {...params({ name: 'Won', color: 'blue', icon: null })} />,
    )
    expect(container.querySelector('.bg-blue-500')).not.toBeNull()
  })

  it('renders a neutral badge (no dot) for a colorless status', () => {
    const { getByText, container } = render(<StatusBadgeCell {...params({ name: 'Draft', color: null })} />)
    expect(getByText('Draft')).toBeInTheDocument()
    expect(container.querySelector('.bg-blue-500')).toBeNull()
  })

  it('renders an em dash when the status is null', () => {
    const { getByText } = render(<StatusBadgeCell {...params(null)} />)
    expect(getByText('—')).toBeInTheDocument()
  })

  it('renders the description hint next to the badge when the status carries a description', () => {
    const { getByRole } = render(
      <StatusBadgeCell {...params({ name: 'Waiting', color: 'amber', description: 'Waiting for the client' })} />,
    )
    expect(getByRole('button', { name: 'Show the status description' })).toBeInTheDocument()
  })

  it('renders no description hint when the status has no description', () => {
    const { queryByRole } = render(<StatusBadgeCell {...params({ name: 'Won', color: 'blue' })} />)
    expect(queryByRole('button')).toBeNull()
  })
})

describe('DateCell', () => {
  it('renders a localized date for a valid Y-m-d value', () => {
    const { queryByText, container } = render(<DateCell {...params('2026-07-17')} />)
    expect(queryByText('—')).toBeNull()
    expect(container.textContent).toContain('2026')
  })

  it('renders an em dash for an empty value', () => {
    expect(render(<DateCell {...params('')} />).getByText('—')).toBeInTheDocument()
  })

  it('renders an em dash for an unparseable value', () => {
    expect(render(<DateCell {...params('not-a-date')} />).getByText('—')).toBeInTheDocument()
  })
})

describe('CurrencyCell', () => {
  it('renders a formatted amount for a decimal string', () => {
    const { queryByText, container } = render(<CurrencyCell {...params('1500.00')} />)
    expect(queryByText('—')).toBeNull()
    expect(container.textContent).toMatch(/\d/)
  })

  it('renders an em dash when the amount is null', () => {
    expect(render(<CurrencyCell {...params(null)} />).getByText('—')).toBeInTheDocument()
  })
})

describe('BooleanBadgeCell', () => {
  it('renders a yes badge with an icon when true', () => {
    const { getByText, container } = render(<BooleanBadgeCell {...params(true)} />)
    expect(getByText(i18n.t('common.yes'))).toBeInTheDocument()
    expect(container.querySelector('svg')).not.toBeNull()
  })

  it('renders a no badge when false', () => {
    const { getByText } = render(<BooleanBadgeCell {...params(false)} />)
    expect(getByText(i18n.t('common.no'))).toBeInTheDocument()
  })

  it('renders an em dash for a non-boolean value', () => {
    expect(render(<BooleanBadgeCell {...params(null)} />).getByText('—')).toBeInTheDocument()
  })
})

describe('ColorSwatchCell', () => {
  it('renders the localized token name', () => {
    const { getByText } = render(<ColorSwatchCell {...params('blue')} />)
    expect(getByText(i18n.t('customFields.colors.blue'))).toBeInTheDocument()
  })

  it('renders an em dash for a missing token', () => {
    expect(render(<ColorSwatchCell {...params(null)} />).getByText('—')).toBeInTheDocument()
  })
})

describe('GroupCell', () => {
  it('renders the localized group label under the given namespace', () => {
    const { getByText } = render(
      <GroupCell {...params('open')} labelPrefix="pipelineStatuses.form.group" />,
    )
    expect(getByText(i18n.t('pipelineStatuses.form.group.open'))).toBeInTheDocument()
  })

  it.each([
    ['open', 'bg-green-500'],
    ['pending', 'bg-orange-500'],
    ['closed', 'bg-red-500'],
  ])('renders the %s group with a %s swatch dot', (group, swatchClass) => {
    const { container } = render(
      <GroupCell {...params(group)} labelPrefix="pipelineStatuses.form.group" />,
    )
    expect(container.querySelector(`.${swatchClass}`)).not.toBeNull()
  })

  it('renders an em dash when the group is null', () => {
    const { getByText } = render(
      <GroupCell {...params(null)} labelPrefix="pipelineStatuses.form.group" />,
    )
    expect(getByText('—')).toBeInTheDocument()
  })
})
