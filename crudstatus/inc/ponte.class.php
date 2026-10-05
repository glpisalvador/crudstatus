<?php

/**
 * Plugin CRUD Status - ponte chamada pelos métodos de status do núcleo
 * Cada método do núcleo começa com uma linha que chama interceptar(); quando ela devolve null, o método
 * original segue normalmente. Para obter o resultado original, original() marca a chamada e o próprio
 * método do núcleo roda sem desvio.
 */
class PluginCrudstatusPonte
{
    public const TIPOS = ['Ticket', 'Problem', 'Change'];

    /** Método do núcleo => comportamento dos status novos que entram na lista */
    public const LISTAS = [
        'getNewStatusArray'     => 'novo',
        'getProcessStatusArray' => 'andamento',
        'getSolvedStatusArray'  => 'solucionado',
        'getClosedStatusArray'  => 'fechado',
    ];

    private static array $emCurso = [];
    private static ?array $defs = null;

    /** Resultado do método do núcleo sem a interferência do plugin */
    public static function original(string $classe, string $metodo, array $args = [])
    {
        $k = $classe . '::' . $metodo;
        self::$emCurso[$k] = (self::$emCurso[$k] ?? 0) + 1;
        try {
            return $classe::$metodo(...$args);
        } finally {
            self::$emCurso[$k]--;
            if (self::$emCurso[$k] <= 0) {
                unset(self::$emCurso[$k]);
            }
        }
    }

    public static function tipo(string $classe): ?string
    {
        foreach (self::TIPOS as $t) {
            if ($classe === $t || is_a($classe, $t, true)) {
                return $t;
            }
        }
        return null;
    }

    /** Definições gravadas (status novos e ajustes dos nativos) por tipo e número */
    public static function definicoes(?string $tipo = null): array
    {
        global $DB;
        if (self::$defs === null) {
            self::$defs = ['Ticket' => [], 'Problem' => [], 'Change' => []];
            if ($DB && $DB->connected) {
                foreach ($DB->request(['FROM' => PluginCrudstatusStatus::TABELA, 'ORDER' => ['ordem', 'numero']]) as $r) {
                    if (isset(self::$defs[$r['itemtype']])) {
                        self::$defs[$r['itemtype']][(int) $r['numero']] = $r;
                    }
                }
            }
        }
        return $tipo === null ? self::$defs : (self::$defs[$tipo] ?? []);
    }

    public static function limparCache(): void
    {
        self::$defs = null;
    }

    /** Chamado pela linha da ponte no núcleo; null = segue o método original */
    public static function interceptar(string $classe, string $metodo, array $args)
    {
        if (isset(self::$emCurso[$classe . '::' . $metodo])) {
            return null;
        }
        $tipo = self::tipo($classe);
        if ($tipo === null) {
            return null;
        }
        try {
            $defs = self::definicoes($tipo);
            if (!$defs) {
                return null;
            }
            switch ($metodo) {
                case 'getAllStatusArray':
                    return self::lista($classe, $tipo, $defs, (bool) ($args[0] ?? false));
                case 'getAllowedStatusArray':
                    $atual = $args[0] ?? null;
                    $r = self::original($classe, $metodo, $args);
                    foreach ($r as $n => $rot) {
                        if (isset($defs[(int) $n]) && !(int) $defs[(int) $n]['disponivel'] && (string) $n !== (string) $atual) {
                            unset($r[$n]);
                        }
                    }
                    return $r;
                case 'getReopenableStatusArray':
                    $r = self::original($classe, $metodo, $args);
                    foreach ($defs as $n => $d) {
                        if ($d['origem'] === 'personalizado' && (int) $d['reabrivel']) {
                            $r[] = $n;
                        }
                    }
                    return array_values(array_unique($r));
                case 'getStatusClass':
                    return self::classe($classe, $tipo, $defs, $args[0] ?? null);
                case 'getStatusKey':
                    $n = $args[0] ?? null;
                    if (is_numeric($n) && isset($defs[(int) $n]) && $defs[(int) $n]['origem'] === 'personalizado') {
                        return (string) $defs[(int) $n]['chave'];
                    }
                    return null;
            }
            if (isset(self::LISTAS[$metodo])) {
                $r = self::original($classe, $metodo, $args);
                foreach ($defs as $n => $d) {
                    if ($d['origem'] === 'personalizado' && $d['categoria'] === self::LISTAS[$metodo]) {
                        $r[] = $n;
                    }
                }
                return array_values(array_unique($r));
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    private static function lista(string $classe, string $tipo, array $defs, bool $meta): array
    {
        $orig = self::original($classe, 'getAllStatusArray', [$meta]);
        $itens = [];
        $extras = [];
        $pos = 0;
        foreach ($orig as $n => $rot) {
            if (!is_int($n)) {
                $extras[$n] = $rot;
                continue;
            }
            $pos++;
            $d = $defs[$n] ?? null;
            $itens[$n] = [
                'nome'  => ($d && trim((string) $d['nome']) !== '') ? (string) $d['nome'] : $rot,
                'ordem' => $d ? (int) $d['ordem'] : $pos * 10,
                'pos'   => $pos,
            ];
        }
        foreach ($defs as $n => $d) {
            if ($d['origem'] === 'personalizado' && !isset($itens[$n])) {
                $pos++;
                $itens[$n] = ['nome' => (string) $d['nome'], 'ordem' => (int) $d['ordem'], 'pos' => $pos];
            }
        }
        uasort($itens, static fn($a, $b) => [$a['ordem'], $a['pos']] <=> [$b['ordem'], $b['pos']]);
        $r = [];
        foreach ($itens as $n => $i) {
            $r[$n] = $i['nome'];
        }
        return $r + $extras;
    }

    private static function classe(string $classe, string $tipo, array $defs, $status): ?string
    {
        if (!is_numeric($status) || !isset($defs[(int) $status])) {
            return null;
        }
        $n = (int) $status;
        $d = $defs[$n];
        $icone = trim((string) $d['icone']);
        if ($d['origem'] !== 'personalizado') {
            if ($icone === '' && trim((string) $d['cor']) === '') {
                return null;
            }
            if ($icone === '') {
                $icone = self::iconeOriginal($classe, $n);
            }
            $chave = (string) self::original($classe, 'getStatusKey', [$n]);
        } else {
            $chave = (string) $d['chave'];
        }
        if ($icone === '') {
            $icone = 'circle';
        }
        return 'itilstatus ti ti-' . $icone . ' ' . $chave . ' crudstatus-' . strtolower($tipo) . '-' . $n;
    }

    /** Ícone padrão do núcleo para um status ("circle-filled", "calendar"...) */
    public static function iconeOriginal(string $classe, int $n): string
    {
        $c = (string) self::original($classe, 'getStatusClass', [$n]);
        return preg_match('/\bti-([a-z0-9-]+)/', $c, $m) ? $m[1] : '';
    }
}
