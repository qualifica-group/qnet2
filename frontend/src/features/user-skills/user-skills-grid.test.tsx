import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { UserSkillsGrid } from '@/features/user-skills/user-skills-grid'
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

describe('UserSkillsGrid', () => {
  it('mounts the user-skills domain and draws the integer level with its number and name', () => {
    render(<UserSkillsGrid tableRef={{ current: null }} onOpen={vi.fn()} />)

    expect(captured?.domain).toBe('user-skills')
    const cell = captured?.renderers?.level
    expect(cell).toBeDefined()
    render(<>{cell?.({ value: 4 } as never)}</>)
    expect(screen.getByText(`4 · ${i18n.t('skills.levels.4')}`)).toBeInTheDocument()
  })
})
