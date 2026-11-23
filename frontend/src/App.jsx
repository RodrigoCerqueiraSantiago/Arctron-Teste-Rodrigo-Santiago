import { useCallback, useEffect, useRef, useState } from 'react'

const money = (cents) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(cents || 0) / 100)
const date = (value) => new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(value))
const initialFilters = { reference: '', customer: '', date_from: '', date_to: '', amount_min: '', amount_max: '' }
const purchaseColumns = [['reference', 'Referência'], ['customer', 'Cliente'], ['date', 'Data (UTC)'], ['discount', 'Desconto'], ['amount', 'Valor']]
const panel = 'rounded-2xl border border-slate-200/80 bg-white shadow-[0_8px_30px_rgba(15,35,70,0.04)]'
const secondaryButton = 'rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 transition hover:border-blue-300 hover:text-navy disabled:opacity-40'

async function getJson(url, signal) {
  const response = await fetch(url, { signal, headers: { Accept: 'application/json' } })
  if (!response.ok) {
    const body = await response.json().catch(() => ({}))
    const error = new Error(body.message || `Erro ao consultar a API (${response.status}).`)
    error.status = response.status
    throw error
  }
  return response.json()
}

function Icon({ name, className = 'h-5 w-5' }) {
  const paths = {
    grid: <><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></>,
    bag: <><path d="M5 8h14l1 12H4L5 8Z" /><path d="M9 9V6a3 3 0 0 1 6 0v3" /></>,
    chart: <><path d="M4 20h16" /><path d="M7 16v-4M12 16V7M17 16v-6" /></>,
    users: <><circle cx="9" cy="8" r="3" /><path d="M3 19a6 6 0 0 1 12 0M16 5a3 3 0 0 1 0 6M17 14a5 5 0 0 1 4 5" /></>,
    box: <><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" /><path d="m4 7.5 8 4.5 8-4.5M12 12v9" /></>,
    search: <><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></>,
    arrow: <><path d="M7 17 17 7M7 7h10v10" /></>,
    calendar: <><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M8 3v4M16 3v4M3 10h18" /></>,
  }
  return <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{paths[name]}</svg>
}

function Ranking({ title, description, items, icon }) {
  const [showAll, setShowAll] = useState(false)
  const maximum = items.reduce((max, item) => Math.max(max, Number(item.total_cents)), 0)
  const visible = showAll ? items : items.slice(0, 5)
  return <section className={`${panel} p-6`}>
    <div className="flex items-start justify-between gap-3">
      <div className="flex gap-3"><span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-navy-light"><Icon name={icon} /></span><div><h2 className="font-bold text-navy">{title}</h2><p className="mt-1 text-xs text-slate-500">{description}</p></div></div>
      <span className="whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500">{items.length} registros</span>
    </div>
    {items.length === 0 ? <p className="py-12 text-center text-sm text-slate-400">Nenhum dado disponível ainda.</p> :
      <div className="mt-7 space-y-5">{visible.map((item, index) => <div key={item.id}>
        <div className="flex items-center justify-between gap-3 text-sm"><span className="min-w-0 truncate font-semibold text-slate-700"><b className="mr-3 text-xs text-slate-400">{String(index + 1).padStart(2, '0')}</b>{item.name}</span><strong className="whitespace-nowrap text-navy">{money(item.total_cents)}</strong></div>
        <div className="mt-2 h-1.5 rounded-full bg-slate-100"><div className="h-full rounded-full bg-pitch" style={{ width: `${maximum ? Number(item.total_cents) / maximum * 100 : 0}%` }} /></div>
        <div className="mt-2 flex justify-between text-[11px] text-slate-400"><span>{item.purchase_count} compra(s)</span><span>Média {money(item.average_cents)}</span></div>
      </div>)}</div>}
    {items.length > 5 && <button className="mt-5 text-xs font-bold text-navy-light hover:underline" type="button" onClick={() => setShowAll(!showAll)} aria-expanded={showAll}>{showAll ? 'Mostrar menos' : `Ver todos os ${items.length}`}</button>}
  </section>
}

function App() {
  const [user, setUser] = useState(null)
  const [token, setToken] = useState('')
  const [checking, setChecking] = useState(true)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const sessionRequest = useRef(null)

  const refreshSession = useCallback(() => {
    if (sessionRequest.current) return sessionRequest.current
    sessionRequest.current = (async () => {
      setChecking(true)
      try {
        const csrf = await getJson('/api/csrf')
        setToken(csrf.token)
        try {
          const session = await getJson('/api/me')
          setUser(session.user)
        } catch (sessionError) {
          if (sessionError.status !== 401) throw sessionError
          setUser(null)
        }
      } catch (sessionError) {
        setUser(null)
        setError(sessionError.message)
      } finally {
        setChecking(false)
        sessionRequest.current = null
      }
    })()
    return sessionRequest.current
  }, [])

  useEffect(() => { refreshSession() }, [refreshSession])

  async function login(event) {
    event.preventDefault()
    setBusy(true)
    setError('')
    const form = new FormData(event.currentTarget)
    try {
      const csrf = await getJson('/api/csrf')
      const response = await fetch('/api/login', {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.token },
        body: JSON.stringify({ email: form.get('email').trim().toLowerCase(), password: form.get('password') }),
      })
      if (!response.ok) {
        if (response.status === 422) throw new Error('E-mail ou senha inválidos.')
        if (response.status === 429) throw new Error('Muitas tentativas. Aguarde um minuto e tente novamente.')
        throw new Error(`Não foi possível entrar (${response.status}).`)
      }
      const result = await response.json()
      setToken(result.token)
      setUser(result.user)
    } catch (loginError) {
      setError(loginError.message)
      if (!token) await refreshSession()
    } finally {
      setBusy(false)
    }
  }

  async function logout() {
    setBusy(true)
    setError('')
    try {
      const csrf = await getJson('/api/csrf')
      const response = await fetch('/api/logout', { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf.token } })
      if (!response.ok) throw new Error(`Não foi possível sair (${response.status}).`)
      setUser(null)
      await refreshSession()
    } catch (logoutError) {
      setError(logoutError.message)
    } finally {
      setBusy(false)
    }
  }

  const sessionExpired = useCallback(() => {
    setError('Sua sessão expirou. Entre novamente.')
    refreshSession()
  }, [refreshSession])

  if (checking) return <p className="p-8 text-sm text-slate-500">Verificando sessão...</p>
  if (user) return <Dashboard user={user} onLogout={logout} onSessionExpired={sessionExpired} busy={busy} authError={error} />

  return <main className="grid min-h-screen place-items-center bg-slate-100 p-5">
    <form onSubmit={login} className={`${panel} w-full max-w-sm p-8`}>
      <div className="mb-6"><span className="text-xs font-bold tracking-widest text-pitch">ARCTRON ANALYTICS</span><h1 className="mt-2 text-2xl font-extrabold text-navy">Acessar dashboard</h1><p className="mt-2 text-sm text-slate-500">Entre com seu usuário e senha.</p></div>
      {error && <p className="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">{error}</p>}
      <label className="block text-sm font-semibold text-slate-700">E-mail<input className="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2" name="email" type="email" autoComplete="username" required /></label>
      <label className="mt-4 block text-sm font-semibold text-slate-700">Senha<input className="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2" name="password" type="password" autoComplete="current-password" required /></label>
      <button className="mt-6 w-full rounded-lg bg-navy px-4 py-3 text-sm font-bold text-white disabled:opacity-40" type="submit" disabled={busy || !token}>Entrar</button>
      {!token && <button className="mt-3 text-sm text-navy-light underline" type="button" onClick={refreshSession}>Tentar novamente</button>}
    </form>
  </main>
}

function Dashboard({ user, onLogout, onSessionExpired, busy, authError }) {
  const [dashboard, setDashboard] = useState(null)
  const [filters, setFilters] = useState(initialFilters)
  const [query, setQuery] = useState(initialFilters)
  const [page, setPage] = useState(1)
  const [sort, setSort] = useState({ sort_by: 'date', sort_direction: 'desc' })
  const [purchases, setPurchases] = useState(null)
  const [dashboardError, setDashboardError] = useState('')
  const [listError, setListError] = useState('')
  const [loading, setLoading] = useState(true)
  const [expanded, setExpanded] = useState(null)
  const [refreshKey, setRefreshKey] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    getJson('/api/dashboard', controller.signal).then(setDashboard).catch((error) => {
      if (error.status === 401) onSessionExpired()
      else if (error.name !== 'AbortError') setDashboardError(error.message)
    })
    return () => controller.abort()
  }, [onSessionExpired, refreshKey])

  useEffect(() => {
    const controller = new AbortController()
    const params = new URLSearchParams(Object.entries({ ...query, ...sort, page }).filter(([, value]) => value !== ''))
    getJson(`/api/purchases?${params}`, controller.signal).then(setPurchases).catch((error) => {
      if (error.status === 401) onSessionExpired()
      else if (error.name !== 'AbortError') setListError(error.message)
    }).finally(() => { if (!controller.signal.aborted) setLoading(false) })
    return () => controller.abort()
  }, [query, sort, page, onSessionExpired, refreshKey])

  const summary = dashboard?.summary
  const cards = [
    { label: 'Valor total de compras', value: money(summary?.purchase_total_cents), detail: 'Total acumulado no período', icon: 'chart', featured: true },
    { label: 'Compras', value: Number(summary?.purchase_count || 0).toLocaleString('pt-BR'), detail: 'Transações registradas', icon: 'bag' },
    { label: 'Ticket médio', value: money(summary?.purchase_average_cents), detail: 'Valor médio por compra', icon: 'arrow' },
    { label: 'Descontos', value: money(summary?.discount_total_cents), detail: `Média de ${money(summary?.discount_average_cents)}`, icon: 'chart' },
    { label: 'Total dos produtos', value: money(summary?.product_total_cents), detail: `Média de ${money(summary?.product_average_cents)} por compra`, icon: 'box' },
  ]
  function submit(event) {
    event.preventDefault()
    setLoading(true)
    setListError('')
    setPage(1)
    setQuery({ ...filters })
  }
  function reset() {
    setLoading(true)
    setListError('')
    setFilters(initialFilters)
    setQuery({ ...initialFilters })
    setPage(1)
  }
  function changePage(nextPage) {
    setLoading(true)
    setListError('')
    setPage(nextPage)
  }
  function changeSort(column) {
    setLoading(true)
    setListError('')
    setPage(1)
    setExpanded(null)
    setSort((current) => ({
      sort_by: column,
      sort_direction: current.sort_by === column && current.sort_direction === 'asc' ? 'desc' : 'asc',
    }))
  }
  function refreshData() {
    setLoading(true)
    setDashboardError('')
    setListError('')
    setExpanded(null)
    setRefreshKey((current) => current + 1)
  }
  return <div className="min-h-screen md:flex">
    <aside className="bg-navy text-blue-100 md:fixed md:inset-y-0 md:left-0 md:flex md:w-58 md:flex-col">
      <div className="flex items-center justify-between px-6 py-6 md:block">
        <a href="#overview" className="flex items-center gap-3 text-white"><span className="grid h-10 w-10 place-items-center rounded-xl bg-gold text-xl font-black text-navy">A</span><span className="text-lg font-extrabold tracking-tight">Arctron<span className="block text-[10px] font-semibold tracking-[0.2em] text-blue-200 uppercase">Analytics</span></span></a>
        <span className="rounded-full border border-blue-300/25 px-2.5 py-1 text-[10px] text-blue-200 md:hidden">DEMO</span>
      </div>
      <div className="hidden px-4 pt-9 md:block"><p className="mb-3 px-3 text-[10px] font-bold tracking-[0.18em] text-blue-300/60">MENU PRINCIPAL</p>
        <a href="#overview" className="mb-1 flex items-center gap-3 rounded-xl bg-white/10 px-3 py-3 text-sm font-semibold text-white"><Icon name="grid" /> Visão geral <span className="ml-auto h-2 w-2 rounded-full bg-gold" /></a>
        <a href="#purchases" className="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-blue-200 transition hover:bg-white/10 hover:text-white"><Icon name="bag" /> Compras</a>
      </div>
      <div className="mt-auto hidden border-t border-white/10 p-6 md:block"><div className="flex items-center gap-3"><div className="grid h-9 w-9 place-items-center rounded-full bg-blue-400/15 text-sm font-bold text-gold">A</div><div className="text-xs font-bold text-white">Arctron Dashboard<span className="mt-1 block text-[11px] font-normal text-blue-300">Projeto demonstrativo</span></div></div></div>
    </aside>
    <main className="min-w-0 flex-1 md:ml-58" id="overview">
      <header className="flex h-16 items-center justify-between border-b border-slate-200/80 bg-white px-5 sm:px-8 lg:px-11">
        <div className="text-xs text-slate-400">Workspace <span className="mx-2 text-slate-300">/</span> <strong className="text-slate-600">Visão geral</strong></div>
        <div className="flex items-center gap-3"><span className="text-xs text-slate-600">{user.name} · {user.role === 'admin' ? 'Administrador' : 'Leitor'}</span><button className={secondaryButton} type="button" onClick={onLogout} disabled={busy}>Sair</button></div>
      </header>
      <div className="mx-auto max-w-400 px-5 py-8 sm:px-8 lg:px-11 lg:py-10">
        <div className="mb-8 flex flex-wrap items-end justify-between gap-4"><div><div className="flex items-center gap-2 text-[10px] font-bold tracking-[0.18em] text-pitch"><span className="h-1.5 w-5 rounded-full bg-gold" /> INTELIGÊNCIA DE COMPRAS</div><h1 className="mt-3 text-3xl font-extrabold tracking-tight text-navy sm:text-4xl">Visão geral<span className="text-pitch">.</span></h1><p className="mt-2 text-sm text-slate-500">Acompanhe suas transações e tome decisões com mais clareza.</p></div><div className="flex flex-wrap items-center gap-2"><div className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-500"><Icon name="calendar" className="h-4 w-4" /> Todo o período</div><button type="button" className={secondaryButton} onClick={refreshData} disabled={loading}>Atualizar dados</button></div></div>
        {authError && <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">{authError}</div>}
        {dashboardError && <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">Não foi possível carregar o resumo: {dashboardError}</div>}
        {!dashboard && !dashboardError && <p className="py-5 text-sm text-slate-500">Carregando indicadores...</p>}
        {dashboard && <>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">{cards.map((card) => <div className={card.featured ? 'rounded-2xl bg-navy p-5 text-white shadow-[0_8px_30px_rgba(15,35,70,0.14)]' : `${panel} p-5`} key={card.label}><div className={`flex items-center justify-between gap-2 text-xs font-semibold ${card.featured ? 'text-blue-200' : 'text-slate-500'}`}><span>{card.label}</span><span className={`grid h-9 w-9 shrink-0 place-items-center rounded-xl ${card.featured ? 'bg-white/10 text-gold' : 'bg-blue-50 text-navy-light'}`}><Icon name={card.icon} className="h-4 w-4" /></span></div><strong className={`mt-5 block text-2xl font-extrabold tracking-tight ${card.featured ? 'text-white' : 'text-navy'}`}>{card.value}</strong><small className={`mt-2 block text-[11px] ${card.featured ? 'text-blue-200' : 'text-slate-400'}`}>{card.detail}</small></div>)}</div>
          <div className="my-5 grid items-start gap-4 lg:grid-cols-2"><Ranking title="Principais clientes" description="Clientes com maior valor total de compras" items={dashboard.customers} icon="users" /><Ranking title="Produtos em destaque" description="Produtos com maior valor vendido" items={dashboard.products} icon="box" /></div>
        </>}
        <section className={`${panel} mt-5 p-5 sm:p-6`} id="purchases">
          <div className="flex flex-wrap items-start justify-between gap-3"><div><div className="mb-2 text-[10px] font-bold tracking-[0.16em] text-pitch">TRANSAÇÕES</div><h2 className="text-lg font-bold text-navy">Histórico de compras</h2><p className="mt-1 text-xs text-slate-500">Datas em UTC. Os filtros alteram apenas a listagem; os indicadores acima abrangem todo o período.</p></div><span className="rounded-full bg-blue-50 px-3 py-1.5 text-[11px] font-bold text-navy-light">{purchases?.total ?? 0} compras</span></div>
          <form onSubmit={submit} className="my-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
            {[['reference', 'Referência', 'Ex.: PED-001'], ['customer', 'Cliente', user.role === 'admin' ? 'Nome, e-mail ou CPF' : 'Nome'], ['date_from', 'Data inicial (UTC)', '', 'date'], ['date_to', 'Data final (UTC)', '', 'date'], ['amount_min', 'Valor mínimo', '0,00', 'number'], ['amount_max', 'Valor máximo', '0,00', 'number']].map(([key, label, placeholder, type]) =>
              <label className="text-xs font-semibold text-slate-600" key={key}>{label}<input className="mt-2 block w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2.5 text-xs font-normal text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100" type={type || 'text'} placeholder={placeholder} min={type === 'number' ? '0' : undefined} step={type === 'number' ? '0.01' : undefined} value={filters[key]} onChange={(event) => setFilters({ ...filters, [key]: event.target.value })} /></label>)}
            <div className="flex gap-2 sm:col-span-2 lg:col-span-3 2xl:col-span-6"><button className="flex items-center gap-2 rounded-lg bg-navy px-4 py-2.5 text-xs font-bold text-white transition hover:bg-navy-light" type="submit"><Icon name="search" className="h-4 w-4" /> Buscar compras</button><button type="button" className={secondaryButton} onClick={reset}>Limpar</button></div>
          </form>
          {listError && <div className="my-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">{listError}</div>}
          {loading ? <p className="py-12 text-center text-sm text-slate-400">Carregando compras...</p> : !listError && purchases?.data.length === 0 ? <p className="py-12 text-center text-sm text-slate-400">Nenhuma compra encontrada. Experimente outros filtros ou envie uma compra pela API.</p> : !listError && <div className="relative overflow-x-auto"><table className="w-full whitespace-nowrap text-left text-xs"><thead className="bg-slate-50 text-[10px] tracking-wide text-slate-400 uppercase"><tr>
            {purchaseColumns.map(([key, label], index) => <th key={key} scope="col" className={`${index === 0 ? 'rounded-l-lg ' : ''}p-3`} aria-sort={sort.sort_by === key ? (sort.sort_direction === 'asc' ? 'ascending' : 'descending') : undefined}>
              <button type="button" className="inline-flex items-center gap-1 font-bold transition hover:text-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy" onClick={() => changeSort(key)}>
                {label}<i className={`${sort.sort_by === key ? (sort.sort_direction === 'asc' ? 'ri-sort-asc' : 'ri-sort-desc') + ' text-navy' : 'ri-arrow-up-down-line'} text-sm`} aria-hidden="true" />
              </button>
            </th>)}
            <th className="rounded-r-lg p-3"><span className="sr-only">Detalhes</span></th></tr></thead><tbody>
            {purchases?.data.map((purchase) => <PurchaseRows key={purchase.id} purchase={purchase} expanded={expanded === purchase.id} onToggle={() => setExpanded(expanded === purchase.id ? null : purchase.id)} />)}
          </tbody></table></div>}
          {purchases && purchases.last_page > 1 && <div className="mt-5 flex items-center justify-between text-xs text-slate-500"><span>Página {purchases.current_page} de {purchases.last_page}</span><div className="flex gap-2"><button type="button" className={secondaryButton} disabled={page <= 1 || loading} onClick={() => changePage(page - 1)}>Anterior</button><button type="button" className={secondaryButton} disabled={page >= purchases.last_page || loading} onClick={() => changePage(page + 1)}>Próxima</button></div></div>}
        </section>
        <footer className="mt-8 flex flex-wrap justify-between gap-2 text-[10px] font-bold tracking-widest text-slate-400">ARCTRON ANALYTICS <span className="font-normal tracking-normal">Dados para decisões melhores.</span></footer>
      </div>
    </main>
  </div>
}

function PurchaseRows({ purchase, expanded, onToggle }) {
  const cell = 'border-b border-slate-100 p-3 text-slate-500'
  return <><tr><td className={cell}><strong className="text-navy-light">{purchase.reference}</strong></td><td className={cell}><strong className="text-slate-700">{purchase.customer_name}</strong><small className="mt-1 block text-slate-400">{purchase.customer_email}</small></td><td className={cell}>{date(purchase.purchased_at)}</td><td className={cell}>{money(purchase.discount_cents)}</td><td className={cell}><strong className="text-navy">{money(purchase.amount_cents)}</strong></td><td className={cell}><button className="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 text-lg text-navy-light hover:bg-blue-50" type="button" onClick={onToggle} aria-expanded={expanded}>{expanded ? '−' : '+'}<span className="sr-only"> detalhes de {purchase.reference}</span></button></td></tr>
    {expanded && <tr><td className="whitespace-normal bg-slate-50 p-4 text-xs leading-6 text-slate-600" colSpan="6"><div><strong className="text-navy">Produtos</strong><ul className="list-inside list-disc">{purchase.items.map((item) => <li key={item.id}>{item.product.name} · {item.quantity} × {money(item.unit_price_cents)}</li>)}</ul>{purchase.customer_cpf && <><span>CPF: {purchase.customer_cpf}</span><p>Observações: {purchase.notes || 'Nenhuma observação.'}</p></>}</div></td></tr>}</>
}

export default App
