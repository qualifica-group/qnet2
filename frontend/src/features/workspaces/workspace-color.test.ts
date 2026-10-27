import { describe, expect, it } from 'vitest'
import { BADGE_COLOR_TOKENS } from '@/features/custom-fields/badge-color-tokens'
import {
  NEUTRAL_SIDEBAR_TEXT_CLASS,
  WORKSPACE_COLOR_TOKENS,
  workspaceSidebarTextClass,
} from '@/features/workspaces/workspace-color'

describe('workspace colours (spec 0317 AC-024)', () => {
  it('covers every palette token, so none falls back to the neutral class', () => {
    expect([...WORKSPACE_COLOR_TOKENS].sort()).toEqual(BADGE_COLOR_TOKENS.map((entry) => entry.token).sort())
  })

  it('uses a light shade on the always-dark sidebar and a neutral class without colour', () => {
    expect(workspaceSidebarTextClass('blue')).toBe('text-blue-300')
    expect(workspaceSidebarTextClass(null)).toBe(NEUTRAL_SIDEBAR_TEXT_CLASS)
    expect(workspaceSidebarTextClass('unknown')).toBe(NEUTRAL_SIDEBAR_TEXT_CLASS)
  })
})
