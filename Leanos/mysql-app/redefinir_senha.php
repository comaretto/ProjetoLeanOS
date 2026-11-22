<?php
declare(strict_types=1);
require 'seguranca.php';
require 'conexao.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$mensagem = '';
$sucesso = false;
$tokenValido = false;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $query = $db->prepare('SELECT id FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()');
    $query->execute([hash('sha256', $token)]);
    $tokenValido = (bool)$query->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $senha = (string)($_POST['senha'] ?? '');
    if (!$tokenValido || strlen($senha) < 6) {
        $mensagem = 'O link expirou ou a senha possui menos de 6 caracteres.';
    } else {
        $db->beginTransaction();
        $query = $db->prepare('SELECT user_id FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE');
        $query->execute([hash('sha256', $token)]);
        $reset = $query->fetch();
        if (!$reset) {
            $db->rollBack();
            $mensagem = 'O link expirou ou já foi utilizado.';
        } else {
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), $reset['user_id']]);
            $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = ?')->execute([hash('sha256', $token)]);
            $db->commit();
            $sucesso = true;
            $mensagem = 'Senha alterada com sucesso. Você já pode entrar.';
        }
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Nova senha | LeanOS</title><link rel="stylesheet" href="style.css"></head><body><main class="login-page"><form class="login-card" method="post"><p class="eyebrow">LeanOS</p><h2>Definir nova senha</h2><?php if ($mensagem): ?><p class="flash <?= $sucesso ? '' : 'error' ?>"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><?php if ($tokenValido && !$sucesso): ?><?= campo_csrf() ?><input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"><div class="field"><label>Nova senha</label><input name="senha" type="password" minlength="6" required autofocus></div><button class="button">Alterar senha</button><?php endif; ?><p class="muted"><a href="login.php">Voltar para o login</a></p></form></main></body></html>
