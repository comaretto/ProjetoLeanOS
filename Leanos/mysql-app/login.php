<?php
declare(strict_types=1);
require 'seguranca.php';
require 'conexao.php';

$adminUsuario = getenv('LEANOS_ADMIN_USER') ?: 'univel';
$adminSenha = getenv('LEANOS_ADMIN_PASSWORD');
if ($adminSenha !== false && $adminSenha !== '') {
    $senhaInicial = password_hash($adminSenha, PASSWORD_DEFAULT);
    $adminEmail = getenv('LEANOS_ADMIN_EMAIL') ?: null;
    $db->prepare("INSERT IGNORE INTO users (name, role, priority, username, email, password_hash) VALUES ('Administrador', 'Gestão do sistema', 'Crítica', ?, ?, ?)")->execute([$adminUsuario, $adminEmail, $senhaInicial]);
    if ($adminEmail) {
        $db->prepare('UPDATE users SET email = ? WHERE username = ? AND (email IS NULL OR email = "")')->execute([$adminEmail, $adminUsuario]);
    }
}

$mensagem = $_SESSION['mensagem'] ?? '';
unset($_SESSION['mensagem']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $query = $db->prepare('SELECT users.id, users.name, users.username, users.password_hash, users.company_id, company_registrations.status AS company_status FROM users LEFT JOIN company_registrations ON company_registrations.id = users.company_id WHERE users.username = ?');
    $query->execute([$usuario]);
    $user = $query->fetch();

    if ($user && $user['company_id'] !== null && $user['company_status'] !== 'Aprovada') {
        $mensagem = 'Este cadastro ainda está pendente de aprovação.';
    } elseif ($user && password_verify($senha, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_nome'] = $user['name'];
        $_SESSION['usuario_login'] = $user['username'];
        $_SESSION['empresa_id'] = $user['company_id'];
        header('Location: index.php');
        exit;
    }

    if (!$mensagem) {
        $mensagem = 'Usuário ou senha inválidos.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | LeanOS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="login-page">
    <form class="login-card" method="post">
        <p class="eyebrow">LeanOS</p>
        <h2>Entrar no sistema</h2>
        <p class="muted">Acesse o painel de melhoria contínua.</p>
        <?php if ($mensagem): ?><p class="flash error"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>
        <?= campo_csrf() ?>
        <div class="field"><label>Usuário</label><input name="usuario" required autofocus></div>
        <div class="field"><label>Senha</label><input name="senha" type="password" required></div>
        <button class="button" type="submit">Entrar</button>
        <p class="muted login-links"><a href="recuperar_senha.php">Esqueci minha senha</a> · <a href="cadastro_empresa.php">Cadastrar uma empresa</a></p>
    </form>
</main>
</body>
</html>
