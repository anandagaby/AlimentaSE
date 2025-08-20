<?php
$usuario = 'root';
$senha = ''; 
$databse = 'cantina';
$host = 'localhost:3406';

$mysqli = new mysqli($host, $usuario, $senha, $database);

if ($mysqli->connect_error) {
    die("Falha ao conectar ao banco de dados: " . $mysqli->connect_error);
}

?>
