function checkout() {
  const userInfo = {
    name: document.getElementById('name').value,
    address: document.getElementById('address').value,
    paymentMethod: document.querySelector('input[name="payment"]:checked').value,
  };

  if (!userInfo.name || !userInfo.address || !userInfo.paymentMethod) {
    alert('Por favor, preencha todas as informações necessárias.');
    return;
  }

  const cartItems = JSON.parse(localStorage.getItem('cart')) || [];
  if (cartItems.length === 0) {
    alert('Seu carrinho está vazio. Adicione itens antes de finalizar o pedido.');
    return;
  }

  // Processar o pagamento (simulação)
  alert(`Pedido finalizado com sucesso!\nNome: ${userInfo.name}\nEndereço: ${userInfo.address}\nMétodo de pagamento: ${userInfo.paymentMethod}`);
  
  // Limpar o carrinho após a finalização
  localStorage.removeItem('cart');
} 

export default checkout;