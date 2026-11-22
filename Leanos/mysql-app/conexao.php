<?php
declare(strict_types=1);

$host = getenv('LEANOS_DB_HOST') ?: '127.0.0.1';
$porta = getenv('LEANOS_DB_PORT') ?: '3306';
$banco = getenv('LEANOS_DB_NAME') ?: 'leanos';
$usuario = getenv('LEANOS_DB_USER') ?: 'root';
$senha = getenv('LEANOS_DB_PASSWORD') ?: '';

try {
    $db = new PDO(
        "mysql:host=$host;port=$porta;dbname=$banco;charset=utf8mb4",
        $usuario,
        $senha
    );

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $error) {
    error_log('LeanOS: falha na conexão com o MySQL: ' . $error->getMessage());
    http_response_code(503);
    exit('Não foi possível conectar ao banco de dados. Verifique se o MySQL está em execução e se o schema.sql foi importado.');
}
