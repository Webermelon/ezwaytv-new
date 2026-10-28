const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const ts = require('typescript')

function clientWith(responses) {
  const calls = []
  const meta = { content: 'stale-token' }
  const exports = {}
  const source = fs.readFileSync(path.resolve(__dirname, '../../resources/react/lib/api.ts'), 'utf8')
  const compiled = ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.CommonJS } }).outputText
  vm.runInNewContext(compiled, {
    exports, Headers, FormData, URL,
    document: { querySelector: () => meta },
    fetch: async (url, options) => {
      calls.push({ url, ...options, headers: new Headers(options.headers) })
      const response = responses.shift()
      assert.ok(response, 'Unexpected extra request')
      return response
    },
  })
  return { client: new exports.ApiClient(), calls, meta }
}

const json = (body, status = 200) => new Response(JSON.stringify(body), { status })

test('stale CSRF refreshes once and retries the original save with the new token', async () => {
  const { client, calls, meta } = clientWith([
    json({ message: 'Expired' }, 419), json({ csrf_token: 'fresh-token' }), json({ status: true }),
  ])
  const result = await client.post('/account/watchlist/save', { entertainment_id: 119, type: 'movie' })
  assert.equal(result.status, true)
  assert.equal(calls.length, 3)
  assert.equal(calls[1].url, '/auth/csrf-token')
  assert.equal(calls[0].body, calls[2].body)
  assert.equal(calls[0].headers.get('X-CSRF-TOKEN'), 'stale-token')
  assert.equal(calls[2].headers.get('X-CSRF-TOKEN'), 'fresh-token')
  assert.equal(meta.content, 'fresh-token')
})

test('repeated CSRF failure stops after one retry and shows the server message', async () => {
  const { client, calls } = clientWith([
    json({}, 419), json({ csrf_token: 'fresh-token' }), json({ message: 'Please log in again.' }, 419),
  ])
  await assert.rejects(client.post('/account/watchlist/save', {}), (error) => error.status === 419 && error.message === 'Please log in again.')
  assert.equal(calls.length, 3)
})

test('failed refresh does not replay the mutation', async () => {
  const { client, calls } = clientWith([json({ message: 'Session expired.' }, 419), json({}, 503)])
  await assert.rejects(client.post('/account/watchlist/save', {}), /Session expired\./)
  assert.equal(calls.length, 2)
})

test('signed-out and server errors are not retried', async () => {
  for (const status of [401, 500]) {
    const { client, calls } = clientWith([json({ message: 'Save was rejected.' }, status)])
    await assert.rejects(client.post('/account/watchlist/save', {}), /Save was rejected\./)
    assert.equal(calls.length, 1)
  }
})

test('a followed login redirect is not treated as successful saving', async () => {
  const response = new Response('<html>Sign in</html>')
  Object.defineProperties(response, { redirected: { value: true }, url: { value: 'https://ezway.tv/login-page' } })
  const { client } = clientWith([response])
  await assert.rejects(client.post('/account/watchlist/save', {}), (error) => error.status === 401)
})

test('HTTP errors without a message never show unknown status', async () => {
  const { client } = clientWith([json({}, 422)])
  await assert.rejects(client.post('/account/watchlist/save', {}), (error) => error.status === 422 && !error.message.includes('unknown status'))
})
