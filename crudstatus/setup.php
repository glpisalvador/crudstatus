<?php

/**
 * Plugin CRUD Status - GLPI 11 e 12
 * Lê e edita os status nativos de chamados, problemas e mudanças (nome, ícone, cor, ordem, disponibilidade),
 * cria status novos com número próprio gravado nas tabelas nativas e volta ao padrão quando quiser.
 * O GLPI não tem hook de status: o plugin insere uma linha de "ponte" marcada no início dos métodos de status
 * do núcleo (com backup, reaplicação depois de atualizar o GLPI e remoção pela tela).
 */

define('PLUGIN_CRUDSTATUS_VERSION', '3.0.0');
define('PLUGIN_CRUDSTATUS_MIN_GLPI', '11.0.0');
define('PLUGIN_CRUDSTATUS_MAX_GLPI', '12.99.99');

function plugin_init_crudstatus(): void
{
    global $PLUGIN_HOOKS;

    // Chave literal: a constante Hooks::CSRF_COMPLIANT não existe no GLPI 12
    $PLUGIN_HOOKS['csrf_compliant']['crudstatus'] = true;

    $plugin = new Plugin();
    if (!$plugin->isActivated('crudstatus')) {
        return;
    }

    // Liga a ponte do núcleo: sem esta constante as linhas inseridas no núcleo não fazem nada
    if (!defined('PLUGIN_CRUDSTATUS_PONTE')) {
        define('PLUGIN_CRUDSTATUS_PONTE', true);
    }

    Plugin::registerClass('PluginCrudstatusMenu');
    Plugin::registerClass('PluginCrudstatusNucleo');

    $PLUGIN_HOOKS['config_page']['crudstatus'] = 'front/config.form.php';

    // Cores e ícones (inclusive no dropdown de status) para todos: o parâmetro "r" renova o cache a cada mudança
    $rev = (string) PluginCrudstatusStatus::revisao();
    $PLUGIN_HOOKS['add_css']['crudstatus'] = ['css/definicoes.css?r=' . $rev];
    $PLUGIN_HOOKS['add_javascript']['crudstatus'] = ['js/definicoes.js?r=' . $rev];

    if (Session::getLoginUserID() && PluginCrudstatusConfig::podeUsar()) {
        $PLUGIN_HOOKS['menu_toadd']['crudstatus'] = ['config' => 'PluginCrudstatusMenu'];
        $PLUGIN_HOOKS['add_css']['crudstatus'][] = 'css/crudstatus.css';
        $PLUGIN_HOOKS['add_javascript']['crudstatus'][] = 'js/crudstatus.js';
    }
}

function plugin_version_crudstatus(): array
{
    return [
        'name'         => 'CRUD Status',
        'version'      => PLUGIN_CRUDSTATUS_VERSION,
        'author'       => 'GLPI Salvador',
        'license'      => 'GPLv2+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_CRUDSTATUS_MIN_GLPI,
                'max' => PLUGIN_CRUDSTATUS_MAX_GLPI,
            ],
            'php'  => ['min' => '8.1'],
        ],
    ];
}

function plugin_crudstatus_check_prerequisites(): bool
{
    return version_compare(GLPI_VERSION, PLUGIN_CRUDSTATUS_MIN_GLPI, '>=');
}

function plugin_crudstatus_check_config($verbose = false): bool
{
    return true;
}
