import { queryOptions } from '@tanstack/react-query'

import { api } from './api'

export type AppConfiguration = {
  app_name?: string | null
  app_logo?: string | null
  app_light_logo?: string | null
  app_mini_logo?: string | null
  app_favicon?: string | null
  theme_color?: string | null
  root_colors?: string | null | Record<string, string>
  homepage_rail_item_limit?: number | string | null
  mobile_app?: {
    playstore_url?: string | null
    appstore_url?: string | null
  } | null
}

export const appConfigurationQuery = queryOptions({
  queryKey: ['app-configuration'],
  queryFn: () => api.get<AppConfiguration>('/api/v3/app-configuration'),
  staleTime: 5 * 60_000,
})
