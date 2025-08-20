<?php
require_once 'protect.php'; // Proteção de acesso
require_once 'conexao.php'; // Conexão com o banco

// Função para executar consultas com tratamento de erro
function executeQuery($mysqli, $query) {
    $result = $mysqli->query($query);
    if (!$result) {
        error_log("Query failed: " . $mysqli->error);
        return false;
    }
    return $result;
}

// Consulta de estoque com prepared statement
$stmt = $mysqli->prepare("SELECT COUNT(*) AS total_produtos, 
    SUM(CASE WHEN qtde = 0 THEN 1 ELSE 0 END) AS sem_estoque, 
    SUM(CASE WHEN qtde BETWEEN 1 AND 5 THEN 1 ELSE 0 END) AS baixo_estoque 
    FROM produto");
$stmt->execute();
$estoque_result = $stmt->get_result();
$estoque = $estoque_result->fetch_assoc() ?: ['total_produtos' => 0, 'sem_estoque' => 0, 'baixo_estoque' => 0];

// Consulta de pedidos
$stmt = $mysqli->prepare("SELECT SUM(CASE WHEN status = 'novo' THEN 1 ELSE 0 END) AS novos, 
    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) AS pendentes, 
    SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) AS pagos 
    FROM pedidos");
$stmt->execute();
$pedidos_result = $stmt->get_result();
$pedidos = $pedidos_result->fetch_assoc() ?: ['novos' => 0, 'pendentes' => 0, 'pagos' => 0];

// Consulta de relatórios (mês atual)
$stmt = $mysqli->prepare("SELECT COUNT(*) AS vendidos_mes, 
    SUM(valor_venda) AS total_vendas,
    (SELECT produto.nome_produto 
     FROM vendas 
     JOIN produto ON vendas.produto_id_produto = produto.id_produto
     WHERE MONTH(vendas.data_venda) = MONTH(NOW()) 
     AND YEAR(vendas.data_venda) = YEAR(NOW()) 
     GROUP BY produto.id_produto 
     ORDER BY COUNT(vendas.id_vendas) DESC 
     LIMIT 1) AS produto_mais_vendido 
FROM vendas 
WHERE MONTH(data_venda) = MONTH(NOW()) 
AND YEAR(data_venda) = YEAR(NOW())");
$stmt->execute();
$relatorios_result = $stmt->get_result();
$relatorios = $relatorios_result->fetch_assoc() ?: ['vendidos_mes' => 0, 'total_vendas' => 0, 'produto_mais_vendido' => 'Nenhum'];

// Consulta de relatórios (mês passado)
$stmt = $mysqli->prepare("SELECT COUNT(*) AS vendidos_mes_passado 
FROM vendas 
WHERE MONTH(data_venda) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) 
AND YEAR(data_venda) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))");
$stmt->execute();
$relatorios_passado_result = $stmt->get_result();
$relatorios_passado = $relatorios_passado_result->fetch_assoc() ?: ['vendidos_mes_passado' => 0];

// Consultar mensagens não lidas
$stmt = $mysqli->prepare("SELECT m.id_mensagem, c.nome_cliente, m.tipo, m.mensagem, m.data_envio 
                         FROM mensagens m 
                         JOIN cliente c ON m.id_cliente = c.id_cliente 
                         WHERE m.lida = 0 
                         ORDER BY m.data_envio DESC");
$stmt->execute();
$mensagens_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Acesso</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f7fff3;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .navbar {
            background-color: #ff7f2a;
            padding: 15px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            margin: 0 10px;
            transition: color 0.3s;
        }
        .navbar a:hover {
            color: #ffe6cc;
        }
        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 15px;
        }
        .dashboard {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            width: 280px;
            text-align: center;
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card h3 {
            margin: 0 0 15px;
            font-size: 1.5em;
            color: #ff7f2a;
        }
        .card p {
            margin: 8px 0;
            font-size: 1.1em;
        }
        .destaque {
            font-weight: bold;
            color: #444;
        }
        .dinheiro {
            color: #28a745;
            font-weight: bold;
        }
        .button {
            display: inline-block;
            padding: 5px 20px;
            margin-top: 20px;
            margin-bottom: 15px;
            background-color: #ff7f2a;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #e66f24;
        }
        .chart-container {
            position: relative;
            height: 150px;
            width: 100%;
            margin: 10px 0;
        }
        @media (max-width: 900px) {
            .dashboard {
                flex-direction: column;
                align-items: center;
            }
            .card {
                width: 100%;
                max-width: 300px;
            }
        }
        @media (max-width: 600px) {
            .navbar {
                justify-content: center;
            }
            .navbar a {
                margin: 5px 0;
            }
            .container {
                margin: 20px 10px;
            }
            .chart-container {
                height: 120px;
            }
        }
        footer {
            background-color: #ff7f2a;
            color: white;
            padding: 15px 0;
            text-align: center;
            width: 100%;
        }
        .footer-content p {
            margin: 5px 0;
            font-size: 1em;
        }
        .footer-content p a {
            color: #ffe6cc;
            text-decoration: none;
            transition: color 0.3s;
        }
        .footer-content p a:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        .footer-content p a i {
            margin-right: 5px;
        }
        html, body {
            height: 100%;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main {
            flex: 1 0 auto;
        }
        footer {
            flex-shrink: 0;
        }
        .loading-spinner {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
        [data-loading="true"] .card-content {
            opacity: 0.5;
        }

        /* Estilos do chat */
        .chat-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background-color: #4db93e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            cursor: pointer;
            z-index: 1000;
        }
        .chat-button i { color: white; font-size: 24px; }
        .chat-box {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 300px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            display: none;
            flex-direction: column;
            max-height: 400px;
            overflow-y: auto;
        }
        .chat-header {
            background-color: #ff7f2a;
            color: white;
            padding: 10px;
            border-radius: 10px 10px 0 0;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
        }
        .chat-filter {
            background-color:rgba(0, 0, 0, 0.42);
            padding: 5px;
            margin-top: 5px;
            border-radius: 5px;
        }
        .chat-messages {
            padding: 10px;
            flex-grow: 1;
        }
        .message {
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 5px;
            background-color: #f1f1f1;
            cursor: pointer;
        }
        .message.selected {
            background-color: #e0e0e0;
        }
        .message .sender { font-weight: bold; color: #ff7f2a; }
        .chat-input {
            padding: 10px;
            border-top: 1px solid #ddd;
            display: none;
        }
        .chat-input textarea {
            width: 100%;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .chat-input button {
            background-color: #4db93e;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
        }
        .chat-input button:hover { background-color: #329129; }
    </style>
</head>
<body>
    <div class="navbar"> 
        <div class="links">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
        </div>
    </div>
    
    <main>
        <div class="container">
            <div class="dashboard" id="dashboard" data-loading="true">
                <div class="card estoque-card" role="region" aria-labelledby="estoque-title">
                    <div class="card-content">
                        <h3 id="estoque-title"><i class="fas fa-warehouse"></i> Estoque</h3>
                        <div class="chart-container">
                            <canvas id="estoqueChart"></canvas>
                        </div>
                        <p class="destaque">Total de Produtos: <?php echo htmlspecialchars($estoque['total_produtos']); ?></p>
                        <p>Sem Estoque: <?php echo htmlspecialchars($estoque['sem_estoque']); ?></p>
                        <p>Baixo Estoque: <?php echo htmlspecialchars($estoque['baixo_estoque']); ?></p>
                        <a href="painel.php" class="button">Ver mais</a>
                    </div>
                    <div class="loading-spinner hidden text-center">
                        <i class="fas fa-spinner text-orange-500 text-2xl"></i>
                    </div>
                </div>

                <div class="card pedidos-card" role="region" aria-labelledby="pedidos-title">
                    <div class="card-content">
                        <h3 id="pedidos-title"><i class="fas fa-shopping-cart"></i> Pedidos</h3>
                        <div class="chart-container">
                            <canvas id="pedidosChart"></canvas>
                        </div>
                        <p class="destaque">Novos: <?php echo htmlspecialchars($pedidos['novos']); ?></p>
                        <p>Pagos: <?php echo htmlspecialchars($pedidos['pagos']); ?></p><br>
                        <a href="pedidos.php" class="button">Ver mais</a>
                    </div>
                    <div class="loading-spinner hidden text-center">
                        <i class="fas fa-spinner text-orange-500 text-2xl"></i>
                    </div>
                </div>

                <div class="card relatorios-card" role="region" aria-labelledby="relatorios-title">
                    <div class="card-content">
                        <h3 id="relatorios-title"><i class="fas fa-chart-line"></i> Relatórios</h3>
                        <div class="chart-container">
                            <canvas id="relatoriosChart"></canvas>
                        </div>
                        <p class="destaque">Vendidos no Mês: <?php echo htmlspecialchars($relatorios['vendidos_mes']); ?></p>
                        <p class="dinheiro">Valor Total: R$ <?php echo number_format($relatorios['total_vendas'], 2, ',', '.'); ?></p>
                        <p>Produto Mais Vendido: <?php echo htmlspecialchars($relatorios['produto_mais_vendido']); ?></p>
                        <a href="relatorio.php" class="button">Ver mais</a>
                    </div>
                    <div class="loading-spinner hidden text-center">
                        <i class="fas fa-spinner text-orange-500 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Botão flutuante do chat -->
    <div class="chat-button" onclick="toggleChat()">
        <i class="fas fa-comment"></i>
    </div>

    <!-- Caixa de chat -->
    <div class="chat-box" id="chat-box">
        <div class="chat-header">
            Chat de Suporte
            <select class="chat-filter" id="message-filter" onchange="filterMessages()">
                <option value="all">Todos</option>
                <option value="duvida">Dúvidas</option>
                <option value="feedback">Feedbacks</option>
            </select>
        </div>
        <div class="chat-messages" id="chat-messages">
            <?php while ($row = $mensagens_result->fetch_assoc()): ?>
                <div class="message" onclick="selectMessage(<?php echo $row['id_mensagem']; ?>)">
                    <span class="sender"><?php echo htmlspecialchars($row['nome_cliente']); ?> (<?php echo htmlspecialchars($row['tipo']); ?>)</span><br>
                    <span><?php echo htmlspecialchars($row['mensagem']); ?></span><br>
                    <span><?php echo htmlspecialchars($row['data_envio']); ?></span>
                </div>
            <?php endwhile; ?>
        </div>
        <div class="chat-input" id="chat-input">
            <textarea id="response-message" placeholder="Digite sua resposta..." rows="2"></textarea>
            <button onclick="sendResponse()">Enviar Resposta</button>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>Todos os direitos reservados © <?php echo date('Y'); ?></p>
            <p>Contato: <a href="https://wa.me/+5547984617515" target="_blank"><i class="fab fa-whatsapp"></i>(47)984617515</a></p>
        </div>
    </footer>

    <script>
        // Simulação de estado de carregamento
        window.addEventListener('load', () => {
            const dashboard = document.getElementById('dashboard');
            setTimeout(() => {
                dashboard.dataset.loading = 'false';
                document.querySelectorAll('.loading-spinner').forEach(spinner => spinner.classList.add('hidden'));
                document.querySelectorAll('.card-content').forEach(content => content.classList.remove('opacity-50'));
            }, 1000);

            // Gráfico de Estoque (Donut)
            const estoqueCtx = document.getElementById('estoqueChart').getContext('2d');
            new Chart(estoqueCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Sem Estoque', 'Baixo Estoque', 'Com Estoque'],
                    datasets: [{
                        data: [
                            <?php echo $estoque['sem_estoque']; ?>,
                            <?php echo $estoque['baixo_estoque']; ?>,
                            <?php echo $estoque['total_produtos'] - $estoque['sem_estoque'] - $estoque['baixo_estoque']; ?>
                        ],
                        backgroundColor: ['#ff4d4f', '#ff7f2a', '#28a745'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });

            // Gráfico de Pedidos (Barras)
            const pedidosCtx = document.getElementById('pedidosChart').getContext('2d');
            new Chart(pedidosCtx, {
                type: 'bar',
                data: {
                    labels: ['Novos', 'Pagos'],
                    datasets: [{
                        label: 'Pedidos',
                        data: [
                            <?php echo $pedidos['novos']; ?>,
                            <?php echo $pedidos['pagos']; ?>
                        ],
                        backgroundColor: ['#ff7f2a', '#28a745'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });

            // Gráfico de Relatórios (Pizza)
            const relatoriosCtx = document.getElementById('relatoriosChart').getContext('2d');
            new Chart(relatoriosCtx, {
                type: 'pie',
                data: {
                    labels: ['Mês Atual', 'Mês Passado'],
                    datasets: [{
                        data: [
                            <?php echo $relatorios['vendidos_mes']; ?>,
                            <?php echo $relatorios_passado['vendidos_mes_passado']; ?>
                        ],
                        backgroundColor: ['#28a745', '#ff7f2a'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: { size: 12 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.raw} produtos`;
                                }
                            }
                        }
                    }
                }
            });
        });

        function toggleChat() {
            const chatBox = document.getElementById('chat-box');
            chatBox.style.display = chatBox.style.display === 'flex' ? 'none' : 'flex';
        }

        function filterMessages() {
            const filter = document.getElementById('message-filter').value;
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => {
                const tipo = msg.querySelector('.sender').textContent.split('(')[1].replace(')', '');
                msg.style.display = (filter === 'all' || filter === tipo) ? 'block' : 'none';
            });
        }

        let selectedMessageId = null;
        function selectMessage(id) {
            selectedMessageId = id;
            document.querySelectorAll('.message').forEach(msg => msg.classList.remove('selected'));
            event.target.closest('.message').classList.add('selected');
            document.getElementById('chat-input').style.display = 'block';
        }

        function sendResponse() {
            if (!selectedMessageId || !document.getElementById('response-message').value.trim()) return;

            fetch('processar_resposta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id_mensagem=${encodeURIComponent(selectedMessageId)}&resposta=${encodeURIComponent(document.getElementById('response-message').value)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('response-message').value = '';
                    document.getElementById('chat-input').style.display = 'none';
                    alert('Resposta enviada com sucesso!');
                    location.reload(); // Recarrega para atualizar a lista
                } else {
                    alert('Erro ao enviar resposta: ' + data.message);
                }
            })
            .catch(error => alert('Erro: ' + error.message));
        }
    </script>
</body>
</html>