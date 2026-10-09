import type { ContractProgrammableLine } from '@/features/contracts/types'
import type { ContractProgramCommon, ContractProgramGroup } from '@/features/contracts/contract-program-schema'

/** Safety cap per save, mirrors the server's `ContractProgramBatch::MAX_GROUPS` (spec 0215 D-9). */
export const MAX_PROGRAM_GROUPS = 50

let groupKeyCounter = 0

/** Client-only stable identity of a group (survives reorders and deletions, unlike its index). */
export function nextGroupKey(): string {
  groupKeyCounter += 1
  return `group-${groupKeyCounter}`
}

/** A group inheriting the four common values at creation time (D-7); the title starts blank = automatic. */
export function createGroup(common: ContractProgramCommon, lineIds: number[] = []): ContractProgramGroup {
  return {
    key: nextGroupKey(),
    title: '',
    type: common.type,
    start_date: common.start_date,
    supervisor_ids: [...common.supervisor_ids],
    task_template_id: common.task_template_id,
    quote_line_ids: lineIds,
  }
}

/** Index of the group holding the line, or `null` when it is still free. */
export function groupOfLine(groups: ContractProgramGroup[], lineId: number): number | null {
  const index = groups.findIndex((group) => group.quote_line_ids.includes(lineId))
  return index === -1 ? null : index
}

/**
 * Takes the lines out of every group (a line belongs to at most one, D-6) and
 * drops the groups that the move left empty: used by the actions that
 * re-partition lines into brand-new groups.
 */
function moveOut(groups: ContractProgramGroup[], lineIds: number[]): ContractProgramGroup[] {
  const moved = new Set(lineIds)
  return groups.flatMap((group) => {
    const kept = group.quote_line_ids.filter((id) => !moved.has(id))
    if (kept.length === group.quote_line_ids.length) {
      return [group]
    }
    return kept.length > 0 ? [{ ...group, quote_line_ids: kept }] : []
  })
}

/** The group list when it fits the cap, else the untouched `current` (callers detect the refusal by identity). */
function withinLimit(next: ContractProgramGroup[], current: ContractProgramGroup[]): ContractProgramGroup[] {
  return next.length > MAX_PROGRAM_GROUPS ? current : next
}

/**
 * Lines a bulk action works on: the selected programmable ones or, with no
 * selection, every still-free line (D-8). Always in offer order.
 */
export function resolveTargetLines(
  lines: ContractProgrammableLine[],
  groups: ContractProgramGroup[],
  selectedIds: number[],
): ContractProgrammableLine[] {
  const programmable = lines.filter((line) => line.work_order === null)
  const selected = programmable.filter((line) => selectedIds.includes(line.id))
  return selected.length > 0 ? selected : programmable.filter((line) => groupOfLine(groups, line.id) === null)
}

/** "Nuovo gruppo": appends a group holding the lines (moved from wherever they were); no lines = an empty group. */
export function newGroupFromLines(
  groups: ContractProgramGroup[],
  common: ContractProgramCommon,
  lineIds: number[],
): ContractProgramGroup[] {
  return withinLimit([...moveOut(groups, lineIds), createGroup(common, lineIds)], groups)
}

/** "Aggiungi a gruppo": MOVES the lines into the group at `index` (D-6). Groups left empty are kept. */
export function addLinesToGroup(
  groups: ContractProgramGroup[],
  index: number,
  lineIds: number[],
): ContractProgramGroup[] {
  const moved = new Set(lineIds)
  return groups.map((group, position) => {
    const others = group.quote_line_ids.filter((id) => !moved.has(id))
    return { ...group, quote_line_ids: position === index ? [...others, ...lineIds] : others }
  })
}

/** Frees one line; the group stays, possibly empty (the client validation flags it). */
export function removeLineFromGroup(groups: ContractProgramGroup[], index: number, lineId: number): ContractProgramGroup[] {
  return groups.map((group, position) =>
    position === index ? { ...group, quote_line_ids: group.quote_line_ids.filter((id) => id !== lineId) } : group,
  )
}

/** Deletes the group; its lines become free again. */
export function removeGroup(groups: ContractProgramGroup[], index: number): ContractProgramGroup[] {
  return groups.filter((_, position) => position !== index)
}

/** "Una commessa per ogni riga": one single-line group per target line. */
export function oneGroupPerLine(
  groups: ContractProgramGroup[],
  common: ContractProgramCommon,
  targets: ContractProgrammableLine[],
): ContractProgramGroup[] {
  if (targets.length === 0) {
    return groups
  }
  const created = targets.map((line) => createGroup(common, [line.id]))
  return withinLimit([...moveOut(groups, targets.map((line) => line.id)), ...created], groups)
}

/**
 * "Raggruppa per categoria": one group per product category, in order of first
 * appearance; lines without a category share one group (D-8).
 */
export function groupByCategory(
  groups: ContractProgramGroup[],
  common: ContractProgramCommon,
  targets: ContractProgrammableLine[],
): ContractProgramGroup[] {
  if (targets.length === 0) {
    return groups
  }
  const idsByCategory = new Map<number | null, number[]>()
  for (const line of targets) {
    const category = line.product?.category?.id ?? null
    idsByCategory.set(category, [...(idsByCategory.get(category) ?? []), line.id])
  }
  const created = [...idsByCategory.values()].map((ids) => createGroup(common, ids))
  return withinLimit([...moveOut(groups, targets.map((line) => line.id)), ...created], groups)
}

/** "Duplica gruppo": same four common fields as the source, blank title, no lines; placed right after it. */
export function duplicateGroup(groups: ContractProgramGroup[], index: number): ContractProgramGroup[] {
  const source = groups[index]
  if (!source) {
    return groups
  }
  const copy = createGroup(source)
  return withinLimit([...groups.slice(0, index + 1), copy, ...groups.slice(index + 1)], groups)
}

/** "Applica a tutti i gruppi": overwrites the four common fields everywhere, never titles or lines (D-7). */
export function applyCommonToAll(groups: ContractProgramGroup[], common: ContractProgramCommon): ContractProgramGroup[] {
  return groups.map((group) => ({
    ...group,
    type: common.type,
    start_date: common.start_date,
    supervisor_ids: [...common.supervisor_ids],
    task_template_id: common.task_template_id,
  }))
}

/** Distinct product names of the group's lines in offer order: what the automatic title is built from (D-2). */
export function automaticTitleProducts(group: ContractProgramGroup, lines: ContractProgrammableLine[]): string[] {
  const inGroup = new Set(group.quote_line_ids)
  const names = lines
    .filter((line) => inGroup.has(line.id))
    .map((line) => line.product?.name)
    .filter((name): name is string => Boolean(name))
  return [...new Set(names)]
}

/** The server-assigned code is unknown until save: the preview shows this stand-in (AC-024). */
const AUTOMATIC_CODE_PLACEHOLDER = 'COM-…'

/** `COM-… - A + B`: what the automatic title will look like, from the group's distinct product names. */
export function automaticTitlePreview(products: string[]): string {
  return products.length > 0 ? `${AUTOMATIC_CODE_PLACEHOLDER} - ${products.join(' + ')}` : AUTOMATIC_CODE_PLACEHOLDER
}

/** What names a group in lists: the typed title, else the automatic preview. */
export function groupDisplayTitle(group: ContractProgramGroup, lines: ContractProgrammableLine[]): string {
  return group.title.trim() || automaticTitlePreview(automaticTitleProducts(group, lines))
}
