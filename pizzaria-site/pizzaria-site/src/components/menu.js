import pizzas from '../data/pizzas.json';

function renderMenu() {
  const menuDiv = document.getElementById('menu');
  menuDiv.innerHTML = '';

  pizzas.forEach(pizza => {
    const pizzaElement = document.createElement('div');
    pizzaElement.className = 'pizza';

    pizzaElement.innerHTML = `
      <h2>${pizza.name}</h2>
      <p>${pizza.description}</p>
      <p>Preço: R$ ${pizza.price.toFixed(2)}</p>
      <button onclick="addToCart('${pizza.name}')">Adicionar ao Carrinho</button>
    `;

    menuDiv.appendChild(pizzaElement);
  });
}

export default renderMenu;