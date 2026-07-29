import * as React from "react"
import type { LucideIcon } from "lucide-react"

export type ConfirmTone =
  | "default"
  | "destructive"
  | "success"
  | "warning"
  | "info"

export interface ConfirmOptions {
  title?: string
  description?: React.ReactNode
  confirmLabel?: string
  cancelLabel?: string
  tone?: ConfirmTone
  /** Override the tone's default icon. */
  icon?: LucideIcon
}

/** Imperative confirm: resolves true on confirm, false on cancel/dismiss. */
export type ConfirmFn = (options?: ConfirmOptions) => Promise<boolean>

export const ConfirmContext = React.createContext<ConfirmFn | null>(null)

/** Read the app confirmation service without requiring it for render-only flows. */
export function useOptionalConfirm(): ConfirmFn | null {
  return React.useContext(ConfirmContext)
}

/** Imperative replacement for `window.confirm`, backed by the wow dialog. */
export function useConfirm(): ConfirmFn {
  const confirm = useOptionalConfirm()
  if (!confirm) {
    throw new Error("useConfirm must be used within a ConfirmDialogProvider")
  }
  return confirm
}
