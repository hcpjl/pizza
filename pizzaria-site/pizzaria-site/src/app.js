// src/app.js
import { renderMenu } from './components/menu.js';
import { renderCart } from './components/cart.js';
import { checkout } from './components/checkout.js';

let cart = [];

function init() {
  renderMenu();
  renderCart(cart);
}

function addToCart(pizza) {
  cart.push(pizza);
  renderCart(cart);
}

function removeFromCart(pizzaId) {
  cart = cart.filter(pizza => pizza.id !== pizzaId);
  renderCart(cart);
}

function checkout() {
  const userInfo = prompt("Por favor, insira suas informações para o pedido:");
  if (userInfo) {
    alert("Pedido finalizado com sucesso!");
    cart = [];
    renderCart(cart);
  }
}

window.onload = init; 
window.addToCart = addToCart;
window.removeFromCart = removeFromCart;
window.checkout = checkout;