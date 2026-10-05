<?php

/**
 * Plugin CRUD Status - status de chamados, problemas e mudanças: lista, edição, criação, ordem e padrão
 */

include('../../../inc/includes.php');

Session::checkLoginUser();
if (!PluginCrudstatusConfig::podeUsar()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

global $CFG_GLPI;
$C = PluginCrudstatusConfig::class;
$S = PluginCrudstatusStatus::class;
$tipoAtivo = (string) ($_GET['tipo'] ?? 'Ticket');
if (!$S::valido($tipoAtivo)) {
    $tipoAtivo = 'Ticket';
}

Html::header('Status', $_SERVER['PHP_SELF'], 'config', 'PluginCrudstatusMenu', 'status');
echo '<div class="crudstatus-pagina" id="crudstatus-pagina">';
PluginCrudstatusMenu::barra('status');
PluginCrudstatusMenu::avisoPonte();

$origens = [
    'nativo'        => ['GLPI', 'neutro'],
    'nucleo'        => ['Núcleo modificado', 'aviso'],
    'personalizado' => ['Novo', 'primario'],
];
$dadosJs = ['tipos' => $S::tipos(), 'categorias' => $S::categorias(), 'proximo' => [], 'listas' => []];

echo '<ul class="nav nav-pills crudstatus-tipos" role="tablist">';
foreach ($S::tipos() as $tipo => $rot) {
    $icone = ['Ticket' => 'ti-ticket', 'Problem' => 'ti-alert-triangle', 'Change' => 'ti-exchange'][$tipo];
    echo '<li class="nav-item"><a class="nav-link' . ($tipo === $tipoAtivo ? ' active' : '') . '" href="#" data-crudstatus-tipo="' . $tipo . '"><i class="ti ' . $icone . '"></i> ' . $rot . '</a></li>';
}
echo '</ul>';

foreach ($S::tipos() as $tipo => $rot) {
    $lista = $S::lista($tipo);
    $dadosJs['proximo'][$tipo] = $S::proximoNumero($tipo);
    $dadosJs['listas'][$tipo] = array_map(static fn($s) => ['numero' => $s['numero'], 'nome' => $s['nome']], array_values($lista));
    $alterados = count(array_filter($lista, static fn($s) => $s['alterado']));

    echo '<div class="crudstatus-painel" data-crudstatus-painel="' . $tipo . '"' . ($tipo === $tipoAtivo ? '' : ' style="display:none"') . '>';
    echo '<div class="card crudstatus-card"><div class="card-header crudstatus-cabecalho"><h5><i class="ti ti-circle-dot"></i> Status de ' . mb_strtolower($rot) . ' <span class="crudstatus-contador">' . count($lista) . '</span></h5>';
    echo '<div class="crudstatus-botoes">';
    if ($alterados) {
        echo '<button type="button" class="btn btn-sm btn-outline-secondary" data-crudstatus-acao="restaurar_tipo" data-tipo="' . $tipo . '" data-confirmar="Clique de novo: ' . $alterados . ' status do GLPI voltam ao padrão"><i class="ti ti-arrow-back-up"></i> Restaurar padrão do GLPI</button>';
    }
    echo '<button type="button" class="btn btn-sm crudstatus-btn-principal" data-crudstatus-novo="' . $tipo . '"><i class="ti ti-plus"></i> Novo status</button>';
    echo '</div></div><div class="card-body p-0">';
    echo '<table class="table table-sm table-hover crudstatus-tabela mb-0"><thead><tr><th class="crudstatus-col-ordem">Ordem</th><th>Status</th><th class="text-center">Número</th><th>Chave</th><th>Comportamento</th><th class="text-center">Reabrir</th><th class="text-center">Disponível</th><th class="text-end">Em uso</th><th>Origem</th><th class="text-end">Ações</th></tr></thead><tbody>';
    $ultimo = count($lista) - 1;
    $i = 0;
    foreach ($lista as $n => $s) {
        [$oRot, $oCls] = $origens[$s['origem']];
        $dados = $s + ['tipo' => $tipo];
        echo '<tr data-status="' . $C::e(json_encode($dados, JSON_UNESCAPED_UNICODE)) . '"' . (!$s['disponivel'] ? ' class="crudstatus-indisponivel"' : '') . '>';
        echo '<td class="crudstatus-col-ordem"><span class="crudstatus-botoes">';
        echo '<button type="button" class="btn btn-sm btn-ghost-secondary crudstatus-btn-icone" data-crudstatus-acao="mover" data-direcao="-1" title="Subir"' . ($i === 0 ? ' disabled' : '') . '><i class="ti ti-chevron-up"></i></button>';
        echo '<button type="button" class="btn btn-sm btn-ghost-secondary crudstatus-btn-icone" data-crudstatus-acao="mover" data-direcao="1" title="Descer"' . ($i === $ultimo ? ' disabled' : '') . '><i class="ti ti-chevron-down"></i></button>';
        echo '</span></td>';
        echo '<td><span class="crudstatus-status"><i class="itilstatus ' . $C::e($S::classes($tipo, $s)) . '" style="--status-color:' . $C::e($s['cor']) . ';color:' . $C::e($s['cor']) . '"></i><strong>' . $C::e($s['nome']) . '</strong></span>';
        if ($s['padrao'] && $s['nome'] !== $s['padrao']['nome']) {
            echo '<small class="crudstatus-padrao">padrão: ' . $C::e($s['padrao']['nome']) . '</small>';
        }
        echo '</td>';
        echo '<td class="text-center"><code>' . (int) $n . '</code></td>';
        echo '<td><code>' . $C::e($s['chave'] ?: '—') . '</code></td>';
        echo '<td><span class="crudstatus-pill crudstatus-cat-' . $C::e($s['categoria']) . '">' . $C::e($S::categorias()[$s['categoria']] ?? $s['categoria']) . '</span></td>';
        echo '<td class="text-center">' . ($s['reabrivel'] ? '<i class="ti ti-check crudstatus-ok" title="Pode ser reaberto por um acompanhamento"></i>' : '<span class="crudstatus-fraco">—</span>') . '</td>';
        echo '<td class="text-center"><div class="form-check form-switch crudstatus-switch-tabela"><input class="form-check-input" type="checkbox" data-crudstatus-acao="disponivel"' . ($s['disponivel'] ? ' checked' : '') . ' title="Aparece na lista para escolher"></div></td>';
        echo '<td class="text-end">' . ($s['uso'] > 0 ? '<a href="' . $C::e($S::urlBusca($tipo, $n)) . '">' . number_format($s['uso'], 0, ',', '.') . '</a>' : '<span class="crudstatus-fraco">0</span>') . '</td>';
        echo '<td><span class="crudstatus-pill crudstatus-pill-' . $oCls . '">' . $oRot . '</span>' . ($s['alterado'] ? ' <span class="crudstatus-pill crudstatus-pill-alterado">alterado</span>' : '') . '</td>';
        echo '<td class="text-end"><span class="crudstatus-botoes">';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary crudstatus-btn-icone" data-crudstatus-acao="editar" title="Editar"><i class="ti ti-edit"></i></button>';
        if ($s['alterado']) {
            echo '<button type="button" class="btn btn-sm btn-outline-secondary crudstatus-btn-icone" data-crudstatus-acao="restaurar" data-confirmar="Padrão?" title="Voltar ao padrão do GLPI"><i class="ti ti-arrow-back-up"></i></button>';
        }
        if ($s['origem'] === 'personalizado') {
            echo '<button type="button" class="btn btn-sm btn-outline-danger crudstatus-btn-icone" data-crudstatus-acao="excluir" title="Excluir"><i class="ti ti-trash"></i></button>';
        }
        echo '</span></td></tr>';
        $i++;
    }
    echo '</tbody></table></div></div>';
    echo '</div>';
}

echo '<div class="crudstatus-dicas">';
echo '<p class="text-muted crudstatus-dica"><i class="ti ti-info-circle"></i> O número é o valor gravado no campo status das tabelas do GLPI (glpi_tickets, glpi_problems, glpi_changes). Status novos aparecem sozinhos nas buscas, no kanban, nos painéis e na matriz de ciclo de vida de cada perfil (Administração &gt; Perfis &gt; Ciclo de vida), onde você define de qual status para qual cada perfil pode mudar.</p>';
echo '<p class="text-muted crudstatus-dica"><i class="ti ti-info-circle"></i> O comportamento diz como o GLPI trata o status: Novo e Em andamento entram nas listas "a fazer", Solucionado e Fechado contam como encerrados. A pausa automática do SLA continua valendo só para o "Pendente" do GLPI.</p>';
echo '</div>';

// ------------------------------------------------------------------ modal de edição
echo '<div class="modal fade" id="crudstatus-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content">';
echo '<div class="modal-header"><h5 class="modal-title" id="crudstatus-modal-titulo">Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>';
echo '<div class="modal-body crudstatus-form">';
echo '<input type="hidden" id="crudstatus-f-tipo"><input type="hidden" id="crudstatus-f-original">';
echo '<div class="crudstatus-previa"><span>Prévia</span><span class="crudstatus-status" id="crudstatus-previa"><i class="itilstatus ti ti-circle"></i><strong>Status</strong></span></div>';
echo '<div class="crudstatus-linha"><label class="crudstatus-rotulo" for="crudstatus-f-nome">Nome</label><input type="text" id="crudstatus-f-nome" class="form-control form-control-sm" maxlength="100"><button type="button" class="btn btn-sm btn-outline-secondary" id="crudstatus-f-nome-padrao" title="Usar o nome do GLPI">Padrão</button></div>';
echo '<div class="crudstatus-linha"><label class="crudstatus-rotulo" for="crudstatus-f-numero">Número</label><input type="number" id="crudstatus-f-numero" class="form-control form-control-sm crudstatus-campo-curto" min="1" max="65000">';
echo '<label class="crudstatus-rotulo-curto" for="crudstatus-f-chave">Chave</label><input type="text" id="crudstatus-f-chave" class="form-control form-control-sm crudstatus-campo-medio" maxlength="40" placeholder="gerada pelo nome"></div>';
echo '<div class="crudstatus-linha"><label class="crudstatus-rotulo" for="crudstatus-f-categoria">Comportamento</label><select id="crudstatus-f-categoria" class="form-select form-select-sm crudstatus-campo-medio">';
foreach ($S::categorias() as $k => $v) {
    echo '<option value="' . $k . '">' . $C::e($v) . '</option>';
}
echo '</select><div class="form-check form-switch crudstatus-switch"><input class="form-check-input" type="checkbox" id="crudstatus-f-reabrivel"><label class="form-check-label" for="crudstatus-f-reabrivel">Pode ser reaberto por acompanhamento</label></div></div>';
echo '<div class="crudstatus-linha"><label class="crudstatus-rotulo" for="crudstatus-f-cor">Cor</label><input type="color" id="crudstatus-f-cor" class="form-control form-control-sm form-control-color"><input type="text" id="crudstatus-f-cor-texto" class="form-control form-control-sm crudstatus-campo-curto" maxlength="7">';
echo '<span class="crudstatus-cores" id="crudstatus-cores">';
foreach (['#49bf4d', '#008000', '#ffa500', '#8cabdb', '#1b2f62', '#a72f00', '#6f42c1', '#0d6efd', '#20c997', '#6c757d', '#000000'] as $cor) {
    echo '<button type="button" class="crudstatus-cor" style="background:' . $cor . '" data-cor="' . $cor . '" title="' . $cor . '"></button>';
}
echo '</span><button type="button" class="btn btn-sm btn-outline-secondary" id="crudstatus-f-cor-padrao">Padrão</button></div>';
echo '<div class="crudstatus-linha crudstatus-linha-topo"><label class="crudstatus-rotulo" for="crudstatus-f-busca-icone">Ícone</label><div class="crudstatus-icones"><div class="crudstatus-icones-topo"><input type="text" id="crudstatus-f-busca-icone" class="form-control form-control-sm" placeholder="Buscar ícone (ex.: clock, check, user)"><span class="crudstatus-icone-atual" id="crudstatus-f-icone-nome"></span><button type="button" class="btn btn-sm btn-outline-secondary" id="crudstatus-f-icone-padrao">Padrão</button></div><input type="hidden" id="crudstatus-f-icone"><div class="crudstatus-grade-icones" id="crudstatus-grade-icones"><span class="text-muted">Carregando…</span></div><div class="crudstatus-icones-total" id="crudstatus-icones-total"></div></div></div>';
echo '<div class="crudstatus-linha"><span class="crudstatus-rotulo"></span><div class="form-check form-switch crudstatus-switch"><input class="form-check-input" type="checkbox" id="crudstatus-f-disponivel"><label class="form-check-label" for="crudstatus-f-disponivel">Disponível para escolher</label></div></div>';
echo '<p class="text-muted crudstatus-dica" id="crudstatus-f-dica-nativo"><i class="ti ti-info-circle"></i> Status do GLPI: número, chave e comportamento são fixos para não quebrar a lógica do sistema. Deixar indisponível só tira da lista de escolha (itens que já estão nele continuam).</p>';
echo '</div><div class="modal-footer"><span class="crudstatus-retorno" id="crudstatus-retorno-modal"></span><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-sm crudstatus-btn-principal" id="crudstatus-salvar"><i class="ti ti-device-floppy"></i> Salvar</button></div>';
echo '</div></div></div>';

// ------------------------------------------------------------------ modal de exclusão
echo '<div class="modal fade" id="crudstatus-modal-excluir" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">';
echo '<div class="modal-header crudstatus-modal-perigo"><h5 class="modal-title"><i class="ti ti-trash"></i> Excluir status</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>';
echo '<div class="modal-body"><p id="crudstatus-excluir-texto"></p>';
echo '<div id="crudstatus-excluir-destino-bloco"><div class="crudstatus-alerta crudstatus-alerta-aviso"><i class="ti ti-alert-triangle"></i><div id="crudstatus-excluir-uso"></div></div>';
echo '<div class="crudstatus-linha"><label class="crudstatus-rotulo" for="crudstatus-excluir-destino">Mover para</label><select id="crudstatus-excluir-destino" class="form-select form-select-sm"></select></div>';
echo '<p class="text-muted crudstatus-dica"><i class="ti ti-info-circle"></i> Cada item é atualizado pelo GLPI (fica no histórico), sem enviar notificações.</p></div>';
echo '</div><div class="modal-footer"><span class="crudstatus-retorno" id="crudstatus-retorno-excluir"></span><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-sm btn-danger" id="crudstatus-confirmar-excluir" data-confirmar="Clique de novo para excluir"><i class="ti ti-trash"></i> Excluir</button></div>';
echo '</div></div></div>';

echo '<script type="application/json" id="crudstatus-dados">' . json_encode($dadosJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
echo '</div>';
Html::footer();
