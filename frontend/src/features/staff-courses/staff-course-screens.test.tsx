import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { moduleScreen, StaffCourseDetailScreen } from '@/features/staff-courses/staff-course-screens'

vi.mock('@/hooks/use-entity-detail', () => ({
  useEntityDetail: () => ({ data: undefined, isLoading: false, isError: true, error: new Error('boom'), refetch: vi.fn() }),
}))

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

describe('staff-courses module screens', () => {
  it('labels the module with an existing navigation key', () => {
    expect(i18n.exists(moduleScreen.labelKey)).toBe(true)
  })

  it('shows the translated load error of the course detail', () => {
    render(<StaffCourseDetailScreen id={1}onChanged={vi.fn()} />)

    expect(screen.getByText('Impossibile caricare il corso. Riprova.')).toBeInTheDocument()
  })
})
