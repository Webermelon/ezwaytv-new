import { CheckCircle2, CircleDashed, Database, FileJson2, GitBranch, ServerCog } from 'lucide-react'

import { Badge } from '@/components/ui/badge'

const milestones = [
  {
    label: 'Route discovery',
    status: 'Verified',
    detail: '205 API routes boot cleanly',
    icon: ServerCog,
  },
  {
    label: 'Database audit',
    status: 'Verified',
    detail: '106 tables, no migrations run',
    icon: Database,
  },
  {
    label: 'API contract',
    status: 'Started',
    detail: 'Genres endpoint mapped first',
    icon: FileJson2,
  },
  {
    label: 'Module rollout',
    status: 'Pilot',
    detail: 'Genres panel reads existing API',
    icon: GitBranch,
  },
]

const nextModules = [
  'Home dashboard',
  'Movie list',
  'TV show list',
  'Live TV',
  'Watchlist',
  'Subscriptions',
]

export function MigrationDashboard() {
  return (
    <section className="rounded-md border border-white/10 bg-card/80 p-6 shadow-xl">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h2 className="text-lg font-semibold">Migration control panel</h2>
          <p className="mt-1 text-sm text-muted-foreground">Local-only preview for tracking the React rollout.</p>
        </div>
        <Badge variant="success">
          <CheckCircle2 className="mr-1 h-3.5 w-3.5" />
          Foundation active
        </Badge>
      </div>

      <div className="mt-6 grid gap-3 md:grid-cols-2">
        {milestones.map((item) => {
          const Icon = item.icon

          return (
            <article key={item.label} className="rounded-md border border-white/10 bg-background p-4">
              <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  <span className="flex h-9 w-9 items-center justify-center rounded-sm bg-primary/15 text-primary">
                    <Icon className="h-4 w-4" />
                  </span>
                  <div>
                    <p className="text-sm font-medium">{item.label}</p>
                    <p className="mt-0.5 text-xs text-muted-foreground">{item.detail}</p>
                  </div>
                </div>
                <Badge variant={item.status === 'Verified' ? 'success' : 'warning'}>{item.status}</Badge>
              </div>
            </article>
          )
        })}
      </div>

      <div className="mt-6 border-t border-white/10 pt-5">
        <div className="flex items-center gap-2 text-sm font-medium">
          <CircleDashed className="h-4 w-4 text-primary" />
          Next module candidates
        </div>
        <div className="mt-3 flex flex-wrap gap-2">
          {nextModules.map((module) => (
            <Badge key={module} variant="outline">
              {module}
            </Badge>
          ))}
        </div>
      </div>
    </section>
  )
}
