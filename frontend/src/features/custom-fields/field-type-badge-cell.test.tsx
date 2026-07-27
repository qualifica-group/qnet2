import type { ICellRendererParams } from 'ag-grid-community'
import { render } from '@testing-library/react'
import { afterAll, beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { FieldTypeBadgeCell } from '@/features/custom-fields/field-type-badge-cell'

/**
 * The `type` column of the attributes and custom-fields grids. The backend
 * supplies the badge value only (config-driven catalogue), so the cell must
 * resolve the copy from `enums.custom_field_type.*` — never render the raw
 * value or the backend label key.
 */
function renderCell(value: unknown) {
  const params = { value } as unknown as ICellRendererParams
  return render(<FieldTypeBadgeCell {...params} />)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})
afterAll(async () => {
  await i18n.changeLanguage('it')
})

describe('FieldTypeBadgeCell', () => {
  it('renders the localized type label, not the raw value or an i18n key', () => {
    const { getByText, queryByText } = renderCell('integer')
    expect(getByText('Integer number')).toBeInTheDocument()
    expect(queryByText(/attributes\.types\.|customFields\.types\./)).toBeNull()
  })

  it('pairs the label with the type glyph', () => {
    const { getByText } = renderCell('boolean')
    expect(getByText('Yes/No').querySelector('svg')).not.toBeNull()
  })

  it('switches the label with the active language', async () => {
    await i18n.changeLanguage('it')
    const { getByText } = renderCell('integer')
    expect(getByText('Numero intero')).toBeInTheDocument()
    await i18n.changeLanguage('en')
  })

  it('renders an em dash when the value is empty', () => {
    expect(renderCell('').container.textContent).toBe('—')
  })

  it('renders an em dash when the value is missing', () => {
    expect(renderCell(null).container.textContent).toBe('—')
  })

  it('still renders the label when the type has no registered glyph', () => {
    const { getByText } = renderCell('unregistered_type')
    // enumLabelOf falls back to the raw value; the cell must not crash.
    expect(getByText('unregistered_type')).toBeInTheDocument()
  })
})
