import { CastCrewPage } from '@/modules/castcrew/CastCrewPage'
import { CastCrewDetailPage } from '@/modules/castcrew/CastCrewDetailPage'
import { AccountSettingPage } from '@/modules/account/AccountSettingPage'
import { ChangePasswordPage } from '@/modules/account/ChangePasswordPage'
import { ProfileDetailsPage } from '@/modules/account/ProfileDetailsPage'
import { PaymentHistoryPage } from '@/modules/account/PaymentHistoryPage'
import { OrdersPage } from '@/modules/account/OrdersPage'
import { WatchlistPage } from '@/modules/account/WatchlistPage'
import { DistributionPage } from '@/modules/distribution/DistributionPage'
import { HomePage } from '@/modules/home/HomePage'
import { LiveTvPage } from '@/modules/live-tv/LiveTvPage'
import { MusicPage } from '@/modules/music/MusicPage'
import { MusicUploadPage } from '@/modules/music/MusicUploadPage'
import { OnDemandPage } from '@/modules/ondemand/OnDemandPage'
import { ManageProfilePage } from '@/modules/profile/ManageProfilePage'
import { PublicPage } from '@/modules/public/PublicPage'
import { SearchPage } from '@/modules/search/SearchPage'
import { SubscriptionPlanPage } from '@/modules/subscription/SubscriptionPlanPage'
import { VideoDetailPage, VideoEmbedPage } from '@/modules/video-detail/VideoDetailPage'
import { VideosPage } from '@/modules/videos/VideosPage'
import { useSpaPath } from '@/lib/spa-router'
import { AppFooter } from '@/components/AppFooter'
import { AuthPage } from '@/modules/auth/AuthPage'
import { useAnalyticsPageView } from '@/lib/analytics'
import { isIosRestrictedPath, isNativeIosApp } from '@/lib/native-platform'

export default function App() {
  const path = useSpaPath()
  const pathname = path.split(/[?#]/)[0] || '/'
  useAnalyticsPageView(path)
  let page

  if (isNativeIosApp() && isIosRestrictedPath(pathname)) {
    page = <HomePage />
  } else if (pathname === '/' || pathname === '/spa' || pathname === '/spa/' || pathname === '/react-home') {
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
    page = <AuthPage />
  } else if (pathname === '/distribution') {
    page = <DistributionPage />
  } else if (pathname === '/subscription-plan') {
    page = <SubscriptionPlanPage />
  } else if (pathname === '/payment-history') {
    page = <PaymentHistoryPage />
  } else if (pathname === '/orders') {
    page = <OrdersPage />
  } else if (pathname === '/account-setting') {
    page = <AccountSettingPage />
  } else if (pathname === '/watch-list') {
    page = <WatchlistPage />
  } else if (pathname === '/update-profile') {
    page = <ProfileDetailsPage />
  } else if (pathname === '/change-password') {
    page = <ChangePasswordPage />
  } else if (pathname === '/manage-profile') {
    page = <ManageProfilePage />
  } else if (pathname === '/music') {
    page = <MusicPage />
  } else if (pathname.startsWith('/upload-your-videoes')) {
    page = <MusicUploadPage />
  } else if (pathname.startsWith('/video-embed')) {
    page = <VideoEmbedPage />
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
      {pathname.startsWith('/video-embed') ? null : <AppFooter />}
    </>
  )
}
