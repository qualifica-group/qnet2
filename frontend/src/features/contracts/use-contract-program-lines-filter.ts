import { useMemo, useState } from 'react'
import type { ContractProgrammableLine } from '@/features/contracts/types'

/** Category filter value meaning "every category". */
export const ALL_CATEGORIES = 'all'
/** Category filter value for the lines whose product has no category. */
export const NO_CATEGORY = 'none'

export interface ProgramLineCategory {
  value: string
  name: string
}

/** Lower-case, accent-free form, so "attivita" finds "Attività". */
function fold(text: string): string {
  return text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()
}

function categoryValueOf(line: ContractProgrammableLine): string {
  return line.product?.category ? String(line.product.category.id) : NO_CATEGORY
}

/** The distinct categories of the lines, by name; "no category" is left to the caller's own option. */
export function programLineCategories(lines: ContractProgrammableLine[]): ProgramLineCategory[] {
  const byValue = new Map<string, string>()
  for (const line of lines) {
    const category = line.product?.category
    if (category) {
      byValue.set(String(category.id), category.name)
    }
  }
  return Array.from(byValue, ([value, name]) => ({ value, name })).sort((a, b) => a.name.localeCompare(b.name))
}

/** Lines matching the search (product name or code, every word) and the category, in their original order. */
export function filterProgramLines(
  lines: ContractProgrammableLine[],
  search: string,
  category: string,
): ContractProgrammableLine[] {
  const words = fold(search).split(/\s+/).filter(Boolean)
  return lines.filter((line) => {
    if (category !== ALL_CATEGORIES && categoryValueOf(line) !== category) {
      return false
    }
    const haystack = fold(`${line.product?.code ?? ''} ${line.product?.name ?? ''}`)
    return words.every((word) => haystack.includes(word))
  })
}

/**
 * Client-side search and category filter of the "Programma" lines table: the
 * offer's lines are already loaded, so it only narrows what the table shows.
 * The selection is untouched: a hidden selected line stays selected.
 */
export function useContractProgramLinesFilter(lines: ContractProgrammableLine[]) {
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState<string>(ALL_CATEGORIES)

  const categories = useMemo(() => programLineCategories(lines), [lines])
  const hasUncategorized = useMemo(() => lines.some((line) => !line.product?.category), [lines])
  const visibleLines = useMemo(() => filterProgramLines(lines, search, category), [lines, search, category])

  return { search, setSearch, category, setCategory, categories, hasUncategorized, visibleLines }
}
