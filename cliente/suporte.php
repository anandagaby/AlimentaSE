<?php
session_start();
include('conexao.php');

if (!isset($_SESSION['id_cliente'])) {
    header("Location: index.php");
    exit();
}
$id_cliente = $_SESSION['id_cliente'];

// Obter nome do cliente
$stmt_nome = $mysqli->prepare("SELECT nome_cliente FROM cliente WHERE id_cliente = ?");
$stmt_nome->bind_param("i", $id_cliente);
$stmt_nome->execute();
$result_nome = $stmt_nome->get_result();
$nome_cliente = ($result_nome && $result_nome->num_rows > 0) 
    ? $result_nome->fetch_assoc()['nome_cliente'] 
    : "Cliente não encontrado";
$stmt_nome->close();

// Obter instituição do cliente
$stmt_insti = $mysqli->prepare("SELECT instituicao FROM cliente WHERE id_cliente = ?");
$stmt_insti->bind_param("i", $id_cliente);
$stmt_insti->execute();
$result_insti = $stmt_insti->get_result();    
$insti_cliente = ($result_insti && $result_insti->num_rows > 0) 
    ? $result_insti->fetch_assoc()['instituicao'] 
    : "Instituição não encontrada";
$stmt_insti->close();

// Contar itens no carrinho
$tipos_carrinho = isset($_SESSION['carrinho'][$id_cliente]) ? count($_SESSION['carrinho'][$id_cliente]) : 0;

// Obter mensagens e respostas para o cliente
$stmt_mensagens = $mysqli->prepare("SELECT m.mensagem, m.resposta, m.data_resposta, m.tipo 
                                   FROM mensagens m 
                                   WHERE m.id_cliente = ? ");
$stmt_mensagens->bind_param("i", $id_cliente);
$stmt_mensagens->execute();
$result_mensagens = $stmt_mensagens->get_result();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Ajuda e Suporte - AlimentaSE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f7fff3;
            margin: 0;
            color: #333;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
            background-image: url("imagens/fundo_site.png");
            background-size: cover;
            background-position: center;
            background-repeat: repeat;
            position: relative;
        }
        .navbar {
            background-color: #ff7f2a;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            box-sizing: border-box;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            margin: 0 15px;
        }
        .navbar a:hover {
            color: #ffe6cc;
        }
        #profile-card {
            position: fixed;
            top: 50px;
            right: 0px;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 10px;
        }
        #profile-card button {
            display: block;
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            background-color: #4db93e;
            border: 1px solid #ddd;
            border-radius: 3px;
            cursor: pointer;
            color: white;
            font-weight: bold;
        }
        #profile-card button:hover {
            background-color: #329129;
        }
        .hidden {
            display: none;
        }
        main {
            margin-top: 100px;
            padding: 20px;
            flex: 1;
        }
        .content {
            padding: 20px;
        }
        .support-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            margin-left: 20px;
            margin-right: 20px;
        }
        .support-section h2 {
            color: #ff7f2a;
            margin-top: 0;
        }
        .support-section ul {
            list-style: none;
            padding: 0;
        }
        .support-section ul li {
            margin-bottom: 10px;
        }
        .article-toggle {
            color: #ff7f2a;
            text-decoration: none;
            cursor: pointer;
            font-weight: bold;
        }
        .article-toggle:hover {
            text-decoration: underline;
        }
        .article-answer {
            margin-top: 10px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
            border-left: 3px solid #ff7f2a;
        }
        .faq-item {
            margin-bottom: 15px;
        }
        .faq-item strong {
            display: block;
            color: #333;
        }
        .contact-info p {
            margin: 5px 0;
        }
        footer {
            background-color: #ff7f2a;
            color: white;
            padding: 15px 0;
            text-align: center;
            width: 100%;
            flex-shrink: 0;
        }
        .footer-content p {
            margin: 5px 0;
        }
        .footer-content p a {
            color: #ffe6cc;
            text-decoration: none;
        }
        .footer-content p a:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        @media (max-width: 600px) {
            main {
                margin-top: 90px;
                margin-left: 10px;
                margin-right: 10px;
            }
            .support-section {
                padding: 15px;
                margin-left: 10px;
                margin-right: 10px;
            }
            #profile-card {
                width: 200px;
            }
        }

        /* Estilos do chat no estilo WhatsApp */
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
        .chat-container {
            position: fixed;
            bottom: 0;
            right: 20px;
            width: 320px;
            height: 500px;
            background-color: #e5ddd5;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            display: none;
            flex-direction: column;
            z-index: 1000;
        }
        .chat-header {
            background-color: #075e54;
            color: white;
            padding: 10px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #075e54;
            border-radius: 10px 10px 0 0;
        }
        .chat-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .chat-header span {
            font-weight: bold;
            font-size: 16px;
        }
        .chat-messages {
            flex-grow: 1;
            padding: 10px;
            overflow-y: auto;
            background-color: #e5ddd5;
        }
        .message {
            max-width: 70%;
            margin-bottom: 10px;
            padding: 8px 12px;
            border-radius: 10px;
            position: relative;
        }
        .sent {
            background-color: #dcf8c6;
            margin-left: auto;
            text-align: right;
        }
        .received {
            background-color: #fff;
            margin-right: auto;
        }
        .message .timestamp {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            display: block;
        }
        .response-preview {
            cursor: pointer;
            font-weight: bold;
            color: #075e54;
        }
        .response-content {
            
            margin-top: 5px;
        }
        .chat-input {
            padding: 10px;
            background-color: #f0f0f0;
            border-top: 1px solid #ccc;
            display: flex;
            gap: 5px;
        }
        .chat-input select, .chat-input textarea {
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 20px;
            flex-grow: 1;
            background-color: #fff;
        }
        .chat-input button {
            background-color: #4db93e;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
        }
        .chat-input button:hover {
            background-color: #329129;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div><a style="cursor: pointer">🍉 AlimentaSE</a></div>
        <div>
            <a href="carrinho.php" id="link-carrinho">
                🛒 Ver Carrinho
                <?php if ($tipos_carrinho > 0): ?>
                    <span class="badge" id="badge"><?= $tipos_carrinho ?></span>
                <?php else: ?>
                    <span class="badge" id="badge" style="display:none;"></span>
                <?php endif; ?>
            </a>
            <a id="profile" class="profile" onclick="toggleClick()">Perfil</a>
        </div>
    </div>

    <div id="profile-card" class="hidden">
        <a style="font-size: large; font-weight: bold; margin-right: 35px;"><?php echo htmlspecialchars($nome_cliente); ?><br></a>
        <a><?php echo htmlspecialchars($insti_cliente); ?><br></a>
        <button>Configurações</button>
        <button onclick="window.location.href='suporte.php'">Ajuda e Suporte</button>
        <button onclick="window.location.href='index.php'">Sair</button>
    </div>

    <main>
        <div class="content">
            <div class="support-section">
                <h2>Artigos de Ajuda</h2>
                <ul>
                    <li>
                        <span class="article-toggle" onclick="toggleAnswer('answer1')">Como fazer um pedido na cantina</span>
                        <div id="answer1" class="article-answer hidden">
                            <p>Para fazer um pedido na cantina, siga estes passos: <br>
                            1. Faça login na sua conta. <br>
                            2. Na página inicial selecione o produto que deseja clicando em "adicionar", para adicionar o produto no seu carrinho. <br>
                            3. Para continuar, clique em "Ver Carrinho". <br>
                            4. Confirme seu pedido, escolha sua unidade e a forma de pagamento.<br>
                            5. Finalize o produto, e escolha entre "Retirar agora", caso você já esteja no local para retirar, <br> ou "Retirar depois", se você irá tirar seu pedido em outro momento</p>
                        </div>
                    </li>
                    <li>
                        <span class="article-toggle" onclick="toggleAnswer('answer2')">Como alterar ou cancelar um pedido</span>
                        <div id="answer2" class="article-answer hidden">
                            <p>Para alterar ou cancelar um pedido: <br>
                            1. Se você deseja alterar os produtos do seu carrinho, você pode clicar em "Remover" o produto que você não deseja mais. <br>
                            2. Para Cancelar um pedido, basta você clicar no botão "Cancelar Pedido" no carde da página inicial. <br>
                            3. Não há como alterar um pedido após já ter sido feito. <br>
                            4. Não é possível cancelar um pedido que foi selecionado como "Retirar Agora". </p>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="support-section">
                <h2>Perguntas Frequentes (FAQ)</h2>
                <div class="faq-item">
                    <strong>Quais são os horários de funcionamento da cantina?</strong>
                    <p>A cantina funciona de segunda a sexta, das 8h às 22h.</p>
                </div>
                <div class="faq-item">
                    <strong>Como posso pagar pelo meu pedido?</strong>
                    <p>Aceitamos pagamento online via cartão ou pix, pagamentos em dinheiro apenas para compras feitas no local.</p>
                </div>
            </div>

            <div class="support-section">
                <h2>Guia Passo a Passo</h2>
                <p>Veja nosso tutorial detalhado sobre como agendar seu pedido:</p>
                <ul>
                    <li><a href="#">Tutorial com imagens: Fazendo um pedido</a></li>
                </ul>
            </div>

            <div class="support-section">
                <h2>Informações de Contato</h2>
                <div class="contact-info">
                    <p><strong>Suporte Online:</strong> Chat disponível no site (seg-sex, 8h-16h)</p>
                    <p><strong>Suporte Telefônico:</strong> (47) 98461-7515 (seg-sex, 9h-15h)</p>
                    <p><strong>Suporte por E-mail:</strong> suporte@alimentase.com.br</p>
                </div>
            </div>

            <div class="support-section">
                <h2>Recursos de Autoajuda</h2>
                <ul>
                    <li><a href="#">Resolver problemas de login</a></li>
                </ul>
            </div>
        </div>
    </main>

    <!-- Botão flutuante do chat -->
    <div class="chat-button" onclick="toggleChat()">
        <i class="fas fa-comment"></i>
    </div>

    <!-- Container de chat no estilo WhatsApp -->
    <div class="chat-container" id="chat-container">
        <div class="chat-header">
            <img src="https://via.placeholder.com/40" alt="Suporte AlimentaSE">
            <span>Suporte AlimentaSE</span>
        </div>
        <div class="chat-messages" id="chat-messages">
            <?php while ($row = $result_mensagens->fetch_assoc()): ?>
                <?php if ($row['mensagem']): ?>
                    <div class="message sent">
                        <?php echo htmlspecialchars($row['mensagem']); ?>
                        <span class="timestamp"><?php echo date('h:i A', strtotime($row['data_resposta'])); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($row['resposta']): ?>
                    <div class="message received">
                        <span class="response-preview">Resposta do Suporte</span>
                        <div class="response-content"><?php echo htmlspecialchars($row['resposta']); ?></div>
                        <span class="timestamp"><?php echo date('h:i A', strtotime($row['data_resposta'])); ?></span>
                    </div>
                <?php endif; ?>
            <?php endwhile; ?>
        </div>
        <div class="chat-input">
            <select name="tipo" id="tipo">
                <option value="duvida">Dúvida</option>
                <option value="feedback">Feedback</option>
            </select>
            <textarea id="chat-message" placeholder="Digite sua mensagem..." rows="1" required></textarea>
            <button onclick="sendMessage()">Enviar</button>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>Todos os direitos reservados © <?php echo date('Y'); ?></p>
            <p>Contato: <a href="https://wa.me/+5547984617515"><i class="fab fa-whatsapp"></i>(47)984617515</a></p>
        </div>
    </footer>

    <script>
        function toggleClick() {
            const profileCard = document.getElementById("profile-card");
            profileCard.classList.toggle('hidden');
        }

        function toggleAnswer(id) {
            const answer = document.getElementById(id);
            answer.classList.toggle('hidden');
        }

        function toggleChat() {
            const chatContainer = document.getElementById('chat-container');
            chatContainer.style.display = chatContainer.style.display === 'flex' ? 'none' : 'flex';
        }

        function toggleResponse(element) {
            const content = element.nextElementSibling;
            content.style.display = content.style.display === 'block' ? 'none' : 'block';
        }

        function sendMessage() {
            const tipo = document.getElementById('tipo').value;
            const mensagem = document.getElementById('chat-message').value.trim();

            if (mensagem) {
                fetch('processar_mensagem.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `tipo=${encodeURIComponent(tipo)}&mensagem=${encodeURIComponent(mensagem)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('chat-message').value = '';
                        const chatMessages = document.getElementById('chat-messages');
                        const messageDiv = document.createElement('div');
                        messageDiv.className = 'message sent';
                        messageDiv.innerHTML = `${htmlspecialchars(mensagem)}<span class="timestamp">${new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })}</span>`;
                        chatMessages.appendChild(messageDiv);
                        chatMessages.scrollTop = chatMessages.scrollHeight; // Rola para a última mensagem
                    } else {
                        alert('Erro ao enviar mensagem: ' + data.message);
                    }
                })
                .catch(error => alert('Erro: ' + error.message));
            }
        }

        // Função auxiliar para escapar HTML
        function htmlspecialchars(str) {
            return str.replace(/&/g, '&amp;')
                      .replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;')
                      .replace(/"/g, '&quot;')
                      .replace(/'/g, '&#039;');
        }

        // Ajustar altura do textarea dinamicamente
        document.getElementById('chat-message').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    </script>
</head>
</html>