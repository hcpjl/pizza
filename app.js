// Frontend JS: menu, carrinho, checkout e fidelidade (persistência via localStorage)

const pizzas = [
    { id: 1, name: 'Margherita', description: 'Mussarela, tomate e manjericão - a clássica italiana', price: 28.50, image: 'https://placehold.co/600x400/d9534f/ffffff?text=Margherita' },
    { id: 2, name: 'Napolitana', description: 'Tomate, anchovas e orégano', price: 32.00, image: 'https://placehold.co/600x400/d9534f/ffffff?text=Napolitana' },
    { id: 3, name: 'Calabresa', description: 'Calabresa, cebola e pimenta', price: 30.00, image: 'https://placehold.co/600x400/d9534f/ffffff?text=Calabresa' },
    { id: 4, name: 'Quatro Queijos', description: 'Mussarela, gorgonzola, provolone e parmesão', price: 35.00, image: 'https://placehold.co/600x400/d9534f/ffffff?text=Quatro+Queijos' },
    { id: 5, name: 'Prosciutto e Funghi', description: 'Presunto cru e cogumelos', price: 38.00, image: 'https://placehold.co/600x400/d9534f/ffffff?text=Prosciutto+e+Funghi' },
    { id: 6, name: 'Frango com Catupiry', description: 'Frango desfiado e catupiry', price: 36.00, image: 'https://placehold.co/600x400/d9534f/ffffff?text=Frango+com+Catupiry' }
];

let cart = [];
let loyalty = { count: 0, free: 0 };

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    loadCartFromStorage();
    renderPizzas();
    renderCartModal();
    updateCartCount();
    renderLoyalty();
    setupEventListeners();
});

// Render do menu
function renderPizzas() {
    const container = document.getElementById('menu-container');
    if (!container) return;
    container.innerHTML = '';
    pizzas.forEach(p => {
        const card = document.createElement('article');
        card.className = 'pizza-card';
        card.innerHTML = `
            <img src="${p.image}" alt="${p.name} - ${p.description}" />
            <div class="p-4">
                <h4 class="text-xl font-semibold mb-2">${p.name}</h4>
                <p class="text-gray-600 mb-3">${p.description}</p>
                <div class="flex justify-between items-center">
                    <span class="text-lg pizza-price">R$ ${p.price.toFixed(2)}</span>
                    <button class="add-btn" data-id="${p.id}" aria-label="Adicionar ${p.name} ao carrinho">Adicionar</button>
                </div>
            </div>
        `;
        container.appendChild(card);
    });
    // event listeners para botões "Adicionar"
    container.querySelectorAll('.add-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = Number(btn.getAttribute('data-id'));
            addToCart(id);
        });
    });
}

// Carrinho: adicionar
function addToCart(pizzaId) {
    const pizza = pizzas.find(p => p.id === pizzaId);
    if (!pizza) return;
    const existing = cart.find(i => i.id === pizzaId);
    if (existing) existing.quantity++;
    else cart.push({ ...pizza, quantity: 1 });
    saveCartToStorage();
    updateCartCount();
    renderCartModal();
    showToast(`${pizza.name} adicionado ao carrinho.`);
}

// Render modal do carrinho
function renderCartModal() {
    const container = document.getElementById('cart-items-modal');
    const totalEl = document.getElementById('cart-total');
    if (!container || !totalEl) return;
    container.innerHTML = '';
    if (cart.length === 0) {
        container.innerHTML = '<p>Seu carrinho está vazio.</p>';
        totalEl.textContent = 'Total: R$ 0,00';
        return;
    }
    let total = 0;
    cart.forEach(item => {
        total += item.price * item.quantity;
        const el = document.createElement('div');
        el.className = 'cart-item flex justify-between items-center py-2 border-b';
        el.innerHTML = `
            <div>
                <div class="font-semibold">${item.name}</div>
                <div class="flex items-center mt-2">
                    <button class="qty-btn" data-id="${item.id}" data-delta="-1" aria-label="Diminuir quantidade">-</button>
                    <span class="mx-2">${item.quantity}</span>
                    <button class="qty-btn" data-id="${item.id}" data-delta="1" aria-label="Aumentar quantidade">+</button>
                </div>
            </div>
            <div>R$ ${(item.price * item.quantity).toFixed(2)}</div>
        `;
        container.appendChild(el);
    });
    totalEl.textContent = `Total: R$ ${total.toFixed(2)}`;

    // listeners para botões de quantidade
    container.querySelectorAll('.qty-btn').forEach(b => {
        b.addEventListener('click', () => {
            const id = Number(b.getAttribute('data-id'));
            const delta = Number(b.getAttribute('data-delta'));
            changeQuantity(id, delta);
        });
    });
}

// Alterar quantidade
function changeQuantity(id, delta) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    item.quantity += delta;
    if (item.quantity <= 0) cart = cart.filter(i => i.id !== id);
    saveCartToStorage();
    updateCartCount();
    renderCartModal();
}

// Persistência
function saveCartToStorage() { localStorage.setItem('cart', JSON.stringify(cart)); }
function loadCartFromStorage() { cart = JSON.parse(localStorage.getItem('cart') || '[]'); }

// Contador do carrinho
function updateCartCount() {
    const count = cart.reduce((s, i) => s + i.quantity, 0);
    const el = document.getElementById('cart-count');
    if (el) el.textContent = count;
}

// Checkout (simulado): salvar pedido e atualizar fidelidade
function submitOrder(name, address, phone) {
    const orders = JSON.parse(localStorage.getItem('orders') || '[]');
    const total = cart.reduce((s, i) => s + i.price * i.quantity, 0);
    const orderId = 'PZ' + Date.now();
    const order = { id: orderId, itens: cart, total, name, address, phone, status: 'Pendente', criado_em: new Date().toISOString() };
    orders.push(order);
    localStorage.setItem('orders', JSON.stringify(orders));
    // atualizar fidelidade local: adicionar quantidade total de pizzas compradas
    const bought = cart.reduce((s,i)=>s+i.quantity,0);
    if (bought > 0) incrementLocalLoyalty(bought);
    cart = [];
    saveCartToStorage();
    updateCartCount();
    renderCartModal();
    showToast('Pedido enviado! ID: ' + orderId);
    return orderId;
}

// Eventos e modais
function setupEventListeners() {
    // mobile menu toggle
    document.getElementById('mobile-menu-toggle')?.addEventListener('click', function () {
        const menu = document.getElementById('mobile-menu');
        if (menu) menu.classList.toggle('hidden');
    });

    // abrir/fechar carrinho
    document.getElementById('cart-btn')?.addEventListener('click', () => openModal('cart-modal'));
    document.getElementById('close-cart')?.addEventListener('click', () => closeModal('cart-modal'));
    document.getElementById('clear-cart')?.addEventListener('click', () => {
        cart = [];
        saveCartToStorage();
        updateCartCount();
        renderCartModal();
        closeModal('cart-modal');
        showToast('Carrinho limpo.');
    });

    // checkout flow
    document.getElementById('checkout-cart')?.addEventListener('click', () => {
        closeModal('cart-modal');
        openModal('checkout-modal');
    });
    document.getElementById('back-to-cart')?.addEventListener('click', () => {
        closeModal('checkout-modal');
        openModal('cart-modal');
    });
    document.getElementById('checkout-form')?.addEventListener('submit', (e) => {
        e.preventDefault();
        const name = document.getElementById('checkout-name')?.value.trim();
        const address = document.getElementById('checkout-address')?.value.trim();
        const phone = document.getElementById('checkout-phone')?.value.trim();
        if (!name || !address || !phone) {
            alert('Por favor, preencha todos os campos.');
            return;
        }
        const id = submitOrder(name, address, phone);
        document.getElementById('checkout-form').reset();
        closeModal('checkout-modal');
        // redirecionar para página de acompanhamento
        window.location.href = 'pedido.html';
    });

    // fechar modais clicando fora
    document.getElementById('cart-modal')?.addEventListener('click', (e) => {
        if (e.target.id === 'cart-modal') closeModal('cart-modal');
    });
    document.getElementById('checkout-modal')?.addEventListener('click', (e) => {
        if (e.target.id === 'checkout-modal') closeModal('checkout-modal');
    });

    // loja/busca de endereço (se existir formulário de busca no hero)
    document.getElementById('store-search-form')?.addEventListener('submit', (e) => {
        e.preventDefault();
        const addr = document.getElementById('address-input')?.value.trim();
        if (!addr) { alert('Por favor, informe seu endereço.'); return; }
        showToast('Buscando pizzarias próximas a: ' + addr);
        const etaEl = document.getElementById('eta-placeholder');
        if (etaEl) etaEl.textContent = (25 + Math.floor(Math.random() * 21)) + ' min';
        document.getElementById('menu')?.scrollIntoView({ behavior: 'smooth' });
    });

    // loyalty button
    document.getElementById('loyalty-btn')?.addEventListener('click', () => {
        const l = JSON.parse(localStorage.getItem('loyalty') || '{"count":0,"free":0}');
        alert(`Selos: ${l.count} /10\nPizzas grátis: ${l.free}\nCompre 10 selos e ganhe 1 pizza grátis.`);
    });
}

// Modais simples
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('hidden');
    el.setAttribute('aria-hidden', 'false');
    setTimeout(() => el.querySelector('button, input, textarea')?.focus(), 50);
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('hidden');
    el.setAttribute('aria-hidden', 'true');
}

// Toast
function showToast(message) {
    const t = document.createElement('div');
    t.className = 'fixed top-4 right-4 bg-green-600 text-white px-4 py-2 rounded shadow-lg z-50';
    t.textContent = message;
    t.setAttribute('role', 'status');
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

/* Fidelidade (localStorage) */
function renderLoyalty() {
    const stored = JSON.parse(localStorage.getItem('loyalty') || '{"count":0,"free":0}');
    loyalty = stored;
    const el = document.getElementById('loyalty-count');
    if (el) el.textContent = loyalty.count ?? 0;
    if ((loyalty.free || 0) > 0) showToast(`Você tem ${loyalty.free} pizza(s) grátis disponível(is).`);
}
function incrementLocalLoyalty(add = 1) {
    const l = JSON.parse(localStorage.getItem('loyalty') || '{"count":0,"free":0}');
    l.count = (l.count || 0) + add;
    while (l.count >= 10) { l.count -= 10; l.free = (l.free || 0) + 1; }
    localStorage.setItem('loyalty', JSON.stringify(l));
    renderLoyalty();
}
function redeemLocalFree() {
    const l = JSON.parse(localStorage.getItem('loyalty') || '{"count":0,"free":0}');
    if ((l.free || 0) > 0) {
        l.free--;
        localStorage.setItem('loyalty', JSON.stringify(l));
        renderLoyalty();
        showToast('Pizza grátis resgatada! Adicione ao carrinho.');
        return true;
    }
    showToast('Nenhuma pizza grátis disponível.');
    return false;
}
