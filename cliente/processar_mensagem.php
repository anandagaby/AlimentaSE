<?php
header('Content-Type: application/json');
include('conexao.php');

session_start();
if (!isset($_SESSION['id_cliente'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cliente = $_SESSION['id_cliente'];
    $tipo = isset($_POST['tipo']) ? htmlspecialchars($_POST['tipo']) : 'feedback'; // Define "feedback" como padrão
    $mensagem = isset($_POST['mensagem']) ? htmlspecialchars($_POST['mensagem']) : '';

    // Para o formulário de feedback, validar nome e email
    $nome = isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : '';
    $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';

    if (!empty($nome) && !empty($email)) {
        if (empty($nome) || empty($email) || empty($mensagem)) {
            echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'E-mail inválido']);
            exit;
        }
    }

    if (empty($tipo) || empty($mensagem) || !in_array($tipo, ['feedback', 'duvida'])) {
        echo json_encode(['success' => false, 'message' => 'Tipo e mensagem são obrigatórios']);
        exit;
    }

    $stmt = $mysqli->prepare("INSERT INTO mensagens (id_cliente, tipo, mensagem) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $id_cliente, $tipo, $mensagem);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar a mensagem: ' . $mysqli->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Método de requisição inválido']);
}
?>