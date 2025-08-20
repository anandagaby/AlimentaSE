<?php
header('Content-Type: application/json');
include('conexao.php');

session_start();
if (!isset($_SESSION['id'])) { // Ajuste para a coluna correta (ex.: 'id', 'id_usuario')
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_mensagem = isset($_POST['id_mensagem']) ? $_POST['id_mensagem'] : '';
    $resposta = isset($_POST['resposta']) ? htmlspecialchars($_POST['resposta']) : '';
    $id_cliente = isset($_POST['id_cliente']) ? intval($_POST['id_cliente']) : 0;

    if (empty($id_mensagem) || empty($resposta) || $id_cliente <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID da mensagem, resposta e cliente são obrigatórios']);
        exit;
    }

    $message_ids = array_map('intval', explode(',', $id_mensagem));

    $stmt = $mysqli->prepare("UPDATE mensagens SET resposta = ?, data_resposta = NOW(), lida = 1 WHERE id_mensagem = ? AND id_cliente = ?");
    $success = true;
    foreach ($message_ids as $msg_id) {
        $stmt->bind_param("sii", $resposta, $msg_id, $id_cliente);
        if (!$stmt->execute()) {
            $success = false;
            break;
        }
    }

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar a resposta: ' . $mysqli->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Método de requisição inválido']);
}
?>