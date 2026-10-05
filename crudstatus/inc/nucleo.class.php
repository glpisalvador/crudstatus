<?php

/**
 * Plugin CRUD Status - integração com o núcleo do GLPI
 * Insere uma linha marcada ("// crudstatus:ponte") no início dos métodos de status, sem tocar no resto do
 * arquivo (preserva alterações de outros plugins, como os status 50–52 do botoesadicionais). Antes de gravar:
 * backup do arquivo limpo por versão do GLPI, verificação com php -l e troca atômica.
 */
class PluginCrudstatusNucleo extends CommonGLPI
{
    public const MARCA = '// crudstatus:ponte';

    public const ALVOS = [
        'src/CommonITILObject.php' => ['getReopenableStatusArray', 'getAllStatusArray', 'getClosedStatusArray', 'getSolvedStatusArray', 'getNewStatusArray', 'getProcessStatusArray', 'getAllowedStatusArray', 'getStatusClass', 'getStatusKey'],
        'src/Ticket.php'           => ['getAllStatusArray', 'getClosedStatusArray', 'getSolvedStatusArray', 'getNewStatusArray', 'getProcessStatusArray'],
        'src/Problem.php'          => ['getAllStatusArray', 'getClosedStatusArray', 'getSolvedStatusArray', 'getNewStatusArray', 'getProcessStatusArray'],
        'src/Change.php'           => ['getAllStatusArray', 'getClosedStatusArray', 'getSolvedStatusArray', 'getNewStatusArray', 'getProcessStatusArray', 'getReopenableStatusArray', 'getStatusKey'],
    ];

    public static function getTypeName($nb = 0): string
    {
        return 'Integração com o núcleo';
    }

    public static function canView(): bool
    {
        return PluginCrudstatusConfig::podeUsar();
    }

    public static function linha(): string
    {
        return "if (\\defined('PLUGIN_CRUDSTATUS_PONTE') && \\class_exists('PluginCrudstatusPonte') && (\$crudstatusPonte = \\PluginCrudstatusPonte::interceptar(static::class, __FUNCTION__, \\func_get_args())) !== null) { return \$crudstatusPonte; } " . self::MARCA;
    }

    private static function regexMetodo(string $m): string
    {
        return '/(public\s+static\s+function\s+' . preg_quote($m, '/') . '\s*\([^)]*\)\s*(?::\s*[?\\\\\w|]+\s*)?\{)[ \t]*\r?\n/';
    }

    private static function temPonte(string $conteudo, string $m): bool
    {
        return (bool) preg_match('/function\s+' . preg_quote($m, '/') . '\s*\([^)]*\)[^{]*\{\s*\r?\n[^\n]*' . preg_quote(self::MARCA, '/') . '/', $conteudo);
    }

    private static function temMetodo(string $conteudo, string $m): bool
    {
        return (bool) preg_match(self::regexMetodo($m), $conteudo);
    }

    public static function limpar(string $conteudo): string
    {
        return (string) preg_replace('/^[^\n]*' . preg_quote(self::MARCA, '/') . '[^\n]*\r?\n/m', '', $conteudo);
    }

    /** Situação de cada arquivo e método */
    public static function estado(): array
    {
        $arquivos = [];
        $esperados = 0;
        $aplicados = 0;
        foreach (self::ALVOS as $rel => $metodos) {
            $caminho = GLPI_ROOT . '/' . $rel;
            $conteudo = is_file($caminho) ? (string) file_get_contents($caminho) : '';
            $info = ['arquivo' => $rel, 'existe' => $conteudo !== '', 'gravavel' => is_writable($caminho) && is_writable(dirname($caminho)), 'metodos' => [], 'backup' => is_file(self::backup($rel))];
            foreach ($metodos as $m) {
                $existe = $conteudo !== '' && self::temMetodo($conteudo, $m);
                $tem = $existe && self::temPonte($conteudo, $m);
                $info['metodos'][$m] = $existe ? ($tem ? 'aplicada' : 'ausente') : 'inexistente';
                if ($existe) {
                    $esperados++;
                    $aplicados += $tem ? 1 : 0;
                }
            }
            $arquivos[] = $info;
        }
        return [
            'arquivos'  => $arquivos,
            'esperados' => $esperados,
            'aplicados' => $aplicados,
            'completa'  => $esperados > 0 && $aplicados === $esperados,
            'nenhuma'   => $aplicados === 0,
            'versao'    => GLPI_VERSION,
            'aplicada_em_versao' => (string) PluginCrudstatusConfig::getConfig('ponte_versao', ''),
            'botoesadicionais'   => defined('CommonITILObject::AGUARDANDOAPROVACAO'),
        ];
    }

    public static function backup(string $rel): string
    {
        return PluginCrudstatusConfig::pasta('nucleo/' . preg_replace('/[^0-9A-Za-z._-]/', '', GLPI_VERSION) . '/' . basename($rel) . '.orig');
    }

    /** Grava um arquivo do núcleo com segurança (php -l antes da troca) */
    private static function gravar(string $caminho, string $novo): ?string
    {
        $tmp = $caminho . '.crudstatus.tmp';
        if (@file_put_contents($tmp, $novo) === false) {
            return 'sem permissão de escrita em ' . dirname($caminho);
        }
        [$cod, $out, $err] = PluginCrudstatusConfig::executar([PluginCrudstatusConfig::phpCli(), '-l', $tmp]);
        if ($cod !== 0) {
            @unlink($tmp);
            return 'o arquivo gerado não passou na verificação do PHP: ' . trim($err . ' ' . $out);
        }
        @chmod($tmp, fileperms($caminho) & 0777);
        if (!@rename($tmp, $caminho)) {
            @unlink($tmp);
            return 'não foi possível substituir ' . basename($caminho);
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($caminho, true);
        }
        return null;
    }

    /** @return array{ok: bool, mensagem: string, alterados: int} */
    public static function aplicar(): array
    {
        PluginCrudstatusConfig::pastas();
        $alterados = 0;
        $erros = [];
        foreach (self::ALVOS as $rel => $metodos) {
            $caminho = GLPI_ROOT . '/' . $rel;
            if (!is_file($caminho)) {
                $erros[] = $rel . ' não encontrado';
                continue;
            }
            $conteudo = (string) file_get_contents($caminho);
            $novo = $conteudo;
            foreach ($metodos as $m) {
                if (!self::temMetodo($novo, $m) || self::temPonte($novo, $m)) {
                    continue;
                }
                $novo = (string) preg_replace(self::regexMetodo($m), "$1\n        " . str_replace(['\\', '$'], ['\\\\', '\\$'], self::linha()) . "\n", $novo, 1);
            }
            if ($novo === $conteudo) {
                continue;
            }
            $bk = self::backup($rel);
            if (!is_file($bk)) {
                @mkdir(dirname($bk), 0750, true);
                @file_put_contents($bk, self::limpar($conteudo));
            }
            if ($e = self::gravar($caminho, $novo)) {
                $erros[] = $rel . ': ' . $e;
                continue;
            }
            $alterados++;
        }
        $est = self::estado();
        if ($est['completa']) {
            PluginCrudstatusConfig::setConfig('ponte_versao', GLPI_VERSION);
        }
        if ($alterados > 0) {
            PluginCrudstatusConfig::registrar('ponte aplicada em ' . $alterados . ' arquivo(s) do núcleo (GLPI ' . GLPI_VERSION . ')');
        }
        if ($erros) {
            return ['ok' => false, 'mensagem' => implode('; ', $erros), 'alterados' => $alterados];
        }
        return ['ok' => $est['completa'], 'mensagem' => $est['completa'] ? 'Ponte aplicada em ' . $est['aplicados'] . ' método(s).' : 'A ponte ficou incompleta.', 'alterados' => $alterados];
    }

    /** @return array{ok: bool, mensagem: string} */
    public static function remover(): array
    {
        $erros = [];
        $n = 0;
        foreach (array_keys(self::ALVOS) as $rel) {
            $caminho = GLPI_ROOT . '/' . $rel;
            if (!is_file($caminho)) {
                continue;
            }
            $conteudo = (string) file_get_contents($caminho);
            $limpo = self::limpar($conteudo);
            if ($limpo === $conteudo) {
                continue;
            }
            if ($e = self::gravar($caminho, $limpo)) {
                $erros[] = $rel . ': ' . $e;
                continue;
            }
            $n++;
        }
        PluginCrudstatusConfig::setConfig('ponte_versao', '');
        if ($n > 0) {
            PluginCrudstatusConfig::registrar('ponte removida de ' . $n . ' arquivo(s) do núcleo');
        }
        return $erros ? ['ok' => false, 'mensagem' => implode('; ', $erros)] : ['ok' => true, 'mensagem' => 'Ponte removida: o núcleo voltou ao comportamento original.'];
    }

    // ------------------------------------------------------------------ ação automática

    public static function cronInfo($name): array
    {
        return ['description' => 'Reaplica a ponte de status no núcleo depois de uma atualização do GLPI'];
    }

    public static function cronCrudstatusPonte($task): int
    {
        if (PluginCrudstatusConfig::getConfig('reaplicar_automatico') !== '1') {
            return 0;
        }
        $est = self::estado();
        if ($est['completa']) {
            return 0;
        }
        $r = self::aplicar();
        PluginCrudstatusStatus::gerarArquivos();
        if ($task) {
            $task->log($r['mensagem']);
        }
        return $r['ok'] ? 1 : 0;
    }
}
