-- Criação do banco de dados para Domidogs Pizzaria

-- Tabela de usuários (clientes)
CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    telefone TEXT NOT NULL,
    email TEXT,
    endereco TEXT NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de sabores de pizza
CREATE TABLE IF NOT EXISTS pizzas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    descricao TEXT,
    preco REAL NOT NULL,
    tipo TEXT CHECK(tipo IN ('salgada','doce')) NOT NULL DEFAULT 'salgada',
    ativa INTEGER DEFAULT 1
);

-- Tabela de pedidos
CREATE TABLE IF NOT EXISTS pedidos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    data_pedido DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TEXT DEFAULT 'Processando',
    endereco_entrega TEXT NOT NULL,
    telefone TEXT NOT NULL,
    observacoes TEXT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de itens do pedido
CREATE TABLE IF NOT EXISTS pedido_itens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pedido_id INTEGER NOT NULL,
    pizza_id INTEGER NOT NULL,
    quantidade INTEGER NOT NULL DEFAULT 1,
    preco_unitario REAL NOT NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
    FOREIGN KEY (pizza_id) REFERENCES pizzas(id)
);

-- Tabela de sócios
CREATE TABLE IF NOT EXISTS socios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    funcao TEXT NOT NULL
);

-- Tabela de funcionários (organograma)
CREATE TABLE IF NOT EXISTS funcionarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    cargo TEXT NOT NULL,
    supervisor_id INTEGER,
    FOREIGN KEY (supervisor_id) REFERENCES funcionarios(id)
);

-- Tabela de processos internos (para documentação)
CREATE TABLE IF NOT EXISTS processos_internos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    etapa TEXT NOT NULL,
    descricao TEXT NOT NULL
);

-- Tabela de marketing (ações e fidelidade)
CREATE TABLE IF NOT EXISTS marketing (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tipo TEXT NOT NULL, -- 'divulgacao' ou 'fidelidade'
    descricao TEXT NOT NULL
);

-- Tabela de valores e visão
CREATE TABLE IF NOT EXISTS valores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tipo TEXT NOT NULL, -- 'missao', 'visao', 'valor'
    descricao TEXT NOT NULL
);

-- Tabela de investimentos iniciais
CREATE TABLE IF NOT EXISTS investimentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    descricao TEXT NOT NULL,
    valor REAL NOT NULL
);

-- Tabela de SWOT
CREATE TABLE IF NOT EXISTS swot (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tipo TEXT NOT NULL, -- 'forca', 'fraqueza', 'oportunidade', 'ameaca'
    descricao TEXT NOT NULL
);

-- Tabela de dados da empresa
CREATE TABLE IF NOT EXISTS dados_empresa (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    campo TEXT NOT NULL,
    valor TEXT NOT NULL
);

-- Tabela de bebidas
CREATE TABLE IF NOT EXISTS bebidas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    preco REAL NOT NULL,
    ativa INTEGER DEFAULT 1
);

-- Tabela de logs de pedidos (opcional)
CREATE TABLE IF NOT EXISTS pedidos_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pedido_id INTEGER NOT NULL,
    status TEXT NOT NULL,
    data_log DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
);