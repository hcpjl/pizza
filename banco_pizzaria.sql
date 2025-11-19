-- Ingredientes
CREATE TABLE IF NOT EXISTS ingredientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    unidade TEXT NOT NULL, -- ex: 'g', 'ml', 'un'
    estoque REAL NOT NULL DEFAULT 0
);

-- Relação pizza-ingredientes (receita)
CREATE TABLE IF NOT EXISTS pizza_ingredientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pizza_id INTEGER NOT NULL,
    ingrediente_id INTEGER NOT NULL,
    quantidade REAL NOT NULL,
    FOREIGN KEY (pizza_id) REFERENCES pizzas(id),
    FOREIGN KEY (ingrediente_id) REFERENCES ingredientes(id)
);

-- Controle de estoque de bebidas
ALTER TABLE bebidas ADD COLUMN estoque INTEGER DEFAULT 0;

-- Entregadores
CREATE TABLE IF NOT EXISTS entregadores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    telefone TEXT
);

-- Pagamentos
CREATE TABLE IF NOT EXISTS pagamentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pedido_id INTEGER NOT NULL,
    valor_pago REAL NOT NULL,
    forma_pagamento TEXT NOT NULL, -- 'dinheiro', 'cartao', 'pix', etc
    status TEXT NOT NULL DEFAULT 'pendente', -- 'pendente', 'pago', 'cancelado'
    data_pagamento DATETIME,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
);

-- Cupons/descontos
CREATE TABLE IF NOT EXISTS cupons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    codigo TEXT NOT NULL UNIQUE,
    desconto_percentual REAL,
    desconto_valor REAL,
    validade DATETIME
);

-- Avaliações dos clientes
CREATE TABLE IF NOT EXISTS avaliacoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pedido_id INTEGER NOT NULL,
    usuario_id INTEGER NOT NULL,
    nota INTEGER NOT NULL CHECK(nota BETWEEN 1 AND 5),
    comentario TEXT,
    data_avaliacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Atualize os relacionamentos se necessário nos outros arquivos.
