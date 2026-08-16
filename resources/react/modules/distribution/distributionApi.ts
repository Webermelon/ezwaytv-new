import { api } from '@/lib/api'

export type DistributionNetwork = {
  name: string
  slug?: string
  image?: string | null
  description?: string | null
  tag?: string | null
  href?: string | null
  showOnboardingCta?: boolean
}

type DistributionResponse = {
  networks?: DistributionNetwork[]
}

export async function loadDistribution() {
  const response = await api.get<DistributionResponse>('/api/distribution')

  return {
    networks: (response.networks ?? []).map((item) => ({
      ...item,
      tag: cleanText(item.tag),
      description: cleanText(item.description),
      image: resolveAssetUrl(item.image),
    })),
  }
}

function resolveAssetUrl(value?: string | null) {
  if (!value) return null
  if (/^https?:\/\//i.test(value)) return value
  if (value.startsWith('/')) return value

  return `/${value}`
}

function cleanText(value?: string | null) {
  if (!value) return value

  return value
    .replaceAll('Â·', '-')
    .replaceAll('â€™', "'")
    .replaceAll('â€”', '-')
}
