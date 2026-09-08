import type { ImportGlobalFieldDescriptor } from '@/features/imports/wizard/types'

/** Where a global field's value comes from: the run's own control, or a mapped file column. */
export type GlobalFieldSource = 'run' | 'file'

/** A global field the backend allows to be fed from a file column (spec 0108 D-2). */
export type FileBackedGlobalField = ImportGlobalFieldDescriptor & { required_unless_mapped: string }

/**
 * The global fields that can be fed from a file column instead of the run: the
 * backend flags them with `required_unless_mapped`, so nothing about
 * "campaign" is hardcoded on this side either.
 */
export function fileBackedGlobalFields(globalFields: ImportGlobalFieldDescriptor[]): FileBackedGlobalField[] {
  return globalFields.filter(
    (field): field is FileBackedGlobalField =>
      typeof field.required_unless_mapped === 'string' && field.required_unless_mapped.length > 0,
  )
}

/** Global field ids whose value is currently mapped from a file column. */
export function globalFieldIdsFromFile(
  globalFields: ImportGlobalFieldDescriptor[],
  mappedTargets: string[],
): string[] {
  return fileBackedGlobalFields(globalFields)
    .filter((field) => mappedTargets.includes(field.required_unless_mapped))
    .map((field) => field.id)
}
