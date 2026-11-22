<?php
declare(strict_types=1);
require 'seguranca.php';
require 'conexao.php';

$mensagem = '';
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $razaoSocial = trim($_POST['razao_social'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = (string)($_POST['senha'] ?? '');

    if ($razaoSocial === '' || $cnpj === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $usuario === '' || strlen($senha) < 6) {
        $mensagem = 'Preencha todos os campos. A senha deve ter pelo menos 6 caracteres.';
    } else {
        try {
            $query = $db->prepare('SELECT id FROM company_registrations WHERE username = ? OR cnpj = ? LIMIT 1');
            $query->execute([$usuario, $cnpj]);
            if ($query->fetch()) {
                $mensagem = 'Este usuário ou CNPJ já possui uma solicitação.';
            } else {
                $query = $db->prepare('INSERT INTO company_registrations (legal_name, cnpj, email, username, password_hash) VALUES (?, ?, ?, ?, ?)');
                $query->execute([$razaoSocial, $cnpj, $email, $usuario, password_hash($senha, PASSWORD_DEFAULT)]);
                $sucesso = true;
                $mensagem = 'Cadastro enviado. A equipe responsável analisará seus dados.';
            }
        } catch (PDOException $error) {
            error_log('LeanOS: falha ao cadastrar empresa: ' . $error->getMessage());
            $mensagem = 'Não foi possível salvar o cadastro. Confirme se o banco foi configurado e tente novamente.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cadastro de empresa | LeanOS</title><link rel="stylesheet" href="style.css"></head>
<body>
<main class="login-page">
  <form class="login-card" method="post">
    <p class="eyebrow">LeanOS</p><h2>Cadastre sua empresa</h2>
    <p class="muted">Envie seus dados para solicitar acesso ao sistema.</p>
    <?php if ($mensagem): ?><p class="flash <?= $sucesso ? '' : 'error' ?>"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>
    <?php if (!$sucesso): ?>
      <?= campo_csrf() ?>
      <div class="field"><label>Razão social</label><input name="razao_social" required></div>
      <div class="field"><label>CNPJ</label><input name="cnpj" placeholder="00.000.000/0000-00" required></div>
      <div class="field"><label>E-mail</label><input name="email" type="email" required></div>
      <div class="field"><label>Usuário</label><input name="usuario" required></div>
      <div class="field"><label>Senha</label><input name="senha" type="password" minlength="6" required></div>
      <button class="button" type="submit">Enviar cadastro</button>
    <?php endif; ?>
    <p class="muted"><a href="index.php">Voltar para o login</a></p>
  </form>
</main>
</body>
</html>
