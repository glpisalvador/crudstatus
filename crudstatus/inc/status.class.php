<?php

/**
 * Plugin CRUD Status - catálogo dos status (nativos lidos do núcleo + novos), edição, exclusão com destino
 * dos itens, ordem, disponibilidade, volta ao padrão e geração das cores/ícones usados pelo navegador.
 */
class PluginCrudstatusStatus
{
    public const TABELA = 'glpi_plugin_crudstatus_status';

    /** Status que vêm de fábrica no GLPI (o resto que aparecer no núcleo veio de alterações locais) */
    public const DE_FABRICA = [
        'Ticket'  => [1, 2, 3, 4, 5, 6, 10],
        'Problem' => [1, 2, 3, 4, 5, 6, 7, 8],
        'Change'  => [1, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14],
    ];

    public static function tipos(): array
    {
        return ['Ticket' => 'Chamados', 'Problem' => 'Problemas', 'Change' => 'Mudanças'];
    }

    public static function tabelaDoTipo(string $tipo): string
    {
        return ['Ticket' => 'glpi_tickets', 'Problem' => 'glpi_problems', 'Change' => 'glpi_changes'][$tipo];
    }

    public static function categorias(): array
    {
        return [
            'novo'        => 'Novo',
            'andamento'   => 'Em andamento',
            'pendente'    => 'Em espera (não solucionado)',
            'solucionado' => 'Solucionado',
            'fechado'     => 'Fechado',
        ];
    }

    /** Cores do núcleo (css/includes/components/itilobject/_status.scss) */
    public static function corPadrao(string $chave): string
    {
        return [
            'new' => '#49bf4d', 'assigned' => '#49bf4d', 'accepted' => '#008000', 'refused' => '#a72f00',
            'test' => '#ffa500', 'qualif' => '#ffa500', 'waiting' => '#ffa500', 'approval' => '#8cabdb',
            'eval' => '#add8e6', 'closed' => '#000000', 'solved' => '#000000', 'observe' => '#000000',
            'canceled' => '#000000', 'planned' => '#1b2f62',
        ][$chave] ?? '#6c757d';
    }

    public static function valido(string $tipo): bool
    {
        return array_key_exists($tipo, self::tipos());
    }

    // =====================================================================
    // Catálogo
    // =====================================================================

    /** Status que existem no núcleo (sem os ajustes do plugin), com os valores padrão de cada elemento */
    public static function nativos(string $tipo): array
    {
        $P = PluginCrudstatusPonte::class;
        $lista = $P::original($tipo, 'getAllStatusArray', [false]);
        $cat = [];
        foreach (PluginCrudstatusPonte::LISTAS as $metodo => $c) {
            foreach ((array) $P::original($tipo, $metodo) as $n) {
                $cat[(int) $n] ??= $c;
            }
        }
        $reabre = array_map('intval', (array) $P::original($tipo, 'getReopenableStatusArray'));
        $r = [];
        $pos = 0;
        foreach ($lista as $n => $nome) {
            if (!is_int($n)) {
                continue;
            }
            $pos++;
            $chave = (string) $P::original($tipo, 'getStatusKey', [$n]);
            $r[$n] = [
                'numero'    => $n,
                'nome'      => (string) $nome,
                'chave'     => $chave,
                'icone'     => $P::iconeOriginal($tipo, $n) ?: 'circle',
                'cor'       => self::corPadrao($chave),
                'categoria' => $cat[$n] ?? 'pendente',
                'reabrivel' => in_array($n, $reabre, true) ? 1 : 0,
                'ordem'     => $pos * 10,
                'disponivel' => 1,
                'origem'    => in_array($n, self::DE_FABRICA[$tipo], true) ? 'nativo' : 'nucleo',
            ];
        }
        return $r;
    }

    /** Lista efetiva de um tipo (como o GLPI mostra), com os padrões ao lado */
    public static function lista(string $tipo, bool $comUso = true): array
    {
        PluginCrudstatusPonte::limparCache();
        $defs = PluginCrudstatusPonte::definicoes($tipo);
        $itens = [];
        foreach (self::nativos($tipo) as $n => $p) {
            $d = $defs[$n] ?? null;
            $i = $p;
            $i['padrao'] = $p;
            if ($d) {
                foreach (['nome', 'icone', 'cor'] as $c) {
                    if (trim((string) $d[$c]) !== '') {
                        $i[$c] = (string) $d[$c];
                    }
                }
                $i['ordem'] = (int) $d['ordem'];
                $i['disponivel'] = (int) $d['disponivel'];
            }
            $i['alterado'] = $i['nome'] !== $p['nome'] || $i['icone'] !== $p['icone'] || $i['cor'] !== $p['cor'] || $i['ordem'] !== $p['ordem'] || $i['disponivel'] !== 1;
            $itens[$n] = $i;
        }
        foreach ($defs as $n => $d) {
            if ($d['origem'] !== 'personalizado' || isset($itens[$n])) {
                continue;
            }
            $itens[$n] = [
                'numero' => $n, 'nome' => (string) $d['nome'], 'chave' => (string) $d['chave'], 'icone' => (string) ($d['icone'] ?: 'circle'),
                'cor' => (string) ($d['cor'] ?: '#6c757d'), 'categoria' => (string) $d['categoria'], 'reabrivel' => (int) $d['reabrivel'],
                'ordem' => (int) $d['ordem'], 'disponivel' => (int) $d['disponivel'], 'origem' => 'personalizado', 'alterado' => false, 'padrao' => null,
            ];
        }
        uasort($itens, static fn($a, $b) => [$a['ordem'], $a['numero']] <=> [$b['ordem'], $b['numero']]);
        if ($comUso) {
            foreach ($itens as $n => &$i) {
                $i['uso'] = self::uso($tipo, $n);
            }
            unset($i);
        }
        return $itens;
    }

    public static function uso(string $tipo, int $n): int
    {
        return (int) countElementsInTable(self::tabelaDoTipo($tipo), ['status' => $n]);
    }

    public static function urlBusca(string $tipo, int $n): string
    {
        global $CFG_GLPI;
        return $CFG_GLPI['root_doc'] . '/front/' . strtolower($tipo) . '.php?' . http_build_query(['criteria' => [['field' => 12, 'searchtype' => 'equals', 'value' => $n]], 'reset' => 'reset']);
    }

    public static function proximoNumero(string $tipo): int
    {
        $usados = array_keys(self::lista($tipo, false));
        $n = max(15, (int) PluginCrudstatusConfig::getConfig('numero_inicial'));
        while (in_array($n, $usados, true)) {
            $n++;
        }
        return $n;
    }

    // =====================================================================
    // Edição
    // =====================================================================

    private static function linha(string $tipo, int $n): ?array
    {
        global $DB;
        $r = $DB->request(['FROM' => self::TABELA, 'WHERE' => ['itemtype' => $tipo, 'numero' => $n], 'LIMIT' => 1])->current();
        return $r ?: null;
    }

    public static function slug(string $texto): string
    {
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        $t = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $t));
        return trim(substr($t, 0, 40), '-');
    }

    /** Cria ou altera; devolve [ok, mensagem] */
    public static function salvar(string $tipo, array $in): array
    {
        global $DB;
        if (!self::valido($tipo)) {
            return [false, 'Tipo inválido.'];
        }
        $nativos = self::nativos($tipo);
        $original = isset($in['original']) && $in['original'] !== '' ? (int) $in['original'] : null;
        $nome = trim(mb_substr(strip_tags((string) ($in['nome'] ?? '')), 0, 100));
        $icone = strtolower(trim((string) ($in['icone'] ?? '')));
        $cor = strtolower(trim((string) ($in['cor'] ?? '')));
        $disponivel = !empty($in['disponivel']) ? 1 : 0;
        if ($icone !== '' && !preg_match('/^[a-z0-9-]{1,60}$/', $icone)) {
            return [false, 'Ícone inválido.'];
        }
        if ($cor !== '' && !preg_match('/^#[0-9a-f]{6}$/', $cor)) {
            return [false, 'Cor inválida (use o formato #rrggbb).'];
        }

        // ---------------------------------------------------------------- status do núcleo
        if ($original !== null && isset($nativos[$original])) {
            $p = $nativos[$original];
            $atual = self::linha($tipo, $original);
            $dados = [
                'nome'       => ($nome === '' || $nome === $p['nome']) ? '' : $nome,
                'icone'      => ($icone === '' || $icone === $p['icone']) ? '' : $icone,
                'cor'        => ($cor === '' || $cor === $p['cor']) ? '' : $cor,
                'disponivel' => $disponivel,
                'ordem'      => $atual ? (int) $atual['ordem'] : $p['ordem'],
            ];
            if ($atual) {
                $DB->update(self::TABELA, $dados, ['id' => $atual['id']]);
            } else {
                $DB->insert(self::TABELA, $dados + ['itemtype' => $tipo, 'numero' => $original, 'origem' => $p['origem'], 'chave' => $p['chave'], 'categoria' => $p['categoria'], 'reabrivel' => $p['reabrivel']]);
            }
            self::depois('status ' . $tipo . ' ' . $original . ' (' . $p['nome'] . ') alterado');
            return [true, 'Status "' . ($nome ?: $p['nome']) . '" salvo.'];
        }

        // ---------------------------------------------------------------- status novo
        $numero = (int) ($in['numero'] ?? 0);
        $categoria = (string) ($in['categoria'] ?? '');
        $chave = self::slug((string) ($in['chave'] ?? '')) ?: self::slug($nome);
        $atual = $original !== null ? self::linha($tipo, $original) : null;
        if ($original !== null && (!$atual || $atual['origem'] !== 'personalizado')) {
            return [false, 'Status não encontrado.'];
        }
        if ($nome === '') {
            return [false, 'Informe o nome.'];
        }
        if ($numero < 1 || $numero > 65000) {
            return [false, 'Informe um número entre 1 e 65000.'];
        }
        if (isset($nativos[$numero])) {
            return [false, 'O número ' . $numero . ' já é usado pelo status "' . $nativos[$numero]['nome'] . '" do GLPI.'];
        }
        $outro = self::linha($tipo, $numero);
        if ($outro && ($atual === null || (int) $outro['id'] !== (int) $atual['id'])) {
            return [false, 'O número ' . $numero . ' já é usado pelo status "' . $outro['nome'] . '".'];
        }
        if ($atual && $numero !== (int) $atual['numero'] && self::uso($tipo, (int) $atual['numero']) > 0) {
            return [false, 'Este status já está em uso: o número não pode mudar.'];
        }
        if (!array_key_exists($categoria, self::categorias())) {
            return [false, 'Escolha o comportamento.'];
        }
        if ($chave === '') {
            return [false, 'Informe uma chave (letras, números e hífen).'];
        }
        foreach (self::lista($tipo, false) as $n => $s) {
            if ($s['chave'] === $chave && $n !== ($atual ? (int) $atual['numero'] : -1)) {
                return [false, 'A chave "' . $chave . '" já é usada pelo status "' . $s['nome'] . '".'];
            }
        }
        $dados = [
            'numero'     => $numero,
            'nome'       => $nome,
            'chave'      => $chave,
            'icone'      => $icone ?: 'circle',
            'cor'        => $cor ?: '#6c757d',
            'categoria'  => $categoria,
            'reabrivel'  => !empty($in['reabrivel']) ? 1 : 0,
            'disponivel' => $disponivel,
        ];
        if ($atual) {
            $DB->update(self::TABELA, $dados, ['id' => $atual['id']]);
            self::depois('status novo ' . $tipo . ' ' . $numero . ' (' . $nome . ') alterado');
            return [true, 'Status "' . $nome . '" salvo.'];
        }
        $ordens = array_column(self::lista($tipo, false), 'ordem');
        $DB->insert(self::TABELA, $dados + ['itemtype' => $tipo, 'origem' => 'personalizado', 'ordem' => ($ordens ? max($ordens) : 0) + 10]);
        self::depois('status novo ' . $tipo . ' ' . $numero . ' (' . $nome . ') criado');
        return [true, 'Status "' . $nome . '" criado com o número ' . $numero . '.'];
    }

    /** Volta um status do núcleo ao padrão */
    public static function restaurar(string $tipo, int $n): array
    {
        global $DB;
        $l = self::linha($tipo, $n);
        if (!$l || $l['origem'] === 'personalizado') {
            return [false, 'Este status já está no padrão.'];
        }
        $DB->delete(self::TABELA, ['id' => $l['id']]);
        self::depois('status ' . $tipo . ' ' . $n . ' voltou ao padrão');
        return [true, 'Status voltou ao padrão.'];
    }

    /** Todos os status do núcleo de um tipo voltam ao padrão (os novos continuam) */
    public static function restaurarTipo(string $tipo): array
    {
        global $DB;
        $DB->delete(self::TABELA, ['itemtype' => $tipo, 'NOT' => ['origem' => 'personalizado']]);
        self::depois('status de ' . $tipo . ' voltaram ao padrão');
        return [true, 'Os status do GLPI voltaram ao padrão. Os status novos foram mantidos.'];
    }

    /** Exclui um status novo; os itens que estão nele vão para $destino (pela atualização nativa, com histórico) */
    public static function excluir(string $tipo, int $n, ?int $destino): array
    {
        global $DB;
        $l = self::linha($tipo, $n);
        if (!$l || $l['origem'] !== 'personalizado') {
            return [false, 'Só status criados aqui podem ser excluídos. Os do GLPI podem ficar indisponíveis ou voltar ao padrão.'];
        }
        $uso = self::uso($tipo, $n);
        $movidos = 0;
        if ($uso > 0) {
            $lista = self::lista($tipo, false);
            if ($destino === null || $destino === $n || !isset($lista[$destino])) {
                return [false, 'Há ' . $uso . ' item(ns) neste status: escolha para qual status eles vão.'];
            }
            @set_time_limit(0);
            $obj = new $tipo();
            foreach ($DB->request(['SELECT' => ['id'], 'FROM' => self::tabelaDoTipo($tipo), 'WHERE' => ['status' => $n]]) as $r) {
                if ($obj->update(['id' => (int) $r['id'], 'status' => $destino, '_disablenotif' => true])) {
                    $movidos++;
                }
            }
            $resta = self::uso($tipo, $n);
            if ($resta > 0) {
                return [false, $movidos . ' item(ns) movido(s), mas ' . $resta . ' não puderam ir para "' . $lista[$destino]['nome'] . '" (o GLPI recusou a mudança). O status não foi excluído.'];
            }
        }
        $DB->delete(self::TABELA, ['id' => $l['id']]);
        self::depois('status novo ' . $tipo . ' ' . $n . ' (' . $l['nome'] . ') excluído' . ($movidos ? ', ' . $movidos . ' item(ns) movido(s) para ' . $destino : ''));
        return [true, 'Status "' . $l['nome'] . '" excluído' . ($movidos ? ' e ' . $movidos . ' item(ns) movido(s).' : '.')];
    }

    /** Sobe ou desce um status na lista */
    public static function mover(string $tipo, int $n, int $direcao): array
    {
        global $DB;
        $lista = array_values(self::lista($tipo, false));
        $idx = array_search($n, array_column($lista, 'numero'), true);
        $alvo = $idx === false ? false : $idx + ($direcao < 0 ? -1 : 1);
        if ($idx === false || $alvo < 0 || $alvo >= count($lista)) {
            return [false, 'Não há para onde mover.'];
        }
        [$lista[$idx], $lista[$alvo]] = [$lista[$alvo], $lista[$idx]];
        $nativos = self::nativos($tipo);
        foreach ($lista as $i => $s) {
            $ordem = ($i + 1) * 10;
            $l = self::linha($tipo, $s['numero']);
            if ($l) {
                $DB->update(self::TABELA, ['ordem' => $ordem], ['id' => $l['id']]);
            } elseif (isset($nativos[$s['numero']]) && $ordem !== $nativos[$s['numero']]['ordem']) {
                $p = $nativos[$s['numero']];
                $DB->insert(self::TABELA, ['itemtype' => $tipo, 'numero' => $p['numero'], 'origem' => $p['origem'], 'chave' => $p['chave'], 'categoria' => $p['categoria'], 'reabrivel' => $p['reabrivel'], 'ordem' => $ordem]);
            }
        }
        self::depois('ordem dos status de ' . $tipo . ' alterada');
        return [true, 'Ordem alterada.'];
    }

    public static function disponivel(string $tipo, int $n, bool $sim): array
    {
        $lista = self::lista($tipo, false);
        if (!isset($lista[$n])) {
            return [false, 'Status não encontrado.'];
        }
        $s = $lista[$n];
        return self::salvar($tipo, ['original' => $n, 'numero' => $n, 'nome' => $s['nome'], 'chave' => $s['chave'], 'icone' => $s['icone'], 'cor' => $s['cor'], 'categoria' => $s['categoria'], 'reabrivel' => $s['reabrivel'], 'disponivel' => $sim ? 1 : 0]);
    }

    private static function depois(string $registro): void
    {
        PluginCrudstatusPonte::limparCache();
        self::gerarArquivos();
        PluginCrudstatusConfig::registrar($registro);
    }

    // =====================================================================
    // Arquivos do navegador
    // =====================================================================

    public static function arquivo(string $tipo): string
    {
        return dirname(__DIR__) . '/public/' . ($tipo === 'css' ? 'css/definicoes.css' : 'js/definicoes.js');
    }

    /** Classes do ícone de um status como a ponte entrega ao GLPI (sem o "itilstatus") */
    public static function classes(string $tipo, array $s): string
    {
        $mudou = $s['origem'] === 'personalizado' || ($s['padrao'] && ($s['icone'] !== $s['padrao']['icone'] || $s['cor'] !== $s['padrao']['cor']));
        if (!$mudou) {
            return trim(str_replace('itilstatus', '', (string) PluginCrudstatusPonte::original($tipo, 'getStatusClass', [$s['numero']])));
        }
        return 'ti ti-' . $s['icone'] . ' ' . $s['chave'] . ' crudstatus-' . strtolower($tipo) . '-' . $s['numero'];
    }

    public static function revisao(): int
    {
        return (int) @filemtime(self::arquivo('js'));
    }

    /** Cores (CSS) e mapa de ícones do dropdown de status (JS), gerados a partir das definições */
    public static function gerarArquivos(): void
    {
        PluginCrudstatusPonte::limparCache();
        $ativa = PluginCrudstatusNucleo::estado()['completa'];
        $css = "/* Gerado pelo plugin CRUD Status - não editar */\n";
        $mapa = [];
        if ($ativa) {
            foreach (array_keys(self::tipos()) as $tipo) {
                foreach (self::lista($tipo, false) as $n => $s) {
                    $mapa[$tipo][$n] = self::classes($tipo, $s);
                    if ($s['origem'] === 'personalizado' || ($s['padrao'] && $s['cor'] !== $s['padrao']['cor'])) {
                        $sel = '.crudstatus-' . strtolower($tipo) . '-' . $n;
                        $css .= '.itilstatus' . $sel . ', .validationstatus' . $sel . ' { --status-color: ' . $s['cor'] . '; color: ' . $s['cor'] . "; }\n";
                    }
                }
            }
        }
        $json = json_encode((object) $mapa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
        $js = <<<JS
/* Gerado pelo plugin CRUD Status - não editar */
(function () {
    'use strict';
    var mapa = {$json};
    window.crudstatusMapa = mapa;
    var original = window.templateItilStatus;
    function tipoDaPagina(el) {
        var form = el && el.closest ? el.closest('form') : null;
        var campo = form ? form.querySelector('input[name="itemtype"]') : null;
        if (campo && mapa[campo.value]) { return campo.value; }
        var p = window.location.pathname;
        if (/problem/.test(p)) { return 'Problem'; }
        if (/change/.test(p)) { return 'Change'; }
        return 'Ticket';
    }
    function esc(t) {
        return String(t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    }
    if (typeof original !== 'function' || !Object.keys(mapa).length) { return; }
    window.templateItilStatus = function (option) {
        if (!option || option.id === undefined || option.id === '') { return original(option); }
        var tipo = tipoDaPagina(option.element);
        var classes = mapa[tipo] ? mapa[tipo][option.id] : null;
        if (!classes) { return original(option); }
        return window.jQuery('<span><i class="itilstatus ' + esc(classes) + '" aria-hidden="true"></i> ' + esc(option.text) + '</span>');
    };
})();

JS;
        @file_put_contents(self::arquivo('css'), $css);
        @file_put_contents(self::arquivo('js'), $js);
        clearstatcache(true, self::arquivo('js'));
    }
}
