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
import { AppFooter } from '@/components/AppFooter'
import { useAnalyticsPageView } from '@/lib/analytics'

export default function App() {
  const path = useSpaPath()
  useAnalyticsPageView(path)
  let page

  if (path === '/' || path === '/spa' || path === '/spa/' || path === '/react-home') {
    page = <HomePage />
  } else if (path.startsWith('/on-demand') || path.startsWith('/spa/ondemand') || path.startsWith('/react-ondemand')) {
    page = <OnDemandPage />
  } else if (path === '/livetv' || path.startsWith('/livetv/') || path.startsWith('/spa/live-tv')) {
    page = <LiveTvPage />
  } else if (path === '/videos' || path.startsWith('/videos/category') || path.startsWith('/spa/videos') || path.startsWith('/react-videos')) {
    page = <VideosPage />
  } else if (path === '/search' || path.startsWith('/search?')) {
    page = <SearchPage />
  } else if (path === '/distribution') {
    page = <DistributionPage />
  } else if (path.startsWith('/video-details')) {
    page = <VideoDetailPage />
  } else if (path.startsWith('/castcrew-detail')) {
    page = <CastCrewDetailPage />
  } else if (path === '/castcrew-list' || path.startsWith('/castcrew-list/')) {
    page = <CastCrewPage />
  } else {
    page = <PublicPage />
  }

  return (
    <>
      {page}
      <AppFooter />
    </>
  )
}
