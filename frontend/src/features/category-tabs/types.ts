/** Which categories the strip lists (spec 0337): every one, or only the root (first-level) ones. */
export type CategoryTabLevel = 'all' | 'root'

/** One tab of the category strip: a Product Category and how many grid rows fall under it. */
export interface CategoryTabItem {
  id: number
  name: string
  count: number
}

/**
 * Which grid a strip serves: `key` roots the query keys and the localStorage
 * entry, `apiBasePath` is the axios path prefix of its two endpoints.
 */
export interface CategoryTabSource {
  key: string
  apiBasePath: string
}

/** The actor's favourite categories on a grid's tab strip (spec 0184), saved on the account. */
export interface CategoryTabPreferences {
  favorite_category_ids: number[]
  show_only_favorites: boolean
  /** True while nothing was saved: the values are the ones proposed from the actor's competence (spec 0193). */
  is_default: boolean
}

/** What the PUT sends: the two saved fields, never `is_default` (spec 0193). */
export type CategoryTabPreferencesPayload = Omit<CategoryTabPreferences, 'is_default'>
