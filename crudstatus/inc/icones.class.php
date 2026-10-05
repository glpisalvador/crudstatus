<?php

/**
 * Plugin CRUD Status - ícones Tabler disponíveis no GLPI (lidos da própria biblioteca instalada)
 */
class PluginCrudstatusIcones
{
    /** Usados quando a biblioteca não puder ser lida */
    public const BASICOS = ['circle', 'circle-filled', 'circle-check', 'circle-check-filled', 'circle-x', 'circle-dot', 'calendar', 'clock', 'hourglass', 'eye', 'help', 'ban', 'check', 'x', 'alert-triangle', 'player-pause', 'player-play', 'flag', 'star', 'lock', 'user-check', 'message', 'send', 'tool', 'truck', 'package', 'shield-check', 'thumb-up', 'thumb-down', 'refresh', 'arrow-up', 'loader'];

    public static function lista(): array
    {
        $cache = PluginCrudstatusConfig::pasta('icones.json');
        $fontes = array_merge(
            // GLPI 11 e 12: os ícones vêm junto do tema em public/lib/tabler.css
            is_file(GLPI_ROOT . '/public/lib/tabler.css') ? [GLPI_ROOT . '/public/lib/tabler.css'] : (is_file(GLPI_ROOT . '/public/lib/tabler.min.css') ? [GLPI_ROOT . '/public/lib/tabler.min.css'] : []),
            glob(GLPI_ROOT . '/public/lib/tabler/icons-webfont/dist/*.css') ?: [],
            glob(GLPI_ROOT . '/public/lib/tabler/icons-webfont/*.css') ?: [],
            glob(GLPI_ROOT . '/public/lib/tabler/icons-webfont/dist/*/*.css') ?: []
        );
        $mtime = 0;
        foreach ($fontes as $f) {
            $mtime = max($mtime, (int) filemtime($f));
        }
        if (is_file($cache) && filemtime($cache) >= $mtime) {
            $l = json_decode((string) file_get_contents($cache), true);
            if (is_array($l) && $l) {
                return array_map('strval', $l);
            }
        }
        $nomes = [];
        foreach ($fontes as $f) {
            if (preg_match_all('/\.ti-([a-z0-9-]+):+before/', (string) file_get_contents($f), $m)) {
                foreach ($m[1] as $n) {
                    $nomes[$n] = true;
                }
            }
        }
        // Nomes só com números ("123", "360") viram inteiros como chave de array: volta para texto
        $lista = array_map('strval', array_keys($nomes));
        sort($lista, SORT_STRING);
        if (!$lista) {
            return self::BASICOS;
        }
        PluginCrudstatusConfig::pastas();
        @file_put_contents($cache, json_encode($lista));
        return $lista;
    }
}
