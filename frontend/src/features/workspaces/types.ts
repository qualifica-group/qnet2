/** An app the current user may enter (`GET /workspaces/available`). */
export interface AvailableWorkspace {
  slug: string
  name: string
  icon: string | null
  /** Palette token (see BADGE_COLOR_TOKENS), or null for the neutral text colour. */
  color: string | null
  description: string | null
  /** Route of the first visible menu entry of the app, e.g. "/absences". */
  home_route: string
}

export interface AvailableWorkspaces {
  workspaces: AvailableWorkspace[]
  /** True for a super-admin, or for everyone while no app exists (spec 0317 D-5). */
  full_view_available: boolean
}

/** An app as configured by the super-admin (`WorkspaceResource`). */
export interface Workspace {
  id: number
  slug: string
  name: string
  icon: string | null
  color: string | null
  description: string | null
  sort_order: number
  navigation_keys: string[]
}

export interface WorkspacePayload {
  slug: string
  name: string
  icon: string | null
  color: string | null
  description: string | null
  sort_order: number | null
  navigation_keys: string[]
}

/** A menu entry the super-admin can assign to an app. */
export interface NavigationCatalogueEntry {
  key: string
  /** i18n key, like `NavigationItem.label`. */
  label: string
  icon: string | null
  route: string
  /** i18n keys of the ancestors (section, group), outermost first. */
  path: string[]
}
