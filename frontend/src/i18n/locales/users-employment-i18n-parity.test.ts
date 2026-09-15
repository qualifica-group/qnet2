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
 * Spec 0129 AC-026: the "competent for all categories" switch and the
 * "all categories" summaries added across the users employment domain
 * (form, detail, assignment) must exist, translated, in both languages.
 */

type I18nTree = { [key: string]: string | I18nTree }

function leafPaths(tree: I18nTree, prefix = ''): string[] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [path] : leafPaths(value, path)
  })
}

function leafEntries(tree: I18nTree, prefix = ''): [string, string][] {
  return Object.entries(tree).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key
    return typeof value === 'string' ? [[path, value] as [string, string]] : leafEntries(value, path)
  })
}

const EN = { form: enForm, detail: enDetail, assignment: enAssignment }
const IT = { form: itForm, detail: itDetail, assignment: itAssignment }

describe('users employment i18n parity (spec 0129 AC-026)', () => {
  it('has the exact same set of keys in en and it', () => {
    expect(leafPaths(IT).sort()).toEqual(leafPaths(EN).sort())
  })

  it('has no empty string value in en', () => {
    expect(leafEntries(EN).filter(([, value]) => value.trim() === '')).toEqual([])
  })

  it('has no empty string value in it', () => {
    expect(leafEntries(IT).filter(([, value]) => value.trim() === '')).toEqual([])
  })
})
