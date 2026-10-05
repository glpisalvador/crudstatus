<?php

/**
 * Plugin CRUD Status - integração com o núcleo: situação da ponte por arquivo e método, aplicar e remover
 */

include('../../../inc/includes.php');

Session::checkLoginUser();
if (!PluginCrudstatusConfig::podeUsar()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

$C = PluginCrudstatusConfig::class;
$N = PluginCrudstatusNucleo::class;
$e = $N::estado();

Html::header('Integração com o núcleo', $_SERVER['PHP_SELF'], 'config', 'PluginCrudstatusMenu', 'nucleo');
echo '<div class="crudstatus-pagina" id="crudstatus-nucleo">';
PluginCrudstatusMenu::barra('nucleo');

$cls = $e['completa'] ? 'ok' : ($e['nenhuma'] ? 'erro' : 'aviso');
$txt = $e['completa'] ? 'Ponte aplicada' : ($e['nenhuma'] ? 'Ponte não aplicada' : 'Ponte incompleta');
echo '<div class="card crudstatus-card"><div class="card-header crudstatus-cabecalho"><h5><i class="ti ti-plug-connected"></i> Situação</h5><span class="crudstatus-pill crudstatus-pill-' . $cls . '">' . $txt . ' · ' . $e['aplicados'] . '/' . $e['esperados'] . '</span></div><div class="card-body">';
echo '<p class="text-muted crudstatus-dica"><i class="ti ti-info-circle"></i> O GLPI não tem como um plugin acrescentar status. Por isso o plugin põe uma única linha marcada (<code>' . $C::e($N::MARCA) . '</code>) no início de cada método de status do núcleo. O resto dos arquivos não é tocado, inclusive alterações de outros plugins. Com o plugin desativado essa linha não faz nada.</p>';
echo '<table class="crudstatus-dados"><tr><th>GLPI</th><td>' . $C::e($e['versao']) . '</td></tr>';
echo '<tr><th>Aplicada na versão</th><td>' . $C::e($e['aplicada_em_versao'] ?: '—') . '</td></tr>';
echo '<tr><th>Reaplicar sozinho</th><td>' . ($C::getConfig('reaplicar_automatico') === '1' ? 'sim, pela ação automática <strong>CrudstatusPonte</strong> (a cada hora), depois de uma atualização do GLPI' : 'não') . '</td></tr>';
if ($e['botoesadicionais']) {
    echo '<tr><th>Outras alterações</th><td>O núcleo tem os status 50–52 adicionados por outro plugin (botoesadicionais). Eles aparecem como "Núcleo modificado" e são preservados.</td></tr>';
}
echo '<tr><th>Backups</th><td><code>' . $C::e($C::pasta('nucleo')) . '</code> (arquivo original de cada versão do GLPI)</td></tr></table>';
echo '<div class="crudstatus-acoes">';
if (!$e['completa']) {
    echo '<button type="button" class="btn btn-sm crudstatus-btn-principal" data-crudstatus-nucleo="aplicar_ponte"><i class="ti ti-plug-connected"></i> ' . ($e['nenhuma'] ? 'Aplicar ponte' : 'Completar ponte') . '</button>';
}
if (!$e['nenhuma']) {
    echo '<button type="button" class="btn btn-sm btn-outline-danger" data-crudstatus-nucleo="remover_ponte" data-confirmar="Clique de novo: os status voltam ao padrão do GLPI e os novos deixam de aparecer"><i class="ti ti-plug-connected-x"></i> Remover ponte</button>';
}
echo '<span class="crudstatus-retorno" id="crudstatus-retorno"></span></div>';
echo '</div></div>';

echo '<div class="card crudstatus-card"><div class="card-header"><h5><i class="ti ti-file-code"></i> Arquivos do núcleo</h5></div><div class="card-body p-0">';
echo '<table class="table table-sm crudstatus-tabela mb-0"><thead><tr><th>Arquivo</th><th>Métodos</th><th class="text-center">Gravável</th><th class="text-center">Backup</th></tr></thead><tbody>';
foreach ($e['arquivos'] as $a) {
    echo '<tr><td><code>' . $C::e($a['arquivo']) . '</code></td><td class="crudstatus-metodos">';
    foreach ($a['metodos'] as $m => $s) {
        $c = ['aplicada' => 'ok', 'ausente' => 'aviso', 'inexistente' => 'neutro'][$s];
        $t = ['aplicada' => 'com ponte', 'ausente' => 'sem ponte', 'inexistente' => 'não existe nesta versão'][$s];
        echo '<span class="crudstatus-pill crudstatus-pill-' . $c . '" title="' . $t . '">' . $C::e($m) . '</span>';
    }
    echo '</td><td class="text-center">' . ($a['gravavel'] ? '<i class="ti ti-check crudstatus-ok"></i>' : '<i class="ti ti-x crudstatus-erro" title="O servidor web não consegue gravar este arquivo"></i>') . '</td>';
    echo '<td class="text-center">' . ($a['backup'] ? '<i class="ti ti-check crudstatus-ok"></i>' : '<span class="crudstatus-fraco">—</span>') . '</td></tr>';
}
echo '</tbody></table></div></div>';

$reg = $C::ultimosRegistros(30);
echo '<div class="card crudstatus-card"><div class="card-header"><h5><i class="ti ti-history"></i> Últimas alterações</h5></div><div class="card-body p-0">';
if (!$reg) {
    echo '<p class="text-muted crudstatus-vazio"><i class="ti ti-mood-empty"></i> Nada registrado ainda.</p>';
} else {
    echo '<ul class="crudstatus-registro">';
    foreach ($reg as $l) {
        echo '<li>' . $C::e($l) . '</li>';
    }
    echo '</ul>';
}
echo '</div></div>';

echo '</div>';
Html::footer();
