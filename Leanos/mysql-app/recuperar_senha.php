<?php
declare(strict_types=1);
require 'seguranca.php';
require 'conexao.php';
require 'mailer.php';

$mensagem = 'Se existir uma conta com esse e-mail, enviaremos um link para redefinir a senha.';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $email = trim($_POST['email'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $query = $db->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
        $query->execute([$email]);
        $user = $query->fetch();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$user['id']]);
            $db->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))')
                ->execute([$user['id'], hash('sha256', $token)]);
            $baseUrl = rtrim(getenv('LEANOS_APP_URL') ?: 'http://127.0.0.1:8086', '/');
            $link = $baseUrl . '/redefinir_senha.php?token=' . urlencode($token);
            try {
                smtp_enviar($email, 'Redefinição de senha - LeanOS', "Olá, {$user['name']}.\n\nUse o link abaixo para criar uma nova senha. Ele expira em 30 minutos e só pode ser usado uma vez:\n\n$link\n\nSe você não solicitou isso, ignore esta mensagem.");
            } catch (Throwable $error) {
                error_log('LeanOS: falha no envio de recuperação: ' . $error->getMessage());
            }
        }
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Recuperar senha | LeanOS</title><link rel="stylesheet" href="style.css"></head><body><main class="login-page"><form class="login-card" method="post"><p class="eyebrow">LeanOS</p><h2>Recuperar senha</h2><p class="muted">Informe o e-mail usado no cadastro.</p><p class="flash"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p><?= campo_csrf() ?><div class="field"><label>E-mail</label><input name="email" type="email" required autofocus></div><button class="button">Enviar link</button><p class="muted"><a href="login.php">Voltar para o login</a></p></form></main></body></html>
