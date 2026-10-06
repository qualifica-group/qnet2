import { createContext, useContext, useEffect } from 'react'

/** Asked before the module Sheet closes from its own chrome (X, overlay, Esc): `true` lets it close. */
export type SheetCloseGuard = () => Promise<boolean>

interface SheetCloseGuardRegistry {
  setCloseGuard: (guard: SheetCloseGuard | null) => void
}

/**
 * Provided by `useModuleOpener` around the screen its Sheet mounts, so a
 * screen can veto the close (spec 0195: the task create form asks before
 * dropping a draft). Absent outside a module Sheet.
 */
export const SheetCloseGuardContext = createContext<SheetCloseGuardRegistry | null>(null)

/** Registers `guard` on the enclosing module Sheet for as long as the caller is mounted; a no-op elsewhere. */
export function useSheetCloseGuard(guard: SheetCloseGuard): void {
  const registry = useContext(SheetCloseGuardContext)

  useEffect(() => {
    if (!registry) {
      return
    }
    registry.setCloseGuard(guard)
    return () => registry.setCloseGuard(null)
  }, [registry, guard])
}
