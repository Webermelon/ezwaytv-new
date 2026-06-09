import { CastCrewPage } from '@/modules/castcrew/CastCrewPage'
import { CastCrewDetailPage } from '@/modules/castcrew/CastCrewDetailPage'
import { DistributionPage } from '@/modules/distribution/DistributionPage'
import { HomePage } from '@/modules/home/HomePage'
import { LiveTvPage } from '@/modules/live-tv/LiveTvPage'
import { OnDemandPage } from '@/modules/ondemand/OnDemandPage'
import { PublicPage } from '@/modules/public/PublicPage'
import { SearchPage } from '@/modules/search/SearchPage'
import { VideoDetailPage } from '@/modules/video-detail/VideoDetailPage'
import { VideosPage } from '@/modules/videos/VideosPage'
import { useSpaPath } from '@/lib/spa-router'

export default function App() {
  const path = useSpaPath()

  if (path === '/' || path === '/spa' || path === '/spa/' || path === '/react-home') {
    return <HomePage />
  }

  if (path.startsWith('/on-demand') || path.startsWith('/spa/ondemand') || path.startsWith('/react-ondemand')) {
    return <OnDemandPage />
  }

  if (path === '/livetv' || path.startsWith('/livetv/') || path.startsWith('/spa/live-tv')) {
    return <LiveTvPage />
  }

  if (path === '/videos' || path.startsWith('/videos/category') || path.startsWith('/spa/videos') || path.startsWith('/react-videos')) {
    return <VideosPage />
  }

  if (path === '/search' || path.startsWith('/search?')) {
    return <SearchPage />
  }

  if (path === '/distribution') {
    return <DistributionPage />
  }

  if (path.startsWith('/video-details')) {
    return <VideoDetailPage />
  }

  if (path.startsWith('/castcrew-detail')) {
    return <CastCrewDetailPage />
  }

  if (path === '/castcrew-list' || path.startsWith('/castcrew-list/')) {
    return <CastCrewPage />
  }

  return <PublicPage />
}
