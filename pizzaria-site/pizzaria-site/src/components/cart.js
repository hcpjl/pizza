function Cart() {
  this.items = [];

  this.addItem = function(pizza) {
    this.items.push(pizza);
    this.render();
  };

  this.removeItem = function(pizzaId) {
    this.items = this.items.filter(item => item.id !== pizzaId);
    this.render();
  };

  this.render = function() {
    const cartDiv = document.getElementById('cart');
    cartDiv.innerHTML = '';

    if (this.items.length === 0) {
      cartDiv.innerHTML = '<p>Seu carrinho está vazio.</p>';
      return;
    }

    const ul = document.createElement('ul');
    this.items.forEach(item => {
      const li = document.createElement('li');
      li.textContent = `${item.name} - R$${item.price.toFixed(2)}`;
      const removeButton = document.createElement('button');
      removeButton.textContent = 'Remover';
      removeButton.onclick = () => this.removeItem(item.id);
      li.appendChild(removeButton);
      ul.appendChild(li);
    });

    cartDiv.appendChild(ul);
  };
}

export default Cart;