(() => {
  const state = { products: [], category: 'all', query: '', cart: JSON.parse(localStorage.getItem('hsw365-cart') || '[]') };
  const money = value => new Intl.NumberFormat('en-US', { style:'currency', currency:'USD' }).format(Number(value || 0));
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const $ = id => document.getElementById(id);
  const findVariant = id => state.products.flatMap(p => p.variants.map(v => ({...v, product:p}))).find(v => String(v.id) === String(id));
  function persist() { localStorage.setItem('hsw365-cart', JSON.stringify(state.cart)); renderCart(); }
  function visibleProducts() {
    return state.products.filter(p => (state.category === 'all' || String(p.category || '').toLowerCase() === state.category) &&
      (String(p.title).toLowerCase().includes(state.query) || String(p.description || '').toLowerCase().includes(state.query)));
  }
  function renderProducts() {
    const grid=$('product-grid'), list=visibleProducts();
    $('store-notice').textContent=list.length?'':'No products found in this collection yet.';
    grid.innerHTML=list.map(p=>{
      const image=p.image_url?'<img loading="lazy" src="'+esc(p.image_url)+'" alt="'+esc(p.image_alt||p.title)+'">':'<div class="product-placeholder">HSW365</div>';
      const options=p.variants.length>1?'<label class="variant-label"><span class="sr-only">Choose an option for '+esc(p.title)+'</span><select class="variant-select" data-variant-for="'+esc(p.id)+'">'+p.variants.map(v=>'<option value="'+esc(v.id)+'">'+esc(v.title)+' · '+money(v.price)+'</option>').join('')+'</select></label>':'';
      const first=p.variants[0];
      return '<article class="product-card"><div class="product-image">'+image+'</div><div class="product-info"><div><h3>'+esc(p.title)+'</h3><p>'+esc(p.category||'Collection')+'</p></div><span class="product-price">'+(first?money(first.price):'')+'</span></div>'+options+'<button class="add-button" data-add="'+esc(p.id)+'" '+(!first?'disabled':'')+'>Add to bag</button></article>';
    }).join('');
    grid.querySelectorAll('[data-add]').forEach(btn=>btn.addEventListener('click',()=>addToCart(btn.dataset.add)));
  }
  function addToCart(productId) {
    const product=state.products.find(p=>String(p.id)===String(productId));
    if(!product||!product.variants.length)return;
    const select=document.querySelector('[data-variant-for="'+CSS.escape(String(productId))+'"]');
    const variantId=select?select.value:product.variants[0].id;
    const line=state.cart.find(x=>String(x.variant_id)===String(variantId));
    if(line)line.quantity=Math.min(20,line.quantity+1);else state.cart.push({variant_id:Number(variantId),quantity:1});
    persist();openCart();
  }
  function renderCart() {
    $('cart-count').textContent=state.cart.reduce((n,x)=>n+x.quantity,0);
    let total=0;
    $('cart-lines').innerHTML=state.cart.map(line=>{
      const v=findVariant(line.variant_id);if(!v)return '';
      total+=Number(v.price)*line.quantity;
      return '<div class="cart-line"><img src="'+esc(v.product.image_url||'')+'" alt=""><div><strong>'+esc(v.product.title)+'</strong><small>'+esc(v.title)+' · '+money(v.price)+'</small><div class="qty"><button data-qty="'+esc(v.id)+'" data-delta="-1">−</button><span>'+line.quantity+'</span><button data-qty="'+esc(v.id)+'" data-delta="1">+</button></div></div><strong>'+money(Number(v.price)*line.quantity)+'</strong></div>';
    }).join('')||'<p>Your bag is empty.</p>';
    $('cart-total').textContent=money(total);
    $('cart-lines').querySelectorAll('[data-qty]').forEach(btn=>btn.addEventListener('click',()=>{
      const line=state.cart.find(x=>String(x.variant_id)===String(btn.dataset.qty));if(!line)return;
      line.quantity+=Number(btn.dataset.delta);if(line.quantity<=0)state.cart=state.cart.filter(x=>x!==line);persist();
    }));
  }
  function openCart(){$('cart-panel').classList.add('open');$('cart-panel').setAttribute('aria-hidden','false');$('scrim').classList.add('show');}
  function closeCart(){$('cart-panel').classList.remove('open');$('cart-panel').setAttribute('aria-hidden','true');$('scrim').classList.remove('show');}
  async function loadProducts(){
    try{const response=await fetch('/api/products.php',{headers:{Accept:'application/json'}});if(!response.ok)throw new Error();const data=await response.json();state.products=Array.isArray(data.products)?data.products:[];state.cart=state.cart.filter(x=>findVariant(x.variant_id));$('store-notice').textContent=state.products.length?'':'The collection is being prepared. Check back soon.';renderProducts();renderCart();}
    catch(error){$('store-notice').textContent='The collection could not load. Please try again shortly.';}
  }
  $('category-filters').addEventListener('click',event=>{const button=event.target.closest('[data-category]');if(!button)return;state.category=button.dataset.category;document.querySelectorAll('.filter').forEach(x=>x.classList.toggle('active',x===button));renderProducts();});
  $('product-search').addEventListener('input',event=>{state.query=event.target.value.trim().toLowerCase();renderProducts();});
  $('cart-toggle').addEventListener('click',openCart);$('cart-close').addEventListener('click',closeCart);$('scrim').addEventListener('click',closeCart);
  $('checkout-button').addEventListener('click',async()=>{
    if(!state.cart.length)return;const button=$('checkout-button');button.disabled=true;button.textContent='Connecting to secure checkout…';
    try{const response=await fetch('/api/create-checkout-session.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({items:state.cart})});const data=await response.json();if(!response.ok||!data.url)throw new Error(data.error||'Checkout could not start.');window.location.assign(data.url);}
    catch(error){alert(error.message);button.disabled=false;button.textContent='Secure checkout';}
  });
  $('year').textContent=new Date().getFullYear();renderCart();loadProducts();
})();