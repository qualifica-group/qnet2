import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { StaffTrainingRegister } from '@/features/staff-trainings/staff-training-register'
import type { TableRendererMap } from '@/features/table/renderer-registry'

let captured: { domain: string; renderers?: TableRendererMap } | undefined
vi.mock('@/features/table/table-view', () => ({
  TableView: (props: { domain: string; renderers?: TableRendererMap }) => {
    captured = props
    return <div />
  },
}))

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

describe('StaffTrainingRegister', () => {
  it('draws the raw outcome as the translated outcome badge', () => {
    render(<StaffTrainingRegister tableRef={{ current: null }} onOpen={vi.fn()} />)

    expect(captured?.domain).toBe('staff-trainings')
    const cell = captured?.renderers?.outcome
    expect(cell).toBeDefined()
    render(<>{cell?.({ value: 'failed' } as never)}</>)
    expect(screen.getByText('Non superato')).toBeInTheDocument()
  })

  it('localizes the outcome values the grid filter reads through the column enum key', () => {
    expect(i18n.t('enums.staff_training_outcome.passed')).toBe('Superato')
    expect(i18n.t('enums.staff_training_outcome.passed', { lng: 'en' })).toBe('Passed')
  })
})
