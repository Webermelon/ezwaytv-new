import { CastCrewPage } from '@/modules/castcrew/CastCrewPage'
import { CastCrewDetailPage } from '@/modules/castcrew/CastCrewDetailPage'
import { DistributionPage } from '@/modules/distribution/DistributionPage'
import { HomePage } from '@/modules/home/HomePage'
import { LiveTvPage } from '@/modules/live-tv/LiveTvPage'
import { MusicPage } from '@/modules/music/MusicPage'
import { MusicUploadPage } from '@/modules/music/MusicUploadPage'
import { OnDemandPage } from '@/modules/ondemand/OnDemandPage'
import { PublicPage } from '@/modules/public/PublicPage'
import { SearchPage } from '@/modules/search/SearchPage'
import { SubscriptionPlanPage } from '@/modules/subscription/SubscriptionPlanPage'
import { VideoDetailPage } from '@/modules/video-detail/VideoDetailPage'
import { VideosPage } from '@/modules/videos/VideosPage'
import { useSpaPath } from '@/lib/spa-router'
import { AppFooter } from '@/components/AppFooter'
import { useAnalyticsPageView } from '@/lib/analytics'

export default function App() {
  const path = useSpaPath()
  const pathname = path.split(/[?#]/)[0] || '/'
  useAnalyticsPageView(path)
  let page

  if (pathname === '/' || pathname === '/spa' || pathname === '/spa/' || pathname === '/react-home') {
    page = <HomePage />
  } else if (pathname.startsWith('/on-demand') || pathname.startsWith('/spa/ondemand') || pathname.startsWith('/react-ondemand')) {
    page = <OnDemandPage />
  } else if (pathname === '/livetv' || pathname.startsWith('/livetv/') || pathname.startsWith('/spa/live-tv')) {
    page = <LiveTvPage />
  } else if (pathname === '/videos' || pathname.startsWith('/videos/category') || pathname.startsWith('/spa/videos') || pathname.startsWith('/react-videos')) {
    page = <VideosPage />
  } else if (pathname === '/search') {
    page = <SearchPage />
  } else if (pathname === '/login' || pathname === '/register' || pathname === '/forget-password') {
    page = <HomePage />
  } else if (pathname === '/distribution') {
    page = <DistributionPage />
  } else if (pathname === '/subscription-plan') {
    page = <SubscriptionPlanPage />
  } else if (pathname === '/music') {
    page = <MusicPage />
  } else if (pathname.startsWith('/upload-your-videoes')) {
    page = <MusicUploadPage />
  } else if (pathname.startsWith('/video-details')) {
    page = <VideoDetailPage />
  } else if (pathname.startsWith('/castcrew-detail')) {
    page = <CastCrewDetailPage />
  } else if (pathname === '/castcrew-list' || pathname.startsWith('/castcrew-list/')) {
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
