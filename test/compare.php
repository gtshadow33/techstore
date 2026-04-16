index
<script>
  function addToCart(product) {
    const i = cart.findIndex(x => x.id === product.id);
    if (i > -1) cart[i].qty++;
    else cart.push({ ...product, qty: 1 });
    saveCart(); renderCart();
    showToast('✓ ' + product.name + ' añadido');
  }
</script>

productos

<script>
  let detailQty = 1;


 function changeDetailQty(delta) {
    detailQty = Math.max(1, detailQty + delta);
    document.getElementById('detailQty').textContent = detailQty;
}

  function addToCartQty(product) {
    const i = cart.findIndex(x => x.id === product.id);
    if (i > -1) cart[i].qty += detailQty;
    else cart.push({ ...product, qty: detailQty });
    saveCart(); renderCart();
    showToast('✓ ' + detailQty + '× ' + product.name + ' añadido');
    detailQty = 1;
    document.getElementById('detailQty').textContent = 1;
  }
</script>



