<?php
session_start();


$PIZZAS = [
    1 => ['id'=>1,'nome'=>'Margherita','ingredientes'=>'Tomate, Mozzarella, Manjericão','preco'=>28.50,'imagem'=>'images/queijo.jpg'],
    2 => ['id'=>2,'nome'=>'Pepperoni','ingredientes'=>'Pepperoni, Mozzarella','preco'=>34.90,'imagem'=>'images/pepperoni.jpg'],
    3 => ['id'=>3,'nome'=>'Quatro Queijos','ingredientes'=>'Parmesão, Gorgonzola, Mozzarella, Catupiry','preco'=>39.00,'imagem'=>'images/queijos.jpg'],
    4 => ['id'=>4,'nome'=>'Portuguesa','ingredientes'=>'Presunto, Cebola, Ovo, Azeitona','preco'=>36.00,'imagem'=>'images/portuguesa.jpg'],
];

// inicializações de sessão
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['orders'])) $_SESSION['orders'] = [];

// --- Loyalty system constants / helpers ---
define('LOYALTY_THRESHOLD', 10);

if (!isset($_SESSION['loyalty'])) {
    // agora armazenamos punches totais e quantas freebies já foram resgatadas
    $_SESSION['loyalty'] = ['punches'=>0,'redeemed_count'=>0];
}

function get_loyalty() {
    return $_SESSION['loyalty'] ?? ['punches'=>0,'redeemed_count'=>0];
}
function loyalty_add_punch($n=1) {
    $_SESSION['loyalty']['punches'] = max(0, ($_SESSION['loyalty']['punches'] ?? 0) + max(0,(int)$n));
}
function loyalty_reset() {
    $_SESSION['loyalty'] = ['punches'=>0,'redeemed_count'=>0];
}
function loyalty_available_free() {
    $p = ($_SESSION['loyalty']['punches'] ?? 0);
    $redeemed = ($_SESSION['loyalty']['redeemed_count'] ?? 0);
    return intdiv($p, LOYALTY_THRESHOLD) - $redeemed;
}
function loyalty_is_eligible() {
    return loyalty_available_free() > 0;
}
function loyalty_mark_redeemed() {
    $_SESSION['loyalty']['redeemed_count'] = ($_SESSION['loyalty']['redeemed_count'] ?? 0) + 1;
}

// utilitários de sessão/carrinho
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

// cart_items agora inclui possível pizza grátis em $_SESSION['free_pizza']
function cart_items() {
    $items = [];
    foreach ($_SESSION['cart'] as $id => $qty) {
        $p = find_pizza($id);
        if (!$p) continue;
        $items[] = ['pizza'=>$p,'qty'=>$qty,'subtotal'=>$p['preco']*$qty];
    }
    // item gratuito, se existir
    if (!empty($_SESSION['free_pizza']) && ($fp = $_SESSION['free_pizza']) && ($p = find_pizza($fp['id']))) {
        $items[] = ['pizza'=>array_merge($p,['nome'=>$p['nome'].' (Grátis)']),'qty'=>intval($fp['qty']),'subtotal'=>0.0,'free'=>true];
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
        unset($_SESSION['free_pizza']);
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

        // Creditar carimbos: somar todas as pizzas pagas (excluir itens grátis)
        $num_pizzas = 0;
        foreach ($order['itens'] as $it) {
            if (!empty($it['free'])) continue;
            $num_pizzas += intval($it['qty']);
        }
        if ($num_pizzas > 0) {
            loyalty_add_punch($num_pizzas);
        }

        // calcular quantos freebies disponíveis depois do checkout
        $available = loyalty_available_free();

        clear_cart();
        unset($_SESSION['free_pizza']);

        $msg = 'Pedido realizado com sucesso! Pedido #' . $order['id'];
        if ($num_pizzas > 0) $msg .= " — +{$num_pizzas} carimbo(s). Você tem {$available} pizza(s) grátis disponível(is).";
        $_SESSION['flash'] = $msg;

        header('Location: backend.php?rota=site'); exit;
    }

    // --- NOVOS ENDPOINTS DE FIDELIDADE ---
    if ($rota === 'add_loyalty_punch') {
        // opcional: permitir enviar count para testes; por padrão +1 (útil para admin/teste)
        $count = intval($_POST['count'] ?? 1);
        loyalty_add_punch($count);
        $_SESSION['flash'] = 'Compra registrada no Cartão Fidelidade.';
        header('Location: backend.php?rota=loyalty'); exit;
    }
    if ($rota === 'redeem_loyalty') {
        $pizza_id = intval($_POST['pizza_id'] ?? 0);
        $available = loyalty_available_free();
        if ($available <= 0) {
            $_SESSION['flash'] = 'Você ainda não atingiu o número necessário de carimbos.';
            header('Location: backend.php?rota=loyalty'); exit;
        }
        if (!find_pizza($pizza_id)) {
            $_SESSION['flash'] = 'Selecione uma pizza válida para resgatar.';
            header('Location: backend.php?rota=loyalty'); exit;
        }
        // reservar pizza grátis no carrinho e marcar 1 resgate
        $_SESSION['free_pizza'] = ['id'=>$pizza_id,'qty'=>1];
        loyalty_mark_redeemed();
        $_SESSION['flash'] = 'Parabéns! Pizza grátis adicionada ao seu carrinho.';
        header('Location: backend.php?rota=view_cart'); exit;
    }
    if ($rota === 'reset_loyalty') {
        // pequena rota administrativa para resetar (opcional)
        loyalty_reset();
        unset($_SESSION['free_pizza']);
        $_SESSION['flash'] = 'Cartão fidelidade resetado.';
        header('Location: backend.php?rota=loyalty'); exit;
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
<link rel="stylesheet" href="dec.css">
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
                        <td><?=htmlspecialchars($p['nome'])?><div style="font-size:0.9em;color:#666"><?=htmlspecialchars($p['ingredientes'] ?? '')?></div></td>
                        <td>
                            <?php if (!empty($it['free'])): ?>
                                Grátis
                            <?php else: ?>
                                R$ <?=number_format($p['preco'],2,',','.')?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($it['free'])): ?>
                                <?=intval($it['qty'])?>
                            <?php else: ?>
                                <input type="number" name="qty[<?=$p['id']?>]" value="<?=intval($it['qty'])?>" min="0" style="width:64px">
                            <?php endif; ?>
                        </td>
                        <td>R$ <?=number_format($it['subtotal'],2,',','.')?></td>
                        <td>
                            <?php if (empty($it['free'])): ?>
                            <form method="post" action="backend.php?rota=remove_from_cart" style="display:inline">
                                <input type="hidden" name="id" value="<?=$p['id']?>">
                                <button type="submit" class="add-btn">Remover</button>
                            </form>
                            <?php else: ?>
                                <!-- para item grátis: permitir remover -->
                                <form method="post" action="backend.php?rota=clear_cart" style="display:inline">
                                    <button type="submit" class="add-btn">Remover Grátis</button>
                                </form>
                            <?php endif; ?>
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

// rota loyalty: cartão fidelidade
if ($rota === 'loyalty') {
    $loyal = get_loyalty();
    $available = loyalty_available_free();
    ?><!doctype html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartão Fidelidade - Pizzaria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="dec.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <a href="backend.php?rota=site">&larr; Voltar</a>
        <h2 class="text-2xl font-bold mb-4">Cartão Fidelidade</h2>
        <?php if (!empty($_SESSION['flash'])){ echo '<p style="color:green">'.htmlspecialchars($_SESSION['flash']).'</p>'; unset($_SESSION['flash']); } ?>

        <p>Colete <?=LOYALTY_THRESHOLD?> pizzas compradas e ganhe 1 pizza grátis. (A cada <?=LOYALTY_THRESHOLD?> pizzas = 1 grátis)</p>
        <div style="margin-top:12px;">
            <?php
                // mostrar progresso dentro do bloco atual de 10
                $filled = intval($loyal['punches'] ?? 0) % LOYALTY_THRESHOLD;
                for ($i=1;$i<=LOYALTY_THRESHOLD;$i++):
            ?>
                <div class="stamp <?=($i <= $filled) ? 'filled' : ''?>"><?=($i <= $filled) ? '✓' : $i?></div>
            <?php endfor; ?>
        </div>

        <div style="margin-top:12px;color:#333">Pizzas totais compradas: <?=intval($loyal['punches'])?> — Pizzas grátis disponíveis: <?=intval($available)?></div>

        <div style="margin-top:16px;display:flex;gap:8px;">
            <form method="post" action="backend.php?rota=add_loyalty_punch">
                <input type="hidden" name="count" value="1">
                <button class="bg-yellow-400 text-red-800 px-4 py-2 rounded-md" type="submit">Registrar Compra (+1 pizza)</button>
            </form>

            <?php if ($available > 0): ?>
                <form method="post" action="backend.php?rota=redeem_loyalty" style="display:flex;gap:8px;align-items:center">
                    <label for="pizza_id">Escolha sua pizza grátis:</label>
                    <select name="pizza_id" id="pizza_id" required>
                        <?php foreach ($PIZZAS as $p): ?>
                            <option value="<?=$p['id']?>"><?=htmlspecialchars($p['nome'])?> — R$ <?=number_format($p['preco'],2,',','.')?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="bg-green-600 text-white px-4 py-2 rounded-md" type="submit">Resgatar 1 Pizza Grátis</button>
                </form>
            <?php else: ?>
                <div style="align-self:center;color:#555">Você precisa comprar mais <?= (LOYALTY_THRESHOLD - ($loyal['punches'] % LOYALTY_THRESHOLD)) ?> pizza(s) para ganhar a próxima grátis.</div>
            <?php endif; ?>

            <form method="post" action="backend.php?rota=reset_loyalty" style="margin-left:auto">
                <button class="bg-gray-300 text-black px-3 py-2 rounded-md" type="submit">Resetar (teste)</button>
            </form>
        </div>
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
    <link rel="stylesheet" href="dec.css">
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
                    <a href="backend.php?rota=loyalty" class="bg-white text-red-800 px-4 py-2 rounded-md border border-yellow-400 font-semibold shadow-sm hover:bg-yellow-50 transition">Cartão Fidelidade</a>
                    <a href="backend.php?rota=view_cart" id="cart-btn" class="bg-yellow-400 text-red-800 px-4 py-2 rounded-md font-semibold shadow-md hover:bg-yellow-300 transition focus:outline-none focus:ring-2 focus:ring-yellow-300"
                        aria-haspopup="dialog" aria-controls="cart-modal" aria-label="Abrir carrinho">
                        Carrinho (<span id="cart-count"><?=array_sum($_SESSION['cart']) + (isset($_SESSION['free_pizza']) ? intval($_SESSION['free_pizza']['qty']) : 0)?></span>)
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
        <div class="container mx-auto px-4 text-center"></div></div>
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