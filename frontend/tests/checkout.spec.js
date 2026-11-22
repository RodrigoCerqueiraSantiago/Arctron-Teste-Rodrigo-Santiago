import { execFileSync } from 'node:child_process'
import { test, expect } from '@playwright/test'

test.describe.configure({ timeout: 120_000 })

test('checkout imports the order and sends a local email', async ({ page, request }) => {
  const email = `checkout-${Date.now()}@example.test`
  const loginEmail = process.env.E2E_LOGIN_EMAIL
  const loginPassword = process.env.E2E_LOGIN_PASSWORD
  if (!loginEmail || !loginPassword) throw new Error('Defina E2E_LOGIN_EMAIL e E2E_LOGIN_PASSWORD para um administrador de teste.')

  await page.goto('http://localhost:8081/shop/')
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
  await expect(page.getByText('Obrigado. Seu pedido foi recebido.')).toBeVisible()

  execFileSync('docker', ['compose', 'exec', '-T', 'wpcli', 'wp', 'action-scheduler', 'run', '--batch-size=100', '--batches=5'], { stdio: 'inherit' })

  await page.goto('http://localhost:5173/')
  await page.getByLabel('E-mail', { exact: true }).fill(loginEmail)
  await page.getByLabel('Senha', { exact: true }).fill(loginPassword)
  await page.getByRole('button', { name: 'Entrar' }).click()
  await expect(page.getByRole('button', { name: 'Sair' })).toBeVisible()

  await expect.poll(async () => {
    const response = await page.request.get(`http://localhost:8080/api/purchases?reference=woocommerce%3A${orderId}`)
    if (!response.ok()) return null
    const result = await response.json()
    return result.total === 1 ? result.data[0] : null
  }, { timeout: 30_000 }).toMatchObject({
    reference: `woocommerce:${orderId}`,
    customer_cpf: '12345678901',
    customer_email: email,
    amount_cents: 9995,
  })

  await expect(page.getByText(`woocommerce:${orderId}`, { exact: true })).toBeVisible()

  await expect.poll(async () => {
    const response = await request.get('http://localhost:8025/api/v1/messages')
    if (!response.ok()) return false
    const result = await response.json()
    return (result.messages ?? []).some(message => message.To.some(recipient => recipient.Address === email))
  }, { timeout: 30_000 }).toBe(true)
})

test('reader sees purchases without personal data', async ({ page, request }) => {
  const loginEmail = process.env.E2E_READER_EMAIL
  const loginPassword = process.env.E2E_READER_PASSWORD
  if (!loginEmail || !loginPassword) throw new Error('Defina E2E_READER_EMAIL e E2E_READER_PASSWORD para um leitor de teste.')

  const withoutCsrf = await request.post('http://localhost:8080/api/login', {
    data: { email: loginEmail, password: loginPassword },
  })
  expect(withoutCsrf.status()).toBe(419)

  await page.goto('http://localhost:5173/')
  await page.getByLabel('E-mail', { exact: true }).fill(loginEmail)
  await page.getByLabel('Senha', { exact: true }).fill(loginPassword)
  await page.getByRole('button', { name: 'Entrar' }).click()
  await expect(page.getByText('Leitor', { exact: false })).toBeVisible()

  const response = await page.request.get('http://localhost:8080/api/purchases')
  expect(response.ok()).toBe(true)
  const result = await response.json()
  expect(result.data.length).toBeGreaterThan(0)
  expect(result.data[0]).not.toHaveProperty('customer_email')
  expect(result.data[0]).not.toHaveProperty('customer_cpf')
  expect(result.data[0]).not.toHaveProperty('notes')
  await page.getByRole('button', { name: /detalhes de/ }).first().click()
  await expect(page.getByText('CPF:', { exact: false })).toHaveCount(0)
  await page.getByRole('button', { name: 'Sair' }).click()
  await expect(page.getByRole('heading', { name: 'Acessar dashboard' })).toBeVisible()
})
