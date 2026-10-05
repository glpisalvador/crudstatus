<?php

/**
 * Plugin CRUD Status - instalação e desinstalação
 * A desinstalação tira a ponte do núcleo mas mantém as tabelas (as definições voltam ao reinstalar).
 */

function plugin_crudstatus_install(): bool
{
    global $DB;

    $t = PluginCrudstatusStatus::TABELA;
    if (!$DB->tableExists($t, false)) {
        $DB->doQuery("CREATE TABLE `$t` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `itemtype` varchar(50) NOT NULL,
            `numero` int unsigned NOT NULL,
            `origem` varchar(20) NOT NULL DEFAULT 'personalizado',
            `nome` varchar(255) NOT NULL DEFAULT '',
            `chave` varchar(60) NOT NULL DEFAULT '',
            `icone` varchar(80) NOT NULL DEFAULT '',
            `cor` varchar(20) NOT NULL DEFAULT '',
            `categoria` varchar(20) NOT NULL DEFAULT 'pendente',
            `reabrivel` tinyint NOT NULL DEFAULT 0,
            `ordem` int NOT NULL DEFAULT 0,
            `disponivel` tinyint NOT NULL DEFAULT 1,
            `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `itemtype_numero` (`itemtype`, `numero`),
            KEY `itemtype` (`itemtype`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC");
    }

    $c = PluginCrudstatusConfig::TABELA;
    if (!$DB->tableExists($c, false)) {
        $DB->doQuery("CREATE TABLE `$c` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `value` text,
            `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC");
    }
    foreach (PluginCrudstatusConfig::padroes() as $nome => $valor) {
        if (count($DB->request(['FROM' => $c, 'WHERE' => ['name' => $nome]])) === 0) {
            $DB->insert($c, ['name' => $nome, 'value' => $valor]);
        }
    }

    PluginCrudstatusConfig::pastas();

    // A ponte é o que faz os status funcionarem: aplicada já na instalação
    $r = PluginCrudstatusNucleo::aplicar();
    if (!$r['ok']) {
        Session::addMessageAfterRedirect('CRUD Status: a ponte do núcleo não pôde ser aplicada (' . $r['mensagem'] . '). Veja Configurar > Status > Integração.', false, WARNING);
    }
    PluginCrudstatusStatus::gerarArquivos();

    CronTask::register('PluginCrudstatusNucleo', 'CrudstatusPonte', HOUR_TIMESTAMP, [
        'mode'    => CronTask::MODE_EXTERNAL,
        'state'   => CronTask::STATE_WAITING,
        'comment' => 'CRUD Status: reaplica a ponte do núcleo depois de uma atualização do GLPI',
    ]);
    return true;
}

/** Tira a ponte do núcleo; tabelas e definições ficam guardadas */
function plugin_crudstatus_uninstall(): bool
{
    PluginCrudstatusNucleo::remover();
    return true;
}
