(() => {
  const state = { products: [], category: 'all', query: '', cart: JSON.parse(localStorage.getItem('hsw365-cart') || '[]') };
  const money = value => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value || 0));
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const $ = id => document.getElementById(id);
  function persist() { localStorage.setItem('hsw365-cart', JSON.stringify(state.cart)); renderCart(); }
  function visibleProducts() {
    return state.products.filter(p => (state.category === 'all' || String(p.category || '').toLowerCase() === state.category) &&
      (String(p.title).toLowerCase().includes(state.query) || String(p.description || '').toLowerCase().includes(state.query)));
  }
  function renderProducts() {
    const grid = $('product-grid');
    const list = visibleProducts();
    $('store-notice').textContent = list.length ? '' : 'No products found in this collection yet.';
    grid.innerHTML = list.map(p => {
      const image = p.image_url ? '<img loading="lazy" src="' + esc(p.image_url) + '" alt="' + esc(p.title) + '">' : '<div class="product-placeholder">HSW365</div>';
      return '<article class="product-card"><div class="product-image">' + image + '</div><div class="product-info"><div><h3>' + esc(p.title) + '</h3><p>' + esc(p.category || 'Collection') + '</p></div><span class="product-price">' + money(p.price) + '</span></div><button class="add-button" data-add="' + esc(p.id) + '">Add to bag</button></article>';
    }).join('');
    grid.querySelectorAll('[data-add]').forEach(btn => btn.addEventListener('click', () => addToCart(btn.dataset.add)));
  }
  function addToCart(id) {
    const product = state.products.find(p => String(p.id) === String(id));
    if (!product) return;
    const line = state.cart.find(x => String(x.id) === String(id));
    if (line) line.quantity = Math.min(20, line.quantity + 1);
    else state.cart.push({ id: product.id, quantity: 1 });
    persist(); openCart();
  }
  function renderCart() {
    const count = state.cart.reduce((n, x) => n + x.quantity, 0);
    $('cart-count').textContent = count;
    let total = 0;
    $('cart-lines').innerHTML = state.cart.map(line => {
      const p = state.products.find(x => String(x.id) === String(line.id));
      if (!p) return '';
      total += Number(p.price) * line.quantity;
      return '<div class="cart-line"><img src="' + esc(p.image_url || '') + '" alt=""><div><strong>' + esc(p.title) + '</strong><small>' + money(p.price) + '</small><div class="qty"><button data-qty="' + esc(p.id) + '" data-delta="-1">−</button><span>' + line.quantity + '</span><button data-qty="' + esc(p.id) + '" data-delta="1">+</button></div></div><strong>' + money(Number(p.price) * line.quantity) + '</strong></div>';
    }).join('') || '<p>Your bag is empty.</p>';
    $('cart-total').textContent = money(total);
    $('cart-lines').querySelectorAll('[data-qty]').forEach(btn => btn.addEventListener('click', () => {
      const line = state.cart.find(x => String(x.id) === String(btn.dataset.qty));
      if (!line) return;
      line.quantity += Number(btn.dataset.delta);
      if (line.quantity <= 0) state.cart = state.cart.filter(x => x !== line);
      persist();
    }));
  }
  function openCart() { $('cart-panel').classList.add('open'); $('cart-panel').setAttribute('aria-hidden','false'); $('scrim').classList.add('show'); }
  function closeCart() { $('cart-panel').classList.remove('open'); $('cart-panel').setAttribute('aria-hidden','true'); $('scrim').classList.remove('show'); }
  async function loadProducts() {
    try {
      const response = await fetch('/api/products.php', { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('Catalog unavailable');
      const data = await response.json();
      state.products = Array.isArray(data.products) ? data.products : [];
      $('store-notice').textContent = state.products.length ? '' : 'The collection is being prepared. Check back soon.';
      renderProducts(); renderCart();
    } catch (error) {
      $('store-notice').textContent = 'The collection could not load. Please try again shortly.';
    }
  }
  $('category-filters').addEventListener('click', event => {
    const button = event.target.closest('[data-category]');
    if (!button) return;
    state.category = button.dataset.category;
    document.querySelectorAll('.filter').forEach(x => x.classList.toggle('active', x === button));
    renderProducts();
  });
  $('product-search').addEventListener('input', event => { state.query = event.target.value.trim().toLowerCase(); renderProducts(); });
  $('cart-toggle').addEventListener('click', openCart); $('cart-close').addEventListener('click', closeCart); $('scrim').addEventListener('click', closeCart);
  $('checkout-button').addEventListener('click', async () => {
    if (!state.cart.length) return;
    const button = $('checkout-button'); button.disabled = true; button.textContent = 'Connecting to secure checkout…';
    try {
      const response = await fetch('/api/create-checkout-session.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ items: state.cart }) });
      const data = await response.json();
      if (!response.ok || !data.url) throw new Error(data.error || 'Checkout could not start.');
      window.location.assign(data.url);
    } catch (error) {
      alert(error.message);
      button.disabled = false; button.textContent = 'Secure checkout';
    }
  });
  $('year').textContent = new Date().getFullYear();
  renderCart(); loadProducts();
})();