<?php
declare(strict_types=1);
require 'seguranca.php';
require 'conexao.php';

if (($_SESSION['usuario_login'] ?? '') !== (getenv('LEANOS_ADMIN_USER') ?: 'univel')) {
    http_response_code(403);
    exit('<h1>Acesso negado</h1><p>Apenas o administrador pode gerenciar cadastros.</p><p><a href="index.php">Voltar</a></p>');
}

$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $acao = $_POST['acao'] ?? '';
    $cadastro = $db->prepare('SELECT * FROM company_registrations WHERE id = ?');
    $cadastro->execute([$id]);
    $empresa = $cadastro->fetch();
    if (!$empresa) {
        $mensagem = 'Cadastro não encontrado.';
    } elseif ($acao === 'aprovar' && $empresa['status'] !== 'Aprovada') {
        $db->beginTransaction();
        $db->prepare("UPDATE company_registrations SET status = 'Aprovada' WHERE id = ?")->execute([$id]);
        $db->prepare('INSERT INTO users (company_id, name, role, priority, username, email, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$id, $empresa['legal_name'], 'Administrador da empresa', 'Média', $empresa['username'], $empresa['email'], $empresa['password_hash']]);
        $db->commit();
        $mensagem = 'Empresa aprovada. O usuário já pode entrar com os dados cadastrados.';
    } elseif ($acao === 'rejeitar') {
        $db->prepare("UPDATE company_registrations SET status = 'Rejeitada' WHERE id = ?")->execute([$id]);
        $mensagem = 'Cadastro rejeitado.';
    }
}

$cadastros = $db->query('SELECT id, legal_name, cnpj, email, username, status, created_at FROM company_registrations ORDER BY created_at DESC')->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cadastros de empresas | LeanOS</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="layout"><aside class="sidebar"><div class="brand"><span class="logo">L</span><div><strong>LeanOS</strong><small>Área dos donos</small></div></div><p class="nav-title">Administração</p><nav class="nav"><a href="index.php">⌂ Dashboard</a><a href="empresas.php">▣ Empresas cadastradas</a></nav></aside>
<main class="main"><header class="topbar"><div><small>LeanOS / Área restrita</small><h1>Cadastros de empresas</h1></div><a class="button light" href="index.php">Voltar</a></header><section class="content">
<?php if ($mensagem): ?><p class="flash"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<div class="welcome"><div><p class="eyebrow">Acesso dos donos</p><h2>Solicitações recebidas</h2><p class="muted">Aprove ou rejeite novos cadastros.</p></div><span class="status"><?= count($cadastros) ?> cadastro(s)</span></div>
<div class="card table-wrap"><table><thead><tr><th>Razão social</th><th>CNPJ</th><th>E-mail</th><th>Usuário</th><th>Status</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($cadastros as $cadastro): ?><tr><td><strong><?= htmlspecialchars($cadastro['legal_name']) ?></strong></td><td><?= htmlspecialchars($cadastro['cnpj']) ?></td><td><?= htmlspecialchars($cadastro['email']) ?></td><td><?= htmlspecialchars($cadastro['username']) ?></td><td><span class="status"><?= htmlspecialchars($cadastro['status']) ?></span></td><td><?php if ($cadastro['status'] === 'Pendente'): ?><div class="table-actions"><form method="post"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int)$cadastro['id'] ?>"><button class="button" name="acao" value="aprovar">Aprovar</button><button class="button light" name="acao" value="rejeitar">Rejeitar</button></form></div><?php else: ?>- <?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$cadastros): ?><tr><td colspan="6" class="empty">Nenhuma empresa cadastrada ainda.</td></tr><?php endif; ?></tbody></table></div></section></main></div>
</body></html>
