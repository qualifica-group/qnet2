/** Fixed skill scale (spec 0286 D-3): the single source of the levels and of their i18n keys (`skills.levels.<n>`). */
export const SKILL_LEVELS = [1, 2, 3, 4] as const
export type SkillLevel = (typeof SKILL_LEVELS)[number]

export const SKILL_LEVEL_MIN: SkillLevel = SKILL_LEVELS[0]
export const SKILL_LEVEL_MAX: SkillLevel = SKILL_LEVELS[SKILL_LEVELS.length - 1]

export function isSkillLevel(value: unknown): value is SkillLevel {
  return SKILL_LEVELS.some((level) => level === value)
}
