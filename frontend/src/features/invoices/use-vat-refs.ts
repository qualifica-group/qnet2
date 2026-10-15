import { useCallback, useState } from 'react'
import type { VatRateRef } from '@/features/invoices/types'

/** Percentage per VAT id: the pickers expose ids, the amounts preview needs rates. */
export type VatRefs = Record<number, VatRateRef>

/** UI cache of the VAT rates met so far (the document's, then each one picked), shared by editors and records. */
export function useVatRefs(initial: VatRefs) {
  const [vatRefs, setVatRefs] = useState<VatRefs>(initial)
  const rememberVat = useCallback((ref: VatRateRef | null) => {
    if (ref === null) return
    setVatRefs((current) => (current[ref.id] ? current : { ...current, [ref.id]: ref }))
  }, [])

  return { vatRefs, rememberVat }
}
