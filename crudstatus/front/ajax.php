<?php

/**
 * Plugin CRUD Status - endpoints AJAX (JSON)
 */

while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();
include('../../../inc/includes.php');
while (ob_get_level() > 0) {
    ob_end_clean();
}

register_shutdown_function(static function (): void {
    $erro = error_get_last();
    if ($erro && in_array($erro['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => 'Erro interno ao processar a solicitação.']);
    }
});

function crudstatus_responder(array $dados): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $dados['new_token'] = PluginCrudstatusConfig::tokenCsrf();
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!Session::getLoginUserID() || !PluginCrudstatusConfig::podeUsar()) {
    crudstatus_responder(['success' => false, 'message' => 'Sem permissão.']);
}

$S = PluginCrudstatusStatus::class;
$acao = (string) ($_REQUEST['action'] ?? '');
if ($acao === 'icones') {
    crudstatus_responder(['success' => true, 'icones' => PluginCrudstatusIcones::lista()]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    crudstatus_responder(['success' => false, 'message' => 'Método não permitido.']);
}
$tipo = (string) ($_POST['tipo'] ?? '');
$numero = (int) ($_POST['numero'] ?? 0);
$precisaTipo = !in_array($acao, ['aplicar_ponte', 'remover_ponte'], true);
if ($precisaTipo && !$S::valido($tipo)) {
    crudstatus_responder(['success' => false, 'message' => 'Tipo inválido.']);
}

switch ($acao) {
    case 'salvar':
        [$ok, $msg] = $S::salvar($tipo, $_POST);
        break;
    case 'restaurar':
        [$ok, $msg] = $S::restaurar($tipo, $numero);
        break;
    case 'restaurar_tipo':
        [$ok, $msg] = $S::restaurarTipo($tipo);
        break;
    case 'excluir':
        $destino = isset($_POST['destino']) && $_POST['destino'] !== '' ? (int) $_POST['destino'] : null;
        [$ok, $msg] = $S::excluir($tipo, $numero, $destino);
        break;
    case 'mover':
        [$ok, $msg] = $S::mover($tipo, $numero, (int) ($_POST['direcao'] ?? 1));
        break;
    case 'disponivel':
        [$ok, $msg] = $S::disponivel($tipo, $numero, !empty($_POST['valor']));
        break;
    case 'uso':
        crudstatus_responder(['success' => true, 'uso' => $S::uso($tipo, $numero)]);
        // no break
    case 'aplicar_ponte':
        $r = PluginCrudstatusNucleo::aplicar();
        PluginCrudstatusStatus::gerarArquivos();
        [$ok, $msg] = [$r['ok'], $r['mensagem']];
        break;
    case 'remover_ponte':
        $r = PluginCrudstatusNucleo::remover();
        PluginCrudstatusStatus::gerarArquivos();
        [$ok, $msg] = [$r['ok'], $r['mensagem']];
        break;
    default:
        crudstatus_responder(['success' => false, 'message' => 'Ação desconhecida.']);
}
crudstatus_responder(['success' => (bool) $ok, 'message' => $msg]);
