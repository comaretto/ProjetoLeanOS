<?php
declare(strict_types=1);
require 'seguranca.php';
require 'conexao.php';

function texto(string $valor): string { return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); }
function mensagem(string $valor): never { $_SESSION['mensagem'] = $valor; header('Location: index.php'); exit; }
function logado(): bool { return isset($_SESSION['usuario_id']); }
function empresaId(): ?int { return isset($_SESSION['empresa_id']) && $_SESSION['empresa_id'] !== null ? (int)$_SESSION['empresa_id'] : null; }
function queryEmpresa(PDO $db, string $sql, array $params = []): array {
    $query = $db->prepare($sql);
    $query->execute($params);
    return $query->fetchAll();
}
function empresaWhere(string $column = 'company_id'): array {
    return empresaId() === null ? ['', []] : [" WHERE $column = ?", [empresaId()]];
}

$adminUser = getenv('LEANOS_ADMIN_USER') ?: 'univel';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'logout') { session_destroy(); header('Location: login.php'); exit; }
    if (!logado()) mensagem('Faça login para continuar.');

    if ($action === 'save_process') {
        $name = trim($_POST['process_name'] ?? '');
        $owner = trim($_POST['process_owner'] ?? '');
        $stage = trim($_POST['step_name'] ?? '');
        if ($name === '' || $owner === '' || $stage === '') mensagem('Preencha o processo, responsável e etapa.');
        $db->beginTransaction();
        $q = $db->prepare('INSERT INTO processes (company_id,name,owner,priority,category,start_date,due_date,status,progress) VALUES (?,?,?,?,?,?,?,"Planejado",0)');
        $q->execute([empresaId(), $name, $owner, $_POST['priority'] ?? 'Média', trim($_POST['category'] ?? 'Operacional'), $_POST['start_date'] ?: null, $_POST['due_date'] ?: null]);
        $q = $db->prepare('INSERT INTO steps (process_id,name,step_order) VALUES (?,?,1)');
        $q->execute([(int)$db->lastInsertId(), $stage]);
        $db->commit(); mensagem('Processo criado com a primeira etapa.');
    }
    if ($action === 'save_task') {
        $title = trim($_POST['title'] ?? '');
        if ($title === '' || trim($_POST['owner'] ?? '') === '') mensagem('Informe o título e o responsável da tarefa.');
        $q = $db->prepare('INSERT INTO tasks (company_id,process_id,title,owner,priority,status,due_date,progress) VALUES (?,?,?,?,?,?,?,0)');
        $q->execute([empresaId(), (int)($_POST['process_id'] ?? 0) ?: null, $title, trim($_POST['owner']), $_POST['priority'] ?? 'Média', 'Pendente', $_POST['due_date'] ?: null]);
        mensagem('Tarefa criada.');
    }
    if ($action === 'complete_task') {
        $q = $db->prepare('UPDATE tasks SET status="Concluída", progress=100 WHERE id=?' . (empresaId() === null ? '' : ' AND company_id=?'));
        $params = [(int)$_POST['task_id']]; if (empresaId() !== null) $params[] = empresaId(); $q->execute($params);
        mensagem('Tarefa concluída.');
    }
    if ($action === 'save_measurement') {
        $stepId = (int)($_POST['step_id'] ?? 0); $start = strtotime($_POST['started_at'] ?? ''); $finish = strtotime($_POST['finished_at'] ?? '');
        if (!$stepId || !$start || !$finish || $finish <= $start) mensagem('Informe horários válidos; o término deve ser posterior ao início.');
        $check = $db->prepare('SELECT steps.id FROM steps JOIN processes ON processes.id=steps.process_id WHERE steps.id=? AND (processes.company_id=? OR ? IS NULL)');
        $check->execute([$stepId, empresaId(), empresaId()]); if (!$check->fetch()) mensagem('Etapa inválida para esta empresa.');
        $q = $db->prepare('INSERT INTO measurements (step_id,started_at,finished_at,minutes,operator_name) VALUES (?,?,?,?,?)');
        $minutes = round(($finish - $start) / 60, 2); $q->execute([$stepId, date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $finish), $minutes, $_SESSION['usuario_nome']]);
        mensagem("Medição registrada: $minutes minutos.");
    }
    if ($action === 'save_cause') {
        $measurementId = (int)($_POST['measurement_id'] ?? 0);
        $check = $db->prepare('SELECT measurements.id FROM measurements JOIN steps ON steps.id=measurements.step_id JOIN processes ON processes.id=steps.process_id WHERE measurements.id=? AND (processes.company_id=? OR ? IS NULL)');
        $check->execute([$measurementId, empresaId(), empresaId()]);
        if (!$check->fetch()) mensagem('Medição inválida para esta empresa.');
        $q = $db->prepare('INSERT INTO delay_causes (measurement_id,cause,note) VALUES (?,?,?)');
        $q->execute([$measurementId, trim($_POST['cause'] ?? ''), trim($_POST['note'] ?? '') ?: null]);
        mensagem('Causa registrada.');
    }
    if ($action === 'save_improvement') {
        $q = $db->prepare('INSERT INTO improvements (process_id,title,problem,action_text,before_minutes,after_minutes,status) SELECT id,?,?,?,?,?,? FROM processes WHERE id=? AND (company_id=? OR ? IS NULL)');
        $q->execute([(int)$_POST['process_id'], trim($_POST['title']), trim($_POST['problem']), trim($_POST['action_text']), (float)$_POST['before_minutes'], $_POST['after_minutes'] === '' ? null : (float)$_POST['after_minutes'], $_POST['status'], (int)$_POST['process_id'], empresaId(), empresaId()]);
        mensagem('Plano de melhoria registrado.');
    }
}

if (!logado()) { header('Location: login.php'); exit; }
$flash = $_SESSION['mensagem'] ?? ''; unset($_SESSION['mensagem']);
$page = $_GET['page'] ?? 'dashboard';
$filter = empresaWhere();
$processes = queryEmpresa($db, 'SELECT * FROM processes' . $filter[0] . ' ORDER BY id DESC', $filter[1]);
$steps = queryEmpresa($db, 'SELECT steps.*, processes.name process_name FROM steps JOIN processes ON processes.id=steps.process_id' . (empresaId() === null ? '' : ' WHERE processes.company_id=?') . ' ORDER BY processes.name, steps.step_order', empresaId() === null ? [] : [empresaId()]);
$measurements = queryEmpresa($db, 'SELECT measurements.*, steps.name step_name, processes.name process_name FROM measurements JOIN steps ON steps.id=measurements.step_id JOIN processes ON processes.id=steps.process_id' . (empresaId() === null ? '' : ' WHERE processes.company_id=?') . ' ORDER BY measurements.id DESC', empresaId() === null ? [] : [empresaId()]);
$taskFilter = empresaId() === null ? ['', []] : [' WHERE tasks.company_id = ?', [empresaId()]];
$tasks = queryEmpresa($db, 'SELECT tasks.*, processes.name process_name FROM tasks LEFT JOIN processes ON processes.id=tasks.process_id' . $taskFilter[0] . ' ORDER BY tasks.id DESC', $taskFilter[1]);
$improvements = queryEmpresa($db, 'SELECT improvements.*, processes.name process_name FROM improvements JOIN processes ON processes.id=improvements.process_id' . (empresaId() === null ? '' : ' WHERE processes.company_id=?') . ' ORDER BY improvements.id DESC', empresaId() === null ? [] : [empresaId()]);
$average = $measurements ? round(array_sum(array_map(fn($m) => (float)$m['minutes'], $measurements)) / count($measurements)) : 0;
$totalByStep = []; foreach ($measurements as $m) $totalByStep[$m['step_name']] = ($totalByStep[$m['step_name']] ?? 0) + (float)$m['minutes'];
arsort($totalByStep); $bottleneck = array_key_first($totalByStep); $totalMinutes = array_sum($totalByStep);
function options(array $items, string $value, string $label): string { $html=''; foreach($items as $item) $html .= '<option value="'.(int)$item[$value].'">'.texto((string)$item[$label]).'</option>'; return $html; }
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>LeanOS | <?=texto(ucfirst($page))?></title><link rel="stylesheet" href="style.css"></head>
<body><div class="layout"><aside class="sidebar"><div class="brand"><span class="logo">L</span><div><strong>LeanOS</strong><small>Melhoria contínua</small></div></div><p class="nav-title">Gestão</p><nav class="nav">
<a href="?page=dashboard">Dashboard</a><a href="?page=processes">Processos</a><a href="?page=measurements">Medições</a><a href="?page=causes">Causas</a><a href="?page=tasks">Tarefas</a><a href="?page=improvements">Melhorias</a><a href="?page=indicators">Indicadores</a><a href="?page=reports">Relatórios</a><a href="?page=team">Equipe</a><?php if (($_SESSION['usuario_login'] ?? '') === $adminUser): ?><a href="empresas.php">Empresas</a><?php endif; ?></nav></aside>
<main class="main"><header class="topbar"><div><small>LeanOS / <?=texto(ucfirst($page))?></small><h1><?=texto(ucfirst($page))?></h1></div><form method="post"><?=campo_csrf()?><input type="hidden" name="action" value="logout"><button class="button light">Sair</button></form></header><section class="content"><?php if($flash): ?><p class="flash"><?=texto($flash)?></p><?php endif; ?>
<?php if($page === 'dashboard'): ?>
<div class="welcome"><div><p class="eyebrow">Pulso operacional</p><h2>Olá, <?=texto($_SESSION['usuario_nome'])?></h2><p class="muted">Acompanhe processos, tarefas e oportunidades de melhoria.</p></div><a class="button" href="?page=measurements">Registrar medição</a></div>
<div class="grid"><div class="card metric"><span>Tempo médio</span><strong><?=$average?> min</strong><span><?=count($measurements)?> medições registradas</span></div><div class="card metric"><span>Gargalo principal</span><strong><?=texto($bottleneck ?: 'Sem dados')?></strong><span><?=$totalMinutes ? round(($totalByStep[$bottleneck] / $totalMinutes) * 100) : 0?>% do tempo total</span></div><div class="card metric"><span>Tarefas abertas</span><strong><?=count(array_filter($tasks, fn($t) => $t['status'] !== 'Concluída'))?></strong><span><?=count(array_filter($tasks, fn($t) => $t['status'] === 'Atrasada'))?> fora do prazo</span></div><div class="card metric"><span>Melhorias implementadas</span><strong><?=count(array_filter($improvements, fn($i) => $i['status'] === 'Implementada'))?></strong><span><?=count($improvements)?> planos registrados</span></div></div>
<div class="card"><div class="card-header"><h3>Processos prioritários</h3><span class="muted"><?=count($processes)?> processo(s)</span></div><div class="table-wrap"><table><thead><tr><th>Processo</th><th>Status</th><th>Responsável</th><th>Prioridade</th><th>Prazo</th></tr></thead><tbody><?php foreach(array_slice($processes,0,5) as $p): ?><tr><td><strong><?=texto($p['name'])?></strong><small class="muted"><?=texto($p['category'])?></small></td><td><span class="status"><?=texto($p['status'])?></span></td><td><?=texto($p['owner'])?></td><td><?=texto($p['priority'])?></td><td><?=texto($p['due_date'] ?: 'Sem prazo')?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php elseif($page === 'processes'): ?>
<div class="welcome"><div><p class="eyebrow">Fluxo operacional</p><h2>Processos</h2><p class="muted">Cadastre processos e acompanhe responsáveis, prazos e etapas.</p></div></div><div class="card"><form class="form-grid" method="post"><?=campo_csrf()?><input type="hidden" name="action" value="save_process"><div class="field"><label>Processo</label><input name="process_name" required></div><div class="field"><label>Responsável</label><input name="process_owner" required></div><div class="field"><label>Primeira etapa</label><input name="step_name" required></div><div class="field"><label>Categoria</label><input name="category" value="Operacional"></div><div class="field"><label>Prioridade</label><select name="priority"><option>Baixa</option><option selected>Média</option><option>Alta</option><option>Crítica</option></select></div><div class="field"><label>Início</label><input name="start_date" type="date"></div><div class="field"><label>Prazo</label><input name="due_date" type="date"></div><button class="button">Criar processo</button></form></div><div class="card table-wrap"><table><thead><tr><th>Processo</th><th>Status</th><th>Responsável</th><th>Prioridade</th><th>Progresso</th><th>Prazo</th></tr></thead><tbody><?php foreach($processes as $p): ?><tr><td><strong><?=texto($p['name'])?></strong><small class="muted"><?=texto($p['category'])?></small></td><td><span class="status"><?=texto($p['status'])?></span></td><td><?=texto($p['owner'])?></td><td><?=texto($p['priority'])?></td><td><?= (int)$p['progress'] ?>%</td><td><?=texto($p['due_date'] ?: 'Sem prazo')?></td></tr><?php endforeach; ?></tbody></table></div>
<?php elseif($page === 'tasks'): ?>
<div class="welcome"><div><p class="eyebrow">Acompanhamento</p><h2>Tarefas</h2><p class="muted">Organize ações e marque entregas concluídas.</p></div></div><div class="card"><form class="form-grid" method="post"><?=campo_csrf()?><input type="hidden" name="action" value="save_task"><div class="field"><label>Título</label><input name="title" required></div><div class="field"><label>Responsável</label><input name="owner" required></div><div class="field"><label>Processo</label><select name="process_id"><option value="0">Sem processo</option><?=options($processes,'id','name')?></select></div><div class="field"><label>Prioridade</label><select name="priority"><option>Baixa</option><option selected>Média</option><option>Alta</option><option>Crítica</option></select></div><div class="field"><label>Prazo</label><input name="due_date" type="date"></div><button class="button">Criar tarefa</button></form></div><div class="card table-wrap"><table><thead><tr><th>Tarefa</th><th>Processo</th><th>Responsável</th><th>Prioridade</th><th>Status</th><th>Ação</th></tr></thead><tbody><?php foreach($tasks as $task): ?><tr><td><strong><?=texto($task['title'])?></strong></td><td><?=texto($task['process_name'] ?: '—')?></td><td><?=texto($task['owner'])?></td><td><?=texto($task['priority'])?></td><td><span class="status"><?=texto($task['status'])?></span></td><td><?php if($task['status'] !== 'Concluída'): ?><form method="post"><?=campo_csrf()?><input type="hidden" name="action" value="complete_task"><input type="hidden" name="task_id" value="<?=$task['id']?>"><button class="button light">Concluir</button></form><?php else: ?>Concluída<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php elseif($page === 'measurements'): ?>
<div class="welcome"><div><p class="eyebrow">Registro operacional</p><h2>Medições</h2><p class="muted">Registre início e término; o LeanOS calcula a duração.</p></div></div><div class="card"><form class="form-grid" method="post"><?=campo_csrf()?><input type="hidden" name="action" value="save_measurement"><div class="field"><label>Etapa</label><select name="step_id" required><?=options($steps,'id','name')?></select></div><div class="field"><label>Início</label><input name="started_at" type="datetime-local" required></div><div class="field"><label>Término</label><input name="finished_at" type="datetime-local" required></div><button class="button">Registrar medição</button></form></div><div class="card table-wrap"><table><thead><tr><th>Processo</th><th>Etapa</th><th>Duração</th><th>Operador</th><th>Data</th></tr></thead><tbody><?php foreach($measurements as $m): ?><tr><td><?=texto($m['process_name'])?></td><td><?=texto($m['step_name'])?></td><td><strong><?=texto((string)$m['minutes'])?> min</strong></td><td><?=texto($m['operator_name'])?></td><td><?=texto($m['created_at'] ?? '')?></td></tr><?php endforeach; ?></tbody></table></div>
<?php elseif($page === 'causes'): ?>
<?php $causes = queryEmpresa($db, 'SELECT delay_causes.*, measurements.minutes, steps.name step_name FROM delay_causes JOIN measurements ON measurements.id=delay_causes.measurement_id JOIN steps ON steps.id=measurements.step_id JOIN processes ON processes.id=steps.process_id' . (empresaId() === null ? '' : ' WHERE processes.company_id=?') . ' ORDER BY delay_causes.id DESC', empresaId() === null ? [] : [empresaId()]); ?>
<div class="welcome"><div><p class="eyebrow">Análise de perdas</p><h2>Causas de demora</h2><p class="muted">Registre o motivo de uma medição acima do esperado.</p></div></div><div class="card"><form class="form-grid" method="post"><?=campo_csrf()?><input type="hidden" name="action" value="save_cause"><div class="field"><label>Medição</label><select name="measurement_id" required><?=options($measurements,'id','step_name')?></select></div><div class="field"><label>Causa</label><select name="cause" required><option>Espera por informação</option><option>Retrabalho</option><option>Interrupção</option><option>Deslocamento</option><option>Outro</option></select></div><div class="field"><label>Observação</label><input name="note" maxlength="255"></div><button class="button">Registrar causa</button></form></div><div class="card table-wrap"><table><thead><tr><th>Etapa</th><th>Causa</th><th>Observação</th><th>Tempo medido</th></tr></thead><tbody><?php foreach($causes as $cause): ?><tr><td><?=texto($cause['step_name'])?></td><td><span class="status"><?=texto($cause['cause'])?></span></td><td><?=texto($cause['note'] ?? '—')?></td><td><?=texto((string)$cause['minutes'])?> min</td></tr><?php endforeach; ?><?php if (!$causes): ?><tr><td colspan="4" class="empty">Nenhuma causa registrada.</td></tr><?php endif; ?></tbody></table></div>
<?php elseif($page === 'improvements'): ?>
<div class="welcome"><div><p class="eyebrow">Melhoria contínua</p><h2>Planos de melhoria</h2><p class="muted">Compare o indicador antes e depois da ação.</p></div></div><div class="card"><form class="form-grid" method="post"><?=campo_csrf()?><input type="hidden" name="action" value="save_improvement"><div class="field"><label>Processo</label><select name="process_id" required><?=options($processes,'id','name')?></select></div><div class="field"><label>Título</label><input name="title" required></div><div class="field"><label>Problema</label><input name="problem" required></div><div class="field"><label>Ação</label><input name="action_text" required></div><div class="field"><label>Antes (min)</label><input name="before_minutes" type="number" step=".01" required></div><div class="field"><label>Depois (min)</label><input name="after_minutes" type="number" step=".01"></div><div class="field"><label>Status</label><select name="status"><option>Proposta</option><option>Em execução</option><option>Verificando</option><option>Implementada</option></select></div><button class="button">Salvar plano</button></form></div><div class="grid"><?php foreach($improvements as $i): ?><div class="card metric"><span><?=texto($i['status'])?></span><h3><?=texto($i['title'])?></h3><p class="muted"><?=texto($i['problem'])?></p><strong><?=texto((string)$i['before_minutes'])?> → <?=texto($i['after_minutes'] === null ? 'Pendente' : (string)$i['after_minutes'])?> min</strong></div><?php endforeach; ?></div>
<?php elseif($page === 'indicators'): ?>
<div class="welcome"><div><p class="eyebrow">Análise</p><h2>Indicadores</h2><p class="muted">Resumo calculado a partir das medições persistidas.</p></div></div><div class="grid"><?php foreach($totalByStep as $step => $minutes): ?><div class="card metric"><span><?=texto($step)?></span><strong><?=round($minutes)?> min</strong><span><?= $totalMinutes ? round($minutes / $totalMinutes * 100) : 0 ?>% do tempo total</span></div><?php endforeach; ?></div>
<?php elseif($page === 'reports'): ?><div class="welcome"><div><p class="eyebrow">Visão executiva</p><h2>Relatórios</h2><p class="muted">Resumo atual para acompanhamento da operação.</p></div></div><div class="card"><div class="card-header"><div><h3>Resumo do período</h3><p class="muted">Indicadores calculados a partir dos dados registrados.</p></div></div><div class="report"><p><strong><?=count($processes)?></strong> processos cadastrados</p><p><strong><?=count($measurements)?></strong> medições registradas</p><p><strong><?=count($tasks)?></strong> tarefas acompanhadas</p><p><strong><?=count($improvements)?></strong> planos de melhoria</p><p><strong><?=$average?> min</strong> de tempo médio</p></div></div>
<?php elseif($page === 'team'): $users = queryEmpresa($db, 'SELECT name,role,priority,username,email FROM users' . (empresaId() === null ? '' : ' WHERE company_id=?') . ' ORDER BY name', empresaId() === null ? [] : [empresaId()]); ?><div class="welcome"><div><p class="eyebrow">Pessoas</p><h2>Equipe</h2><p class="muted">Usuários vinculados à empresa.</p></div></div><div class="card table-wrap"><table><thead><tr><th>Nome</th><th>Função</th><th>Prioridade</th><th>Usuário</th><th>E-mail</th></tr></thead><tbody><?php foreach($users as $u): ?><tr><td><?=texto($u['name'])?></td><td><?=texto($u['role'])?></td><td><?=texto($u['priority'])?></td><td><?=texto($u['username'])?></td><td><?=texto($u['email'] ?? '')?></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?></section></main></div></body></html>
