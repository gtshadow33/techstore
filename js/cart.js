// ─── cart.js – lógica compartida del carrito ───────────────────────────────

let cart = JSON.parse(localStorage.getItem('cart') || '[]');

function saveCart() {
  localStorage.setItem('cart', JSON.stringify(cart));
}

function removeFromCart(id) {
  cart = cart.filter(x => x.id !== id);
  saveCart();
  renderCart();
}

function changeQty(id, delta) {
  const i = cart.findIndex(x => x.id === id);
  if (i === -1) return;
  cart[i].qty += delta;
  if (cart[i].qty <= 0) cart.splice(i, 1);
  saveCart();
  renderCart();
}

function renderCart() {
  const el     = document.getElementById('cartItems');
  const footer = document.getElementById('cartFooter');
  const count  = cart.reduce((s, x) => s + x.qty, 0);
  document.getElementById('cartCount').textContent = count;

  if (cart.length === 0) {
    el.innerHTML = '<div class="empty-cart"><span class="icon">🛒</span><p>El carrito está vacío</p></div>';
    footer.style.display = 'none';
    return;
  }

  footer.style.display = 'block';
  el.innerHTML = cart.map(p => `
    <div class="cart-item">
      <img src="${p.image}" alt="${p.name}">
      <div class="cart-item-info">
        <div class="cart-item-name">${p.name}</div>
        <div class="cart-item-price">€${(p.price * p.qty).toFixed(2)}</div>
        <div class="cart-qty">
          <button class="qty-btn" onclick="changeQty(${p.id}, -1)">−</button>
          <span class="qty-val">${p.qty}</span>
          <button class="qty-btn" onclick="changeQty(${p.id}, 1)">+</button>
        </div>
      </div>
      <button class="remove-btn" onclick="removeFromCart(${p.id})">🗑</button>
    </div>
  `).join('');

  const total = cart.reduce((s, x) => s + x.price * x.qty, 0);
  document.getElementById('cartTotal').textContent = '€' + total.toFixed(2);
}

function toggleCart() {
  document.getElementById('cartOverlay').classList.toggle('open');
  document.getElementById('cartPanel').classList.toggle('open');
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}

function checkout() {
  const email     = document.getElementById('email').value.trim();
  const password  = document.getElementById('password').value.trim();
  const direccion = document.getElementById('direccion').value.trim();
  const errEl     = document.getElementById('cart-error');
  errEl.style.display = 'none';

  if (!email)     { showToast('Introduce tu correo');     return; }
  if (!password)  { showToast('Introduce tu contraseña'); return; }
  if (!direccion) { showToast('Introduce tu dirección');  return; }
  if (cart.length === 0) return;

  fetch('checkout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ cart, email, password, direccion })
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      cart = [];
      saveCart();
      renderCart();
      document.getElementById('email').value     = '';
      document.getElementById('password').value  = '';
      document.getElementById('direccion').value = '';
      showToast('✔ Pedido realizado');
    } else {
      errEl.textContent   = data.error || 'Error en el pedido';
      errEl.style.display = 'block';
    }
  })
  .catch(() => {
    errEl.textContent   = 'Error de conexión';
    errEl.style.display = 'block';
  });
}



renderCart();