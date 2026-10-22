import { useCallback, useState } from 'react'

/** Selected people across pages of the overview (ids survive paging and filtering until cleared). */
export function usePersonSelection() {
  const [selected, setSelected] = useState<ReadonlySet<number>>(new Set())

  const toggle = useCallback((id: number) => {
    setSelected((current) => {
      const next = new Set(current)
      if (!next.delete(id)) next.add(id)
      return next
    })
  }, [])

  const setMany = useCallback((ids: number[], checked: boolean) => {
    setSelected((current) => {
      const next = new Set(current)
      for (const id of ids) {
        if (checked) next.add(id)
        else next.delete(id)
      }
      return next
    })
  }, [])

  const clear = useCallback(() => setSelected(new Set()), [])

  return { selected, toggle, setMany, clear }
}
