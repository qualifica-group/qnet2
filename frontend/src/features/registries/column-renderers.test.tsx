import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { registryColumnRenderers } from '@/features/registries/column-renderers'

function renderCell(columnId: string, value: unknown) {
  const renderer = registryColumnRenderers[columnId]
  if (!renderer) {
    throw new Error(`Missing renderer for column "${columnId}"`)
  }
  const params = { value } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('registryColumnRenderers.source', () => {
  it('renders the hydrated source name', () => {
    renderCell('source', { id: 3, name: 'Website' })
    expect(screen.getByText('Website')).toBeInTheDocument()
  })

  it('renders an em dash when the registry has no source', () => {
    renderCell('source', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('registryColumnRenderers.is_supplier', () => {
  it('renders a "Yes" badge when true', () => {
    renderCell('is_supplier', true)
    expect(screen.getByText('Yes')).toBeInTheDocument()
  })

  it('renders a "No" badge when false', () => {
    renderCell('is_supplier', false)
    expect(screen.getByText('No')).toBeInTheDocument()
  })
})

describe('registryColumnRenderers.agreement_status', () => {
  it('renders a localized badge for "negotiating"', () => {
    renderCell('agreement_status', 'negotiating')
    expect(screen.getByText('Negotiating')).toBeInTheDocument()
  })

  it('renders an em dash for a null value', () => {
    renderCell('agreement_status', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('registryColumnRenderers.size_class', () => {
  it('renders a localized badge for "small"', () => {
    renderCell('size_class', 'small')
    expect(screen.getByText('Small')).toBeInTheDocument()
  })

  it('renders an em dash for a null value', () => {
    renderCell('size_class', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('registryColumnRenderers.primary_contact', () => {
  it('reuses the shared ContactsCell: a count badge for the primary contacts', () => {
    renderCell('primary_contact', [
      { type: 'email', icon: 'mail', label: 'Work', value: 'ada@example.com' },
    ])
    expect(screen.getByLabelText('1 primary contacts')).toBeInTheDocument()
  })

  it('renders an em dash when the registry has no primary contact', () => {
    renderCell('primary_contact', [])
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('registryColumnRenderers team columns', () => {
  it.each(['commercial', 'reporter'])('renders the %s referent name', (columnId) => {
    renderCell(columnId, { id: 7, name: 'Carla Rossi' })
    expect(screen.getByText('Carla Rossi')).toBeInTheDocument()
  })

  it.each(['commercial', 'reporter', 'supervisor', 'managers'])('renders an em dash for %s when unset', (columnId) => {
    renderCell(columnId, columnId === 'managers' ? [] : null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('wires supervisor and managers ("Operatori") to the shared person cells', () => {
    expect(registryColumnRenderers.supervisor).toBeTypeOf('function')
    expect(registryColumnRenderers.managers).toBeTypeOf('function')
  })
})
