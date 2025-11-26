<?php
session_start();


$PIZZAS = [
    1 => ['id'=>1,'nome'=>'Margherita','ingredientes'=>'Tomate, Mozzarella, Manjericão','preco'=>28.50,'imagem'=>'images/queijo.jpg'],
    2 => ['id'=>2,'nome'=>'Pepperoni','ingredientes'=>'Pepperoni, Mozzarella','preco'=>34.90,'imagem'=>'images/pepperoni.jpg'],
    3 => ['id'=>3,'nome'=>'Quatro Queijos','ingredientes'=>'Parmesão, Gorgonzola, Mozzarella, Catupiry','preco'=>39.00,'imagem'=>'images/queijos.jpg'],
    4 => ['id'=>4,'nome'=>'Portuguesa','ingredientes'=>'Presunto, Cebola, Ovo, Azeitona','preco'=>36.00,'imagem'=>'images/portuguesa.jpg'],
];

// utilitários de sessão/carrinho
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['orders'])) $_SESSION['orders'] = [];
$rota = $_GET['rota'] ?? 'site';

function find_pizza($id) {
    global $PIZZAS;
    return $PIZZAS[$id] ?? null;
}
function add_to_cart($id, $qty=1) {
    $id = (int)$id; $qty = max(1,(int)$qty);
    if (!find_pizza($id)) return;
    if (!isset($_SESSION['cart'][$id])) $_SESSION['cart'][$id] = 0;
    $_SESSION['cart'][$id] += $qty;
}
function update_cart($id, $qty) {
    $id = (int)$id; $qty = (int)$qty;
    if ($qty <= 0) { unset($_SESSION['cart'][$id]); return; }
    if (find_pizza($id)) $_SESSION['cart'][$id] = $qty;
}
function remove_from_cart($id) {
    $id = (int)$id; unset($_SESSION['cart'][$id]);
}
function clear_cart() { $_SESSION['cart'] = []; }
function cart_items() {
    $items = [];
    foreach ($_SESSION['cart'] as $id => $qty) {
        $p = find_pizza($id);
        if (!$p) continue;
        $items[] = ['pizza'=>$p,'qty'=>$qty,'subtotal'=>$p['preco']*$qty];
    }
    return $items;
}
function cart_total() {
    $t = 0;
    foreach (cart_items() as $it) $t += $it['subtotal'];
    return $t;
}

// processar ações via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($rota === 'add_to_cart') {
        $id = $_POST['id'] ?? 0; $qty = $_POST['qty'] ?? 1;
        add_to_cart($id,$qty);
        $_SESSION['flash'] = 'Item adicionado ao carrinho.';
        header('Location: backend.php?rota=site'); exit;
    }
    if ($rota === 'update_cart') {
        foreach ($_POST['qty'] ?? [] as $id => $q) update_cart($id,$q);
        $_SESSION['flash'] = 'Carrinho atualizado.';
        header('Location: backend.php?rota=view_cart'); exit;
    }
    if ($rota === 'remove_from_cart') {
        remove_from_cart($_POST['id'] ?? 0);
        $_SESSION['flash'] = 'Item removido.';
        header('Location: backend.php?rota=view_cart'); exit;
    }
    if ($rota === 'clear_cart') {
        clear_cart();
        $_SESSION['flash'] = 'Carrinho limpo.';
        header('Location: backend.php?rota=view_cart'); exit;
    }
    if ($rota === 'checkout') {
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if (!$name || !$address || !$phone || empty($_SESSION['cart'])) {
            $_SESSION['flash'] = 'Preencha todos os campos e tenha ao menos 1 item no carrinho.';
            header('Location: backend.php?rota=view_cart'); exit;
        }
        $order = [
            'id' => time(),
            'nome' => $name,
            'endereco' => $address,
            'telefone' => $phone,
            'itens' => cart_items(),
            'total' => cart_total(),
            'criado_em' => date('Y-m-d H:i:s'),
            'status' => 'Pendente'
        ];
        $_SESSION['orders'][] = $order;
        clear_cart();
        $_SESSION['flash'] = 'Pedido realizado com sucesso! Pedido #' . $order['id'];
        header('Location: backend.php?rota=site'); exit;
    }
}

// view do carrinho (página dedicada)
if ($rota === 'view_cart') {
    ?><!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Carrinho - Pizzaria</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="bg-gray-100">
<div class="container mx-auto px-4 py-8">
    <a href="backend.php?rota=site">&larr; Voltar ao cardápio</a>
    <h2 class="text-2xl font-bold mb-4">Seu Carrinho</h2>
    <?php if (!empty($_SESSION['flash'])){ echo '<p style="color:green">'.htmlspecialchars($_SESSION['flash']).'</p>'; unset($_SESSION['flash']); } ?>
    <?php $items = cart_items(); if (empty($items)): ?>
        <p>Seu carrinho está vazio.</p>
        <p><a href="backend.php?rota=site" class="cta-btn">Ver cardápio</a></p>
    <?php else: ?>
        <form method="post" action="backend.php?rota=update_cart">
            <table style="width:100%;border-collapse:collapse">
                <thead><tr><th align="left">Pizza</th><th>Preço</th><th>Quantidade</th><th>Subtotal</th><th></th></tr></thead>
                <tbody>
                <?php foreach($items as $it): $p = $it['pizza']; ?>
                    <tr class="cart-item">
                        <td><?=htmlspecialchars($p['nome'])?><div style="font-size:0.9em;color:#666"><?=htmlspecialchars($p['ingredientes'])?></div></td>
                        <td>R$ <?=number_format($p['preco'],2,',','.')?></td>
                        <td><input type="number" name="qty[<?=$p['id']?>]" value="<?=intval($it['qty'])?>" min="0" style="width:64px"></td>
                        <td>R$ <?=number_format($it['subtotal'],2,',','.')?></td>
                        <td>
                            <form method="post" action="backend.php?rota=remove_from_cart" style="display:inline">
                                <input type="hidden" name="id" value="<?=$p['id']?>">
                                <button type="submit" class="add-btn">Remover</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div style="text-align:right;margin-top:12px;font-weight:bold">Total: R$ <?=number_format(cart_total(),2,',','.')?></div>
            <div style="margin-top:12px;display:flex;gap:8px;margin-bottom:12px;">
                <button type="submit" class="add-btn">Atualizar</button>
        </form>
        <form method="post" action="backend.php?rota=clear_cart" style="display:inline"><button type="submit" class="bg-gray-400 text-white px-4 py-2 rounded-md">Limpar Carrinho</button></form>
        <a href="#checkout" class="cta-btn">Finalizar Pedido</a>
            </div>

        <hr style="margin:18px 0">
        <h3>Finalizar Pedido</h3>
        <form id="checkout-form" method="post" action="backend.php?rota=checkout">
            <label>Nome:<br><input name="name" required class="w-full p-2 border rounded-md"></label><br>
            <label>Endereço:<br><textarea name="address" required class="w-full p-2 border rounded-md"></textarea></label><br>
            <label>Telefone:<br><input name="phone" required class="w-full p-2 border rounded-md"></label><br>
            <div style="margin-top:8px"><button type="submit" class="bg-green-600 text-white px-8 py-2 rounded-md">Fazer Pedido</button></div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
<?php
    exit;
}

// rota site: renderiza todo o front-end (conteúdo do index.html) no PHP
if ($rota === 'site' || $rota === '') {
    ?><!doctype html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Domidog's - Sabores Autênticos da Itália</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-gray-100">
    <!-- header: nova topbar branca + rootbar vermelha abaixo -->
    <header role="banner">
        <!-- top white bar (logo + auth) -->
        <div class="top-nav bg-white shadow-sm">
            <div class="container mx-auto px-4 py-3 flex items-center justify-between">
                <!-- header centralizado: logo + nav + carrinho -->
                <div class="header-center flex items-center gap-6">
                    <a href="backend.php?rota=site" class="flex items-center gap-3" aria-label="Página inicial Domidog's">
                        <img src="https://placehold.co/40x40/d9534f/ffffff?text=D" alt="Logo Domidog's" class="w-10 h-10 rounded-full" />
                        <span class="font-bold text-lg text-red-800">Domidog's</span>
                    </a>
                    <nav class="hidden lg:flex gap-6 text-sm" aria-label="Navegação principal top">
                        <a href="#menu" class="text-gray-700 hover:text-red-800">Cardápio</a>
                        <a href="#sobre" class="text-gray-700 hover:text-red-800">Sobre</a>
                    </nav>
                    <a href="backend.php?rota=view_cart" id="cart-btn" class="bg-yellow-400 text-red-800 px-4 py-2 rounded-md font-semibold shadow-md hover:bg-yellow-300 transition focus:outline-none focus:ring-2 focus:ring-yellow-300"
                        aria-haspopup="dialog" aria-controls="cart-modal" aria-label="Abrir carrinho">
                        Carrinho (<span id="cart-count"><?=array_sum($_SESSION['cart'])?></span>)
                    </a>
                </div>
            </div>
        </div>

        <!-- rootbar / hero stripe -->
        <div class="hero-strip bg-red-800">
            <div class="container mx-auto px-4 py-8 relative">
                <!-- card central sobrepondo a faixa -->
                <div class="hero-card mx-auto rounded-lg bg-white shadow-2xl grid grid-cols-1 lg:grid-cols-2 gap-6 items-center max-w-5xl p-8"
                    role="region" aria-labelledby="hero-title">
                    <div>
                        <h1 id="hero-title"
                            class="text-2xl lg:text-3xl font-bold text-gray-800 mb-3">Peça sua pizza em casa ou retire na loja mais próxima</h1>
                        <p class="text-gray-600 mb-6">Informe seu endereço para encontrarmos a pizzaria mais próxima e mostrar o
                            cardápio disponível na sua região.</p>

                        <form id="store-search-form" method="get" action="backend.php?rota=site" class="flex gap-3 items-center"
                            aria-label="Buscar loja por endereço">
                            <label for="address-input" class="sr-only">Endereço</label>
                            <input id="address-input" name="address" type="text" placeholder="Em que endereço você está?"
                                class="w-full p-4 border rounded-full shadow-sm focus:outline-none focus:ring-2 focus:ring-red-300" />
                            <button type="submit"
                                class="cta-btn inline-block">Encontrar</button>
                        </form>

                        <div class="mt-4 text-sm text-gray-500">Entrega estimada: <strong id="eta-placeholder">30–45 min</strong>
                        </div>
                    </div>

                    <!-- imagem decorativa da pizza -->
                    <div class="hidden lg:flex justify-end">
                        <img src="https://placehold.co/520x360/d9534f/ffffff?text=Pizza+quente"
                            alt="Pizza quente e apetitosa"
                            class="rounded-lg object-cover w-full h-56 shadow-lg" />
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Menu de Pizzas com design elegante -->
    <section id="menu" class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <h3 class="text-3xl font-bold text-center mb-12 text-red-800">Nosso Menu Tradicional</h3>
            <div id="menu-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($PIZZAS as $p): ?>
                <div class="pizza-card bg-white rounded-lg shadow-lg">
                    <img src="<?=$p['imagem']?>" alt="<?=htmlspecialchars($p['nome'])?>" class="w-full h-64 object-cover rounded-t-lg" />
                    <div class="p-4">
                        <h4 class="text-lg font-semibold"><?=htmlspecialchars($p['nome'])?></h4>
                        <p class="text-sm text-gray-600 mb-2"><?=htmlspecialchars($p['ingredientes'])?></p>
                        <div class="flex items-center justify-between">
                            <div class="pizza-price">R$ <?=number_format($p['preco'],2,',','.')?></div>
                            <form method="post" action="backend.php?rota=add_to_cart" style="display:flex;gap:8px;align-items:center">
                                <input type="hidden" name="id" value="<?=$p['id']?>
                                <input type="number" name="qty" value="1" min="1" style="width:64px">
                                <button type="submit" class="add-btn">Adicionar</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Sobre Nós, contanto a história -->
    <section id="sobre" class="py-16 bg-gray-200">
        <div class="container mx-auto px-4 text-center">
            <h3 class="text-3xl font-bold mb-8 text-red-800">Sobre a Pizzaria Giuseppe</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                <img src="https://placehold.co/600x400/d9534f/ffffff?text=Família+italiana+preparando+pizzas+artesanalmente+no+forno+a+lênha+em+cômodo+clássico+de+pizzaria"
                    alt="Sobre a Pizzaria" class="rounded-lg shadow-lg" />
                <div class="text-left">
                    <p class="mb-4 text-lg">Fundada em 1995 por imigrantes italianos da Toscana, a Pizzaria Giuseppe traz o sabor
                        autêntico da culinária italiana para Diadema.</p>
                    <p class="mb-4">Nossa paixão por pizzas começou com receitas transmitidas de geração em geração, usando apenas
                        ingredientes selecionados importados diretamente da Itália.</p>
                    <p class="font-semibold text-red-800">Venha conhecer nossa tradição e sentir o calor da família Giuseppe!</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contato com localização -->
    <section id="contato" class="py-16 bg-white">
        <div class="container mx-auto px-4 text-center">
            <h3 class="text-3xl font-bold mb-8 text-red-800">Visite-nos em Diadema</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                <div class="text-left">
                    <h4 class="text-xl font-semibold mb-4 text-red-800">Horário de Funcionamento</h4>
                    <ul class="mb-6">
                        <li>Segunda a Sexta: 18h às 23h</li>
                        <li>Sábado e Domingo: 17h às 24h</li>
                        <li>Feriados: Consultar disponibilidade</li>
                    </ul>
                    <h4 class="text-xl font-semibold mb-2 text-red-800">Endereço</h4>
                    <p>Rua Italia, 123 - Centro, Diadema-SP</p>
                    <p>Telefone: (11) 9876-54321</p>
                </div>
                <img src="" alt="">
            </div>
        </div>
    </section>

    <!-- Modais (marc-up copiado do index; sem JS funcionam como elementos estáticos) -->
    <div id="cart-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
            <h3 class="text-2xl font-bold mb-4 text-red-800">Seu Carrinho</h3>
            <div id="cart-items-modal" class="space-y-4 mb-6">
                <!-- Itens do carrinho aqui (usar página de carrinho em vez de modal sem JS) -->
            </div>
            <div id="cart-total" class="text-xl font-bold text-right mb-4">Total: R$ 0,00</div>
            <div class="flex space-x-4">
                <a href="backend.php?rota=view_cart" class="bg-gray-400 text-white px-4 py-2 rounded-md hover:bg-gray-500 transition">Ver Carrinho</a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-red-800 text-white py-8 ">
        <div class="container mx-auto px-4 text-center ">
            <p>&copy; 2024 Pizzaria Giuseppe de Diadema. Todos os direitos reservados.</p>
            <p class="mt-2 ">Tradição italiana em cada fatia.</p>
        </div>
    </footer>

</body>
</html>
<?php
    exit;
}

// fallback
header('Location: backend.php?rota=site');
exit;
?>