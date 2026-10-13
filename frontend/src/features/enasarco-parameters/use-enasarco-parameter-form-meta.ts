import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import { ENASARCO_PARAMETERS_DOMAIN, type EnasarcoParameterFormMode } from '@/features/enasarco-parameters/types'

/** Metadata-loading state driving what `EnasarcoParameterForm` renders. */
export type EnasarcoParameterFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form: edit mode seeds them
 * from the loaded detail, create mode fetches `GET /meta/enasarco-parameters` once.
 */
export function useEnasarcoParameterFormMeta(mode: EnasarcoParameterFormMode): EnasarcoParameterFormMetaState {
  const metaQuery = useResourceMeta(ENASARCO_PARAMETERS_DOMAIN, mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.parameter.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
