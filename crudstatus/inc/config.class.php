<?php

/**
 * Plugin CRUD Status - configurações e utilitários
 */
class PluginCrudstatusConfig extends CommonDBTM
{
    // $rightname não é redeclarada: é tipada (string) no GLPI 12 e sem tipo no 11.

    public const TABELA = 'glpi_plugin_crudstatus_configs';
    public const PLUGIN = 'crudstatus';

    public static function getTypeName($nb = 0): string
    {
        return 'Status';
    }

    public static function getTable($classname = null)
    {
        return self::TABELA;
    }

    public static function canView(): bool
    {
        return self::podeUsar();
    }

    public static function canCreate(): bool
    {
        return self::podeUsar();
    }

    public static function canUpdate(): bool
    {
        return self::podeUsar();
    }

    public static function canDelete(): bool
    {
        return self::podeUsar();
    }

    public static function canPurge(): bool
    {
        return self::podeUsar();
    }

    /** Mexe no núcleo e no ciclo de vida dos chamados: só quem administra o GLPI */
    public static function podeUsar(): bool
    {
        return (bool) Session::getLoginUserID() && Session::haveRight('config', UPDATE);
    }

    public static function padroes(): array
    {
        return [
            'reaplicar_automatico' => '1',
            'numero_inicial'       => '100',
            'ultima_acao'          => '',
        ];
    }

    private static ?array $cache = null;

    public static function getConfig(string $name, $default = null)
    {
        global $DB;
        if (self::$cache === null) {
            self::$cache = [];
            if ($DB->tableExists(self::TABELA)) {
                foreach ($DB->request(['SELECT' => ['name', 'value'], 'FROM' => self::TABELA]) as $row) {
                    self::$cache[$row['name']] = $row['value'];
                }
            }
        }
        return self::$cache[$name] ?? ($default ?? (self::padroes()[$name] ?? null));
    }

    public static function setConfig(string $name, $value): bool
    {
        global $DB;
        if (count($DB->request(['FROM' => self::TABELA, 'WHERE' => ['name' => $name], 'LIMIT' => 1])) > 0) {
            $ok = (bool) $DB->update(self::TABELA, ['value' => $value], ['name' => $name]);
        } else {
            $ok = (bool) $DB->insert(self::TABELA, ['name' => $name, 'value' => $value]);
        }
        self::$cache = null;
        return $ok;
    }

    public static function getAllConfigs(): array
    {
        global $DB;
        $todas = [];
        foreach ($DB->request(['FROM' => self::TABELA]) as $row) {
            $todas[$row['name']] = $row['value'];
        }
        return $todas;
    }

    public static function getArrayConfig(string $name): array
    {
        $v = json_decode((string) self::getConfig($name), true);
        return is_array($v) ? $v : [];
    }

    public static function setArrayConfig(string $name, array $value): bool
    {
        return self::setConfig($name, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Pasta dos backups do núcleo e do registro de alterações */
    public static function pasta(string $sub = ''): string
    {
        $base = GLPI_PLUGIN_DOC_DIR . '/' . self::PLUGIN;
        return $sub === '' ? $base : $base . '/' . $sub;
    }

    public static function pastas(): void
    {
        foreach (['', 'nucleo'] as $s) {
            if (!is_dir(self::pasta($s))) {
                @mkdir(self::pasta($s), 0750, true);
            }
        }
        if (!is_file(self::pasta('.htaccess'))) {
            @file_put_contents(self::pasta('.htaccess'), "Require all denied\nOrder Deny,Allow\nDeny from all\n");
        }
    }

    public static function registrar(string $mensagem): void
    {
        self::pastas();
        $quem = Session::getLoginUserID() ? getUserName((int) Session::getLoginUserID()) : 'sistema';
        @file_put_contents(self::pasta('registro.log'), '[' . date('Y-m-d H:i:s') . '] ' . $quem . ': ' . $mensagem . "\n", FILE_APPEND);
    }

    public static function ultimosRegistros(int $n = 30): array
    {
        $arq = self::pasta('registro.log');
        if (!is_file($arq)) {
            return [];
        }
        $linhas = array_filter(explode("\n", (string) file_get_contents($arq)));
        return array_reverse(array_slice($linhas, -$n));
    }

    /** Executa um comando e devolve [código, saída, erro] */
    public static function executar(array $cmd): array
    {
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($p)) {
            return [127, '', 'não foi possível executar'];
        }
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($p), $out, $err];
    }

    public static function phpCli(): string
    {
        $v = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        foreach (['/usr/bin/php' . $v, '/usr/local/bin/php' . $v, '/usr/bin/php', '/usr/local/bin/php'] as $p) {
            if (is_executable($p)) {
                return $p;
            }
        }
        return (PHP_SAPI === 'cli' && PHP_BINARY !== '') ? PHP_BINARY : 'php';
    }

    public static function e($texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }

    public static function url(string $arquivo, array $params = []): string
    {
        global $CFG_GLPI;
        return $CFG_GLPI['root_doc'] . '/plugins/crudstatus/front/' . $arquivo . ($params ? '?' . http_build_query($params) : '');
    }

    public static function tokenCsrf(): string
    {
        return (version_compare(GLPI_VERSION, '12.0.0-dev', '<') && session_status() === PHP_SESSION_ACTIVE) ? Session::getNewCSRFToken() : '';
    }
}
