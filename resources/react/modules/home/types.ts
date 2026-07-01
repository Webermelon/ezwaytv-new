export type ApiEnvelope<T> = {
  status: boolean
  data?: T
  message?: string
  hasMore?: boolean
}

export type MediaItem = {
  id: number | string
  name: string
  slug?: string
  type?: string
  poster_image?: string
  poster_tv_image?: string
  poster_url?: string
  thumbnail_url?: string
  language_image?: string
  cover_image_url?: string
  avatar_image_url?: string
  profile_image?: string
  duration?: string | null
  access?: string
  has_content_access?: number | boolean | null
  is_premium?: number | boolean | null
  show_premium_badge?: number | boolean | null
  plan_id?: number | string | null
  plan_level?: number | string | null
  required_plan_level?: number | string | null
  required_plan_name?: string | null
  current_plan_level?: number | string | null
  username?: string
  channel_name?: string | null
  channel_username?: string | null
  profile_url?: string
  ondemand_channel_id?: number | string | null
  videos_count?: number
  is_active?: boolean
  is_watch_list?: number | boolean | string | null
  is_in_watchlist?: number | boolean | string | null
  video_url?: string
  video_url_input?: string | null
  video_type?: string
  video_upload_type?: string | null
  trailer_url?: string | null
  trailer_url_type?: string | null
  release_date?: string | null
  imdb_rating?: string | number | null
  description?: string | null
  short_desc?: string | null
  now_playing?: ProgramInfo | null
  next_playing?: ProgramInfo | null
  schedules_url?: string | null
  full_schedule?: ScheduleProgram[]
  suggested_content?: MediaItem[]
  video_qualities?: Array<{
    url_type?: string | null
    url?: string | null
  }>
  details?: {
    name?: string
    slug?: string
    type?: string
    access?: string
    category?: string
    description?: string | null
    thumbnail_image?: string | null
    server_url?: string | null
    is_device_supported?: number | boolean | null
    has_content_access?: number | boolean | null
    required_plan_level?: number | string | null
    required_plan_name?: string | null
  }
}

export type ProgramInfo = {
  title?: string | null
  start_time?: string | null
  end_time?: string | null
  timezone?: string | null
  status?: string | null
  duration_seconds?: number | null
  elapsed_seconds?: number | null
}

export type ScheduleProgram = ProgramInfo & {
  id?: number | string
  start_at?: string | null
  end_at?: string | null
  meta?: Record<string, unknown> | null
}

export type DashboardData = {
  continue_watch?: MediaItem[]
  top_10?: NamedRail
  latest_movie?: NamedRail
  popular_language?: NamedRail
  popular_movie?: NamedRail
  popular_tvshow?: NamedRail
  free_movie?: NamedRail
  personality?: NamedRail
  popular_personality?: NamedRail
  pay_per_view?: MediaItem[]
}

export type NamedRail = {
  name: string
  data?: MediaItem[]
}

export type LiveTvDashboard = {
  slider?: MediaItem[]
  category_data?: Array<{
    id: number | string
    name: string
    channel_data?: MediaItem[]
  }>
}

export type PaginatedData<T> = {
  data?: T[]
  current_page?: number
  last_page?: number
  total?: number
}
