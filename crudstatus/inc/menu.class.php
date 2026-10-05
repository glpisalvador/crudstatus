<?php

/**
 * Plugin CRUD Status - menu em Configurar
 */
class PluginCrudstatusMenu extends CommonGLPI
{
    public static function getTypeName($nb = 0): string
    {
        return 'Status';
    }

    public static function getMenuName(): string
    {
        return 'Status de chamados';
    }

    public static function getIcon(): string
    {
        return 'ti ti-circle-dot';
    }

    public static function canView(): bool
    {
        return PluginCrudstatusConfig::podeUsar();
    }

    public static function getMenuContent(): array
    {
        if (!self::canView()) {
            return [];
        }
        $base = '/plugins/crudstatus/front/';
        return [
            'title'   => self::getMenuName(),
            'page'    => $base . 'status.php',
            'icon'    => self::getIcon(),
            'options' => [
                'status' => ['title' => 'Status', 'page' => $base . 'status.php', 'icon' => 'ti ti-circle-dot'],
                'nucleo' => ['title' => 'Integração', 'page' => $base . 'nucleo.php', 'icon' => 'ti ti-plug-connected'],
            ],
        ];
    }

    public static function barra(string $atual): void
    {
        $itens = [
            'status' => ['Status', 'ti ti-circle-dot', 'status.php'],
            'nucleo' => ['Integração com o núcleo', 'ti ti-plug-connected', 'nucleo.php'],
            'config' => ['Configuração', 'ti ti-settings', 'config.form.php'],
        ];
        echo '<ul class="nav nav-tabs crudstatus-nav">';
        foreach ($itens as $k => [$t, $i, $arq]) {
            echo '<li class="nav-item"><a class="nav-link' . ($k === $atual ? ' active' : '') . '" href="' . PluginCrudstatusConfig::url($arq) . '"><i class="' . $i . '"></i> ' . $t . '</a></li>';
        }
        echo '</ul>';
    }

    /** Aviso quando a ponte não está completa */
    public static function avisoPonte(): void
    {
        $e = PluginCrudstatusNucleo::estado();
        if ($e['completa']) {
            return;
        }
        echo '<div class="crudstatus-alerta crudstatus-alerta-aviso"><i class="ti ti-alert-triangle"></i><div><strong>A ponte com o núcleo não está ' . ($e['nenhuma'] ? 'aplicada' : 'completa') . ' (' . $e['aplicados'] . ' de ' . $e['esperados'] . ').</strong> Sem ela, nomes, ícones e status novos não aparecem no GLPI. Isso acontece depois de atualizar o GLPI.</div>';
        echo '<a class="btn btn-sm btn-outline-secondary" href="' . PluginCrudstatusConfig::url('nucleo.php') . '">Ver integração</a></div>';
    }
}
