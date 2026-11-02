import { render } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { describe, expect, it } from 'vitest'
import { supportTopicColumnRenderers } from '@/features/support-topics/column-renderers'

function renderIconCell(value: unknown) {
  const renderIcon = supportTopicColumnRenderers.icon
  return render(<>{renderIcon({ value } as ICellRendererParams)}</>)
}

describe('supportTopicColumnRenderers.icon (spec 0334)', () => {
  it('draws the icon instead of printing its internal name', () => {
    const { container } = renderIconCell('printer')

    expect(container.querySelector('svg')).not.toBeNull()
    expect(container).not.toHaveTextContent('printer')
  })

  it('renders an empty cell for an unknown or missing icon', () => {
    expect(renderIconCell('not-an-icon').container.querySelector('svg')).toBeNull()
    expect(renderIconCell(null).container.querySelector('svg')).toBeNull()
  })
})
