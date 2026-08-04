/* eslint-disable react-refresh/only-export-components -- context module: the provider and its paired `useResourcePermissions()` hook are one cohesive unit (spec 0004), not a route/page component */
import { createContext, useContext, type ReactNode } from 'react'
import type {
  FieldPermission,
  ResourceAbility,
  ResourcePermissions,
} from '@/features/authorization/types'

/**
 * Graceful fallback for a field/action absent from the loaded metadata (or no
 * metadata at all): visible + editable, never a crash. The backend is the
 * source of truth, but a genuinely missing entry must not lock the UI.
 */
const FALLBACK_FIELD_PERMISSION: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

const ResourcePermissionsContext = createContext<ResourcePermissions | null>(null)

interface ResourcePermissionsProviderProps {
  /** The resolved `permissions` block (create-context meta or instance detail). */
  permissions: ResourcePermissions | null | undefined
  children: ReactNode
}

/** Scopes `useResourcePermissions()` to the given resource's metadata block. */
export function ResourcePermissionsProvider({
  permissions,
  children,
}: ResourcePermissionsProviderProps) {
  return (
    <ResourcePermissionsContext.Provider value={permissions ?? null}>
      {children}
    </ResourcePermissionsContext.Provider>
  )
}

export interface UseResourcePermissionsResult {
  /** The field's authorization descriptor, or the graceful fallback when absent. */
  field(name: string): FieldPermission
  /** Whether a named domain action is currently available. */
  canAction(name: string): boolean
  /** Whether the given resource-level ability is currently available. */
  canResource(ability: ResourceAbility): boolean
  /**
   * Whether the actor may propose a change on this field via a field-change
   * request (spec 0078) instead of writing it directly. Unlike the other
   * accessors, this defaults to `false` when the block is missing: a locked
   * field must not offer a proposal affordance the backend never advertised.
   */
  canRequestChange(field: string): boolean
}

/**
 * Reads the current resource's authorization metadata (spec 0004). Never
 * throws: outside a `ResourcePermissionsProvider`, or for any field/action
 * missing from the loaded block, it resolves to the permissive fallback so a
 * form never crashes on incomplete metadata.
 */
export function useResourcePermissions(): UseResourcePermissionsResult {
  const permissions = useContext(ResourcePermissionsContext)

  return {
    field: (name) => permissions?.fields[name] ?? FALLBACK_FIELD_PERMISSION,
    canAction: (name) => permissions?.actions[name] ?? true,
    canResource: (ability) => permissions?.resource[ability] ?? true,
    canRequestChange: (field) => permissions?.change_requestable_fields?.includes(field) ?? false,
  }
}
