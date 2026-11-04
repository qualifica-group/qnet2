import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import axios from 'axios'
import { useQuery } from '@tanstack/react-query'
import { extractMentionIds } from '@/features/notes/note-rich-text'
import { fetchMentionableUsers } from '@/features/notes/api'
import { notesKeys } from '@/features/notes/query-keys'
import type {
  MentionableUser,
  MentionOutsideMeta,
  MentionOutsideRole,
  MentionScope,
} from '@/features/notes/types'

/** Hoistati: un riferimento nuovo a ogni render romperebbe le dipendenze stabili. */
const NO_USERS: MentionableUser[] = []
const NO_IDS: number[] = []
const NO_MENTION_OUTSIDE: MentionOutsideMeta = { allowed: false, role: null }

/** Page size dell'idratazione: piu' dei 12 Partecipanti massimi, una nota ne cita pochi. */
const HYDRATION_LIMIT = 100

/**
 * Cio' che l'estensione Tiptap deve poter leggere a ogni ricerca senza che
 * l'editor venga ricreato: l'oggetto e' stabile, i valori vivono in ref.
 */
export interface MentionSource {
  getScope: () => MentionScope
  getRole: () => MentionOutsideRole | null
  /** Ricorda le voci appena cercate, cosi' `pick` ritrova i flag di quella scelta. */
  remember: (items: MentionableUser[]) => void
  /** Registra la scelta dal menu: se fuori perimetro entra nel riepilogo. */
  pick: (userId: number) => void
}

interface UseMentionOutsideOptions {
  entityType: string
  entityId: number
  mentionOutside?: MentionOutsideMeta
  /** Id gia' menzionati dalla nota in modifica: i loro flag vanno riletti dal server. */
  existingMentionIds?: number[]
}

/** Messaggio del server su `errors.mentions` di un 422 (commessa piena, fuori perimetro non ammesso). */
export function mentionsErrorMessage(error: unknown): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return null
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  return errors?.mentions?.[0] ?? null
}

/**
 * Stato dell'interruttore "Menziona chiunque" (spec 0353 D-9): lo scope della
 * ricerca @, i flag degli utenti scelti dal menu e il riepilogo di chi verra'
 * aggiunto al record, calcolato dalle menzioni PRESENTI nel body.
 */
export function useMentionOutside({
  entityType,
  entityId,
  mentionOutside = NO_MENTION_OUTSIDE,
  existingMentionIds = NO_IDS,
}: UseMentionOutsideOptions) {
  const { allowed, role } = mentionOutside
  const [enabled, setEnabled] = useState(false)
  const [picked, setPicked] = useState<Record<number, MentionableUser>>({})
  const scopeRef = useRef<MentionScope>('record')
  const roleRef = useRef<MentionOutsideRole | null>(role)
  const seenRef = useRef(new Map<number, MentionableUser>())

  useEffect(() => {
    roleRef.current = role
  }, [role])

  const hydrationIds = allowed ? existingMentionIds : NO_IDS
  const { data: hydrated = NO_USERS } = useQuery({
    queryKey: notesKeys.mentionHydration(entityType, entityId, hydrationIds),
    queryFn: async () =>
      (
        await fetchMentionableUsers({
          entityType,
          entityId,
          ids: hydrationIds,
          limit: HYDRATION_LIMIT,
          scope: 'extended',
        })
      ).items,
    enabled: hydrationIds.length > 0,
  })

  const source = useMemo<MentionSource>(
    () => ({
      getScope: () => scopeRef.current,
      getRole: () => roleRef.current,
      remember: (items) => items.forEach((item) => seenRef.current.set(item.id, item)),
      pick: (userId) => {
        const user = seenRef.current.get(userId)
        if (user && !user.in_record) {
          setPicked((current) => ({ ...current, [user.id]: user }))
        }
      },
    }),
    [],
  )

  const toggle = useCallback(() => {
    const next = scopeRef.current === 'record'
    scopeRef.current = next ? 'extended' : 'record'
    setEnabled(next)
  }, [])

  const reset = useCallback(() => {
    scopeRef.current = 'record'
    setEnabled(false)
    setPicked({})
  }, [])

  /** Fuori perimetro presenti nel body, nell'ordine in cui compaiono. */
  const outsidersIn = useCallback(
    (body: string | null): MentionableUser[] => {
      const known = new Map<number, MentionableUser>()
      hydrated.forEach((user) => known.set(user.id, user))
      Object.values(picked).forEach((user) => known.set(user.id, user))
      return extractMentionIds(body).flatMap((id) => {
        const user = known.get(id)
        return user && !user.in_record ? [user] : []
      })
    },
    [hydrated, picked],
  )

  return { allowed, role, enabled, toggle, reset, source, outsidersIn }
}
