import { describe, expect, it } from 'vitest'
import {
  usersAssignment as enAssignment,
  usersDetailEmployment as enDetail,
  usersFormEmployment as enForm,
} from '@/i18n/locales/en-users-employment'
import {
  usersAssignment as itAssignment,
  usersDetailEmployment as itDetail,
  usersFormEmployment as itForm,
} from '@/i18n/locales/it-users-employment'

/**
 * Spec 0286 D-1 (REQUIREMENT CHANGED): the assignment "Competenza" of the user form is now
 * "Ambito di assegnazione" / "Assignment scope". Labels only: the keys (`productLines`,
 * `competence`) are unchanged because the fields and the API are.
 */

type I18nTree = { [key: string]: string | I18nTree }

function strings(tree: I18nTree): string[] {
  return Object.values(tree).flatMap((value) => (typeof value === 'string' ? [value] : strings(value)))
}

describe('assignment scope rename (spec 0286 D-1)', () => {
  it('names the form row, the chips, the blockers and the stats in Italian', () => {
    expect(itForm.productLines).toBe('Ambito di assegnazione')
    expect(itDetail.productLines).toBe('Ambito di assegnazione')
    expect(itForm.coversAllProductCategories).toBe('Ambito su tutte le categorie')
    expect(itAssignment.chips.competence).toBe('Ambiti di assegnazione')
    expect(itAssignment.stats.competence).toBe('Ambiti di assegnazione')
    expect(itAssignment.stats.blockers.competence).toBe('Manca l’ambito di assegnazione')
    expect(itAssignment.blockers.competence).toMatch(/^Nessun ambito di assegnazione configurato/)
  })

  it('names the same things in English', () => {
    expect(enForm.productLines).toBe('Assignment scope')
    expect(enDetail.productLines).toBe('Assignment scope')
    expect(enForm.coversAllProductCategories).toBe('Scope on all categories')
    expect(enAssignment.chips.competence).toBe('Assignment scopes')
    expect(enAssignment.stats.competence).toBe('Assignment scopes')
    expect(enAssignment.stats.blockers.competence).toBe('Assignment scope missing')
    expect(enAssignment.blockers.competence).toMatch(/^No assignment scope configured/)
  })

  it('leaves no user-facing "competenza" or "competence" in the assignment configuration', () => {
    const italian = [...strings(itForm), ...strings(itDetail), ...strings(itAssignment)]
    const english = [...strings(enForm), ...strings(enDetail), ...strings(enAssignment)]

    expect(italian.filter((text) => /competen/i.test(text))).toEqual([])
    expect(english.filter((text) => /competen/i.test(text))).toEqual([])
  })
})
