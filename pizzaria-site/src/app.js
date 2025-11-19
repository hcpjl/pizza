let cart = [];

async function loadMenu() {
  let res = await fetch("http://localhost/pizzaria-site/get-products.php");
  let products = await res.json();
  let menu = document.getElementById("menu");
  menu.innerHTML = "";
  products.forEach(p => {
    let div = document.createElement("div");
    div.className = "card";
    div.innerHTML = `<h3>${p.name}</h3>
                     <p>${p.description}</p>
                     <strong>R$ ${p.price}</strong>
                     <br><button onclick="addToCart(${p.id}, '${p.name}', ${p.price})">Adicionar</button>`;
    menu.appendChild(div);
  });
}

function addToCart(id, name, price) {
  cart.push({id, name, qtd: 1, price});
  alert(`${name} adicionado ao carrinho!`);
}

async function checkout() {
  if (cart.length === 0) return alert("Carrinho vazio!");
  let total = cart.reduce((sum, i) => sum + i.price*i.qtd, 0);
  let order = { user_id: 1, items: cart, total };
  let res = await fetch("http://localhost/pizzaria-site/create-order.php", {
    method: "POST",
    body: JSON.stringify(order)
  });
  let data = await res.json();
  alert("Pedido feito! ID: " + data.order_id);
  cart = [];
}

loadMenu();