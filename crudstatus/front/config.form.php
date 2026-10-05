<?php

/**
 * Plugin CRUD Status - configuração
 */

include('../../../inc/includes.php');

Session::checkLoginUser();
if (!PluginCrudstatusConfig::podeUsar()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

$C = PluginCrudstatusConfig::class;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_action'])) {
    switch ($_POST['save_action']) {
        case 'salvar_config':
            $C::setConfig('reaplicar_automatico', !empty($_POST['reaplicar_automatico']) ? '1' : '0');
            $C::setConfig('numero_inicial', (string) max(15, min(60000, (int) ($_POST['numero_inicial'] ?? 100))));
            Session::addMessageAfterRedirect('Configuração salva.', false, INFO);
            break;
    }
}

Html::header('Status', $_SERVER['PHP_SELF'], 'config', 'PluginCrudstatusMenu', 'status');
echo '<div class="crudstatus-pagina">';
PluginCrudstatusMenu::barra('config');

echo '<div class="card crudstatus-card"><div class="card-header"><h5><i class="ti ti-settings"></i> Configuração</h5></div><div class="card-body">';
echo '<form method="post" action="' . $C::url('config.form.php') . '"><input type="hidden" name="save_action" value="salvar_config">';
echo '<div class="form-check form-switch crudstatus-switch"><input class="form-check-input" type="checkbox" name="reaplicar_automatico" id="crudstatus-reaplicar" value="1"' . ($C::getConfig('reaplicar_automatico') === '1' ? ' checked' : '') . '><label class="form-check-label" for="crudstatus-reaplicar">Reaplicar a ponte sozinho depois de atualizar o GLPI</label>';
echo '<div class="text-muted crudstatus-dica">A atualização do GLPI troca os arquivos do núcleo e a ponte some. A ação automática CrudstatusPonte (a cada hora) a coloca de volta.</div></div>';
echo '<div class="crudstatus-linha"><label class="crudstatus-rotulo" for="crudstatus-inicial">Primeiro número sugerido</label><input type="number" min="15" max="60000" name="numero_inicial" id="crudstatus-inicial" class="form-control form-control-sm crudstatus-campo-curto" value="' . (int) $C::getConfig('numero_inicial') . '"><span class="text-muted crudstatus-dica-inline">para status novos (longe dos números do GLPI, que vão até 14)</span></div>';
echo '<div class="crudstatus-acoes"><button type="submit" class="btn btn-sm crudstatus-btn-principal"><i class="ti ti-device-floppy"></i> Salvar</button></div>';
Html::closeForm();
echo '</div></div>';

echo '<p class="text-muted crudstatus-dica"><i class="ti ti-info-circle"></i> Acesso restrito a quem pode alterar a configuração do GLPI. Ao desinstalar, a ponte sai do núcleo e as definições ficam guardadas para uma reinstalação.</p>';
echo '</div>';
Html::footer();
