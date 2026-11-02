import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { RecordSkeletonSections } from '@/components/detail/record-skeleton-sections'

describe('RecordSkeletonSections', () => {
  it('renders the slots in the canonical order whatever the props order', () => {
    render(
      <RecordSkeletonSections
        specific={<h3>H</h3>}
        document={<h3>G</h3>}
        company={<h3>F</h3>}
        team={<h3>E</h3>}
        rewards={<h3>D</h3>}
        contacts={<h3>C</h3>}
        context={<h3>B</h3>}
        details={<h3>A</h3>}
      />,
    )

    expect(screen.getAllByRole('heading').map((heading) => heading.textContent)).toEqual([
      'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
    ])
  })

  it('renders nothing for an absent slot', () => {
    render(<RecordSkeletonSections details={<h3>A</h3>} team={<h3>E</h3>} />)

    expect(screen.getAllByRole('heading').map((heading) => heading.textContent)).toEqual(['A', 'E'])
  })
})
