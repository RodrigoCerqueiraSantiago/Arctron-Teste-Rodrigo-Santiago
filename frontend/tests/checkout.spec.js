import { test, expect } from '@playwright/test'

test.describe.configure({ timeout: 120_000 })

const storeUrl = process.env.E2E_STORE_URL || 'http://localhost:8081'
const apiUrl = process.env.E2E_API_URL || 'http://localhost:8080'
const dashboardUrl = process.env.E2E_DASHBOARD_URL || 'http://localhost:5173'
const mailpitUrl = process.env.E2E_MAILPIT_URL || 'http://localhost:8025'

test('checkout imports the order and sends a local email', async ({ page, request }) => {
  const email = `checkout-${Date.now()}@example.test`
  const loginEmail = process.env.E2E_LOGIN_EMAIL
  const loginPassword = process.env.E2E_LOGIN_PASSWORD
  if (!loginEmail || !loginPassword) throw new Error('Defina E2E_LOGIN_EMAIL e E2E_LOGIN_PASSWORD para um administrador de teste.')

  await page.goto(`${storeUrl}/shop/`)
  const addShirt = page.getByRole('link', { name: /Adicione ao carrinho:.*Camisa de teste/ })
  await addShirt.click()
  await expect(page.locator('.cart-count')).toHaveText('1')
  await expect(page.locator('.cart-total')).toContainText('99,95')
  await addShirt.click()
  await expect(page.locator('.cart-count')).toHaveText('2')
  await expect(page.locator('.cart-total')).toContainText('199,90')
  await page.locator('a.added_to_cart').first().click()
  await page.getByRole('button', { name: 'Reduza a quantidade de Camisa de teste' }).click()
  await expect(page.locator('.cart-count')).toHaveText('1')
  await expect(page.locator('.cart-total')).toContainText('99,95')
  await page.getByRole('link', { name: 'Continuar para finalização' }).click()
  await expect(page.getByRole('heading', { name: 'Finalizar pedido', level: 1 })).toBeVisible()
  await page.getByLabel('Endereço de e-mail').fill(email)
  await page.getByLabel('País').selectOption('BR')
  await page.getByLabel('Nome', { exact: true }).fill('Cliente')
  await page.getByLabel('Sobrenome').fill('Checkout')
  await page.getByLabel('Endereço', { exact: true }).fill('Rua Teste 100')
  await page.getByLabel('Cidade').fill('Sao Paulo')
  await page.getByLabel('Estado').selectOption('SP')
  await page.getByLabel('CEP').fill('01310-100')
  await page.getByLabel('CPF (11 dígitos)').fill('123')
  await page.getByRole('button', { name: 'Finalizar pedido' }).click()
  await expect(page.getByText('Informe o CPF com 11 dígitos.').first()).toBeVisible()

  await page.getByLabel('CPF (11 dígitos)').fill('123.456.789-01')
  await page.getByRole('button', { name: 'Finalizar pedido' }).click()
  await page.waitForURL(/\/order-received\/\d+\//)
  const orderId = page.url().match(/\/order-received\/(\d+)\//)[1]
  await expect(page.getByRole('heading', { name: 'Pedido recebido', level: 1 })).toBeVisible()
  await expect(page.getByText('Obrigado. Seu pedido foi recebido.')).toBeVisible()

  await page.goto(dashboardUrl)
  await page.getByLabel('E-mail', { exact: true }).fill(loginEmail)
  await page.getByLabel('Senha', { exact: true }).fill(loginPassword)
  await page.getByRole('button', { name: 'Entrar' }).click()
  await expect(page.getByRole('button', { name: 'Sair' })).toBeVisible()

  await expect.poll(async () => {
    const response = await page.request.get(`${apiUrl}/api/purchases?reference=woocommerce%3A${orderId}`)
    if (!response.ok()) return null
    const result = await response.json()
    return result.total === 1 ? result.data[0] : null
  }, { timeout: 30_000 }).toMatchObject({
    reference: `woocommerce:${orderId}`,
    customer_cpf: '12345678901',
    customer_email: email,
    amount_cents: 9995,
  })

  await page.getByRole('button', { name: 'Atualizar dados' }).click()
  const purchaseLabel = `Pedido WooCommerce nº ${orderId}`
  await expect(page.getByText(purchaseLabel, { exact: true })).toBeVisible()
  await page.getByLabel('Referência', { exact: true }).fill(purchaseLabel)
  await page.getByRole('button', { name: 'Buscar compras' }).click()
  await expect(page.getByText(purchaseLabel, { exact: true })).toBeVisible()

  await expect.poll(async () => {
    const response = await request.get(`${mailpitUrl}/api/v1/messages`)
    if (!response.ok()) return false
    const result = await response.json()
    return (result.messages ?? []).some(message => message.To.some(recipient => recipient.Address === email))
  }, { timeout: 30_000 }).toBe(true)
})

test('reader sees purchases without personal data', async ({ page, request }) => {
  const loginEmail = process.env.E2E_READER_EMAIL
  const loginPassword = process.env.E2E_READER_PASSWORD
  if (!loginEmail || !loginPassword) throw new Error('Defina E2E_READER_EMAIL e E2E_READER_PASSWORD para um leitor de teste.')

  const withoutCsrf = await request.post(`${apiUrl}/api/login`, {
    data: { email: loginEmail, password: loginPassword },
  })
  expect(withoutCsrf.status()).toBe(419)

  await page.goto(dashboardUrl)
  await page.getByLabel('E-mail', { exact: true }).fill(loginEmail)
  await page.getByLabel('Senha', { exact: true }).fill(loginPassword)
  await page.getByRole('button', { name: 'Entrar' }).click()
  await expect(page.getByText('Leitor', { exact: false })).toBeVisible()

  const response = await page.request.get(`${apiUrl}/api/purchases`)
  expect(response.ok()).toBe(true)
  const result = await response.json()
  expect(result.data.length).toBeGreaterThan(0)
  expect(result.data[0]).not.toHaveProperty('customer_email')
  expect(result.data[0]).not.toHaveProperty('customer_cpf')
  expect(result.data[0]).not.toHaveProperty('notes')
  await page.getByRole('button', { name: /detalhes de/ }).first().click()
  await expect(page.getByText('CPF:', { exact: false })).toHaveCount(0)
  await page.getByRole('button', { name: 'Sair' }).click()
  await expect(page.getByRole('heading', { name: 'Acessar painel' })).toBeVisible()
})

test('purchase headers sort the complete filtered list and reset pagination', async ({ page }) => {
  let dashboardRequests = 0
  let purchaseRequests = 0
  let requestedReference = null
  const purchases = Array.from({ length: 12 }, (_, index) => ({
    id: index + 1,
    reference: `PED-${String(index + 1).padStart(2, '0')}`,
    customer_name: `Cliente ${String(index + 1).padStart(2, '0')}`,
    purchased_at: `2026-01-${String(index + 1).padStart(2, '0')}T12:00:00Z`,
    discount_cents: index * 100,
    amount_cents: (index + 1) * 100,
    items: [],
  }))
  const wooPurchase = {
    id: 13,
    reference: 'woocommerce:35',
    customer_name: 'Cliente WooCommerce',
    purchased_at: '2026-01-13T12:00:00Z',
    discount_cents: 0,
    amount_cents: 9995,
    items: [],
  }
  const columns = { reference: 'reference', customer: 'customer_name', date: 'purchased_at', discount: 'discount_cents', amount: 'amount_cents' }
  await page.route('**/api/**', async (route) => {
    const url = new URL(route.request().url())
    let data
    if (url.pathname === '/api/csrf') data = { token: 'test' }
    else if (url.pathname === '/api/me') data = { user: { name: 'Teste', role: 'reader' } }
    else if (url.pathname === '/api/dashboard') {
      dashboardRequests++
      data = { summary: {}, customers: [], products: [] }
    }
    else if (url.pathname === '/api/purchases') {
      purchaseRequests++
      const sortBy = url.searchParams.get('sort_by')
      const direction = url.searchParams.get('sort_direction')
      const pageNumber = Number(url.searchParams.get('page'))
      const reference = url.searchParams.get('reference') || ''
      requestedReference = reference
      const customer = url.searchParams.get('customer') || ''
      const filtered = reference
        ? (reference === wooPurchase.reference ? [wooPurchase] : [])
        : purchases.filter((purchase) => purchase.customer_name.includes(customer))
      const sorted = filtered.sort((a, b) => {
        const first = a[columns[sortBy]]
        const second = b[columns[sortBy]]
        return (first < second ? -1 : first > second ? 1 : 0) * (direction === 'asc' ? 1 : -1)
      })
      data = { data: sorted.slice((pageNumber - 1) * 10, pageNumber * 10), total: sorted.length, current_page: pageNumber, last_page: Math.ceil(sorted.length / 10) }
    } else throw new Error(`Unexpected API request: ${url.pathname}`)
    await route.fulfill({ json: data })
  })

  await page.goto(dashboardUrl)
  const headers = page.locator('#purchases thead')
  const rows = page.locator('#purchases tbody tr')
  await expect(headers.getByRole('columnheader', { name: 'Data (UTC)' })).toHaveAttribute('aria-sort', 'descending')
  await expect(rows.first()).toContainText('PED-12')
  await page.getByRole('button', { name: 'Próxima' }).click()
  await expect(rows.first()).toContainText('PED-02')
  await headers.getByRole('button', { name: 'Valor', exact: true }).click()
  await expect(headers.getByRole('columnheader', { name: 'Valor' })).toHaveAttribute('aria-sort', 'ascending')
  await expect(headers.locator('.ri-sort-asc')).toHaveCount(1)
  await expect(rows.first()).toContainText('PED-01')
  await expect(page.getByText('Página 1 de 2')).toBeVisible()
  await headers.getByRole('button', { name: 'Valor', exact: true }).click()
  await expect(headers.getByRole('columnheader', { name: 'Valor' })).toHaveAttribute('aria-sort', 'descending')
  await expect(headers.locator('.ri-sort-desc')).toHaveCount(1)
  await expect(rows.first()).toContainText('PED-12')

  await page.getByLabel('Cliente', { exact: true }).fill('Cliente 01')
  await page.getByRole('button', { name: 'Buscar compras' }).click()
  await expect(rows).toHaveCount(1)
  for (const label of ['Referência', 'Cliente', 'Data (UTC)', 'Desconto']) {
    await headers.getByRole('button', { name: label, exact: true }).click()
    await expect(headers.getByRole('columnheader', { name: label })).toHaveAttribute('aria-sort', 'ascending')
    await expect(rows.first()).toContainText('PED-01')
  }
  await expect(page.getByText('Os filtros alteram apenas a listagem', { exact: false })).toBeVisible()
  const beforeDashboard = dashboardRequests
  const beforePurchases = purchaseRequests
  await page.getByRole('button', { name: 'Atualizar dados' }).click()
  await expect.poll(() => dashboardRequests).toBeGreaterThan(beforeDashboard)
  await expect.poll(() => purchaseRequests).toBeGreaterThan(beforePurchases)
  await expect(rows).toHaveCount(1)
  await expect(rows.first()).toContainText('PED-01')

  await page.getByRole('button', { name: 'Limpar' }).click()
  await page.getByLabel('Referência', { exact: true }).fill('Pedido WooCommerce nº 35')
  await page.getByRole('button', { name: 'Buscar compras' }).click()
  await expect.poll(() => requestedReference).toBe('woocommerce:35')
  await expect(rows).toHaveCount(1)
  await expect(rows.first()).toContainText('Pedido WooCommerce nº 35')
})
