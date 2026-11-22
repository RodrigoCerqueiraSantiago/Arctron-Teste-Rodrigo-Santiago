(() => {
  const updateCart = () => {
    const store = window.wp.data.select('wc/store/cart')
    if (!store.hasFinishedResolution('getCartData', [])) return

    const cart = store.getCartData()
    const total = Number(cart.totals.total_price)
    if (!Number.isFinite(total)) return

    const count = document.querySelector('.cart-summary .cart-count')
    const amount = document.querySelector('.cart-summary .cart-total')
    if (count && amount) {
      count.textContent = cart.itemsCount
      amount.textContent = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: cart.totals.currency_code,
        minimumFractionDigits: cart.totals.currency_minor_unit,
      }).format(total / 10 ** cart.totals.currency_minor_unit)
    }
  }

  window.wp.data.subscribe(updateCart)
  updateCart()
})()
