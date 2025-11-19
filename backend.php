<?php
// backend.php - Backend básico para pizzaria

header('Content-Type: application/json');
session_start();

// Configuração do banco de dados (SQLite para simplicidade)
$db = new PDO('sqlite:pizzaria.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Criação das tabelas se não existirem
$db->exec("
CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT,
    email TEXT UNIQUE,
    senha TEXT,
    admin INTEGER DEFAULT 0
);
CREATE TABLE IF NOT EXISTS pizzas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT,
    ingredientes TEXT,
    preco REAL,
    imagem TEXT
);
CREATE TABLE IF NOT EXISTS pedidos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER,
    endereco TEXT,
    total REAL,
    status TEXT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS pedido_itens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pedido_id INTEGER,
    pizza_id INTEGER,
    quantidade INTEGER,
    preco_unitario REAL
);
");

// Funções auxiliares
function resposta($dados, $status = 200) {
    http_response_code($status);
    echo json_encode($dados);
    exit;
}
function usuario_atual() {
    return $_SESSION['usuario'] ?? null;
}

// Rotas
$rota = $_GET['rota'] ?? '';

switch ($rota) {
    // Autenticação
    case 'registrar':
        $dados = json_decode(file_get_contents('php://input'), true);
        if (!$dados['nome'] || !$dados['email'] || !$dados['senha']) resposta(['erro'=>'Dados obrigatórios'], 400);
        $hash = password_hash($dados['senha'], PASSWORD_DEFAULT);
        try {
            $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
            $stmt->execute([$dados['nome'], $dados['email'], $hash]);
            resposta(['sucesso'=>true]);
        } catch (PDOException $e) {
            resposta(['erro'=>'Email já cadastrado'], 400);
        }
        break;
    case 'login':
        $dados = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email=?");
        $stmt->execute([$dados['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($dados['senha'], $user['senha'])) {
            $_SESSION['usuario'] = ['id'=>$user['id'], 'nome'=>$user['nome'], 'admin'=>$user['admin']];
            resposta(['sucesso'=>true, 'usuario'=>$_SESSION['usuario']]);
        } else {
            resposta(['erro'=>'Credenciais inválidas'], 401);
        }
        break;
    case 'logout':
        session_destroy();
        resposta(['sucesso'=>true]);
        break;

    // Pizzas
    case 'listar_pizzas':
        $pizzas = $db->query("SELECT * FROM pizzas")->fetchAll(PDO::FETCH_ASSOC);
        resposta($pizzas);
        break;
    case 'adicionar_pizza':
        if (!usuario_atual() || !usuario_atual()['admin']) resposta(['erro'=>'Acesso negado'], 403);
        $dados = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare("INSERT INTO pizzas (nome, ingredientes, preco, imagem) VALUES (?, ?, ?, ?)");
        $stmt->execute([$dados['nome'], $dados['ingredientes'], $dados['preco'], $dados['imagem']]);
        resposta(['sucesso'=>true]);
        break;
    case 'editar_pizza':
        if (!usuario_atual() || !usuario_atual()['admin']) resposta(['erro'=>'Acesso negado'], 403);
        $dados = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare("UPDATE pizzas SET nome=?, ingredientes=?, preco=?, imagem=? WHERE id=?");
        $stmt->execute([$dados['nome'], $dados['ingredientes'], $dados['preco'], $dados['imagem'], $dados['id']]);
        resposta(['sucesso'=>true]);
        break;
    case 'remover_pizza':
        if (!usuario_atual() || !usuario_atual()['admin']) resposta(['erro'=>'Acesso negado'], 403);
        $id = $_GET['id'] ?? 0;
        $stmt = $db->prepare("DELETE FROM pizzas WHERE id=?");
        $stmt->execute([$id]);
        resposta(['sucesso'=>true]);
        break;

    // Pedidos
    case 'fazer_pedido':
        if (!usuario_atual()) resposta(['erro'=>'Login necessário'], 403);
        $dados = json_decode(file_get_contents('php://input'), true);
        $db->beginTransaction();
        $stmt = $db->prepare("INSERT INTO pedidos (usuario_id, endereco, total, status) VALUES (?, ?, ?, 'Pendente')");
        $stmt->execute([usuario_atual()['id'], $dados['endereco'], $dados['total']]);
        $pedido_id = $db->lastInsertId();
        foreach ($dados['itens'] as $item) {
            $stmt = $db->prepare("INSERT INTO pedido_itens (pedido_id, pizza_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
            $stmt->execute([$pedido_id, $item['pizza_id'], $item['quantidade'], $item['preco_unitario']]);
        }
        $db->commit();
        resposta(['sucesso'=>true, 'pedido_id'=>$pedido_id]);
        break;
    case 'meus_pedidos':
        if (!usuario_atual()) resposta(['erro'=>'Login necessário'], 403);
        $stmt = $db->prepare("SELECT * FROM pedidos WHERE usuario_id=? ORDER BY criado_em DESC");
        $stmt->execute([usuario_atual()['id']]);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pedidos as &$pedido) {
            $stmt2 = $db->prepare("SELECT pi.*, p.nome FROM pedido_itens pi JOIN pizzas p ON pi.pizza_id=p.id WHERE pedido_id=?");
            $stmt2->execute([$pedido['id']]);
            $pedido['itens'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        resposta($pedidos);
        break;
    case 'todos_pedidos':
        if (!usuario_atual() || !usuario_atual()['admin']) resposta(['erro'=>'Acesso negado'], 403);
        $pedidos = $db->query("SELECT * FROM pedidos ORDER BY criado_em DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pedidos as &$pedido) {
            $stmt2 = $db->prepare("SELECT pi.*, p.nome FROM pedido_itens pi JOIN pizzas p ON pi.pizza_id=p.id WHERE pedido_id=?");
            $stmt2->execute([$pedido['id']]);
            $pedido['itens'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        resposta($pedidos);
        break;
    case 'atualizar_status_pedido':
        if (!usuario_atual() || !usuario_atual()['admin']) resposta(['erro'=>'Acesso negado'], 403);
        $dados = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare("UPDATE pedidos SET status=? WHERE id=?");
        $stmt->execute([$dados['status'], $dados['id']]);
        resposta(['sucesso'=>true]);
        break;

    // Usuários (admin)
    case 'listar_usuarios':
        if (!usuario_atual() || !usuario_atual()['admin']) resposta(['erro'=>'Acesso negado'], 403);
        $usuarios = $db->query("SELECT id, nome, email, admin FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
        resposta($usuarios);
        break;
    case 'tornar_admin':
        if (!usuario_atual() || !usuario_atual()['admin']) resposta(['erro'=>'Acesso negado'], 403);
        $dados = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare("UPDATE usuarios SET admin=1 WHERE id=?");
        $stmt->execute([$dados['id']]);
        resposta(['sucesso'=>true]);
        break;

    default:
        resposta(['erro'=>'Rota não encontrada'], 404);
}
?>