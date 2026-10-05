/**
 * Plugin CRUD Status - tela de status (abas, edição em modal, seletor de ícones, ordem, exclusão) e integração
 */
(function () {
    'use strict';

    if (window.CrudStatusTela) {
        return;
    }
    window.CrudStatusTela = true;

    var raiz = (window.CFG_GLPI && window.CFG_GLPI.root_doc) ? window.CFG_GLPI.root_doc : '';
    var urlAjax = raiz + '/plugins/crudstatus/front/ajax.php';

    function $(sel, base) { return (base || document).querySelector(sel); }
    function $$(sel, base) { return Array.prototype.slice.call((base || document).querySelectorAll(sel)); }

    function aviso(texto, erro) {
        if (erro && typeof window.glpi_toast_error === 'function') {
            window.glpi_toast_error(texto);
        } else if (!erro && typeof window.glpi_toast_info === 'function') {
            window.glpi_toast_info(texto);
        }
    }

    function lerJson(texto) {
        try {
            return JSON.parse(texto);
        } catch (e) {
            var m = texto.match(/\{[\s\S]*\}\s*$/);
            if (m) {
                try { return JSON.parse(m[0]); } catch (e2) { /* segue */ }
            }
        }
        return { success: false, message: 'Resposta inválida do servidor.' };
    }

    function pedir(acao, dados, metodo) {
        var opcoes = { method: metodo || 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } };
        var url = urlAjax + '?action=' + encodeURIComponent(acao);
        if (opcoes.method !== 'GET') {
            var fd = new FormData();
            Object.keys(dados || {}).forEach(function (k) { fd.append(k, dados[k]); });
            var m = document.querySelector('meta[property="glpi:csrf_token"]');
            var t = m ? m.getAttribute('content') : '';
            if (t) {
                opcoes.headers['X-Glpi-Csrf-Token'] = t;
                fd.append('_glpi_csrf_token', t);
            }
            opcoes.body = fd;
        }
        return fetch(url, opcoes).then(function (r) { return r.text(); }).then(function (txt) {
            var j = lerJson(txt);
            var m = document.querySelector('meta[property="glpi:csrf_token"]');
            if (j.new_token && m) { m.setAttribute('content', j.new_token); }
            return j;
        });
    }

    function retorno(el, texto, ok) {
        if (!el) { return; }
        el.textContent = texto || '';
        el.className = 'crudstatus-retorno ' + (ok ? 'ok' : 'erro');
    }

    function recarregar(tipo) {
        var u = new URL(window.location.href);
        if (tipo) { u.searchParams.set('tipo', tipo); }
        window.location.href = u.toString();
    }

    function slug(t) {
        return String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
    }

    // ------------------------------------------------------------------ confirmação dentro do botão
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-confirmar]');
        if (!btn || btn.disabled || !btn.closest('.crudstatus-pagina, .modal')) { return; }
        if (btn.dataset.armado === '1') {
            clearTimeout(parseInt(btn.dataset.tempo || '0', 10));
            btn.innerHTML = btn.dataset.original;
            btn.classList.remove('crudstatus-confirmando');
            btn.dataset.armado = '';
            return;
        }
        ev.preventDefault();
        ev.stopImmediatePropagation();
        btn.dataset.original = btn.innerHTML;
        btn.innerHTML = '<i class="ti ti-alert-triangle"></i> ' + btn.getAttribute('data-confirmar');
        btn.classList.add('crudstatus-confirmando');
        btn.dataset.armado = '1';
        btn.dataset.tempo = String(setTimeout(function () {
            btn.innerHTML = btn.dataset.original;
            btn.classList.remove('crudstatus-confirmando');
            btn.dataset.armado = '';
        }, 4000));
    }, true);

    // ------------------------------------------------------------------ tela de status
    function iniciarStatus() {
        var pagina = $('#crudstatus-pagina');
        if (!pagina) { return; }
        var dados = JSON.parse(($('#crudstatus-dados') || {}).textContent || '{}');
        var modalEl = $('#crudstatus-modal');
        var modal = (window.bootstrap && modalEl) ? new window.bootstrap.Modal(modalEl) : null;
        var modalExcEl = $('#crudstatus-modal-excluir');
        var modalExc = (window.bootstrap && modalExcEl) ? new window.bootstrap.Modal(modalExcEl) : null;
        var atual = null;
        var icones = null;
        var chaveManual = false;

        // Abas por tipo
        $$('[data-crudstatus-tipo]', pagina).forEach(function (a) {
            a.addEventListener('click', function (ev) {
                ev.preventDefault();
                var tipo = a.getAttribute('data-crudstatus-tipo');
                $$('[data-crudstatus-tipo]', pagina).forEach(function (x) { x.classList.toggle('active', x === a); });
                $$('[data-crudstatus-painel]', pagina).forEach(function (p) { p.style.display = p.getAttribute('data-crudstatus-painel') === tipo ? '' : 'none'; });
                var u = new URL(window.location.href);
                u.searchParams.set('tipo', tipo);
                window.history.replaceState(null, '', u.toString());
            });
        });

        function linha(el) {
            var tr = el.closest('tr[data-status]');
            return tr ? JSON.parse(tr.getAttribute('data-status')) : null;
        }

        // Ações da tabela
        pagina.addEventListener('click', function (ev) {
            var novo = ev.target.closest('[data-crudstatus-novo]');
            if (novo) {
                abrir(null, novo.getAttribute('data-crudstatus-novo'));
                return;
            }
            var btn = ev.target.closest('button[data-crudstatus-acao]');
            if (!btn || btn.disabled || btn.dataset.armado === '1') { return; }
            var acao = btn.getAttribute('data-crudstatus-acao');
            if (acao === 'restaurar_tipo') {
                var tipoR = btn.getAttribute('data-tipo');
                pedir('restaurar_tipo', { tipo: tipoR }).then(function (j) { aviso(j.message, !j.success); if (j.success) { recarregar(tipoR); } });
                return;
            }
            var s = linha(btn);
            if (!s) { return; }
            if (acao === 'editar') {
                abrir(s, s.tipo);
            } else if (acao === 'mover') {
                pedir('mover', { tipo: s.tipo, numero: s.numero, direcao: btn.getAttribute('data-direcao') }).then(function (j) {
                    if (j.success) { recarregar(s.tipo); } else { aviso(j.message, true); }
                });
            } else if (acao === 'restaurar') {
                pedir('restaurar', { tipo: s.tipo, numero: s.numero }).then(function (j) { aviso(j.message, !j.success); if (j.success) { recarregar(s.tipo); } });
            } else if (acao === 'excluir') {
                abrirExclusao(s);
            }
        });
        pagina.addEventListener('change', function (ev) {
            var cb = ev.target.closest('input[data-crudstatus-acao="disponivel"]');
            if (!cb) { return; }
            var s = linha(cb);
            pedir('disponivel', { tipo: s.tipo, numero: s.numero, valor: cb.checked ? '1' : '' }).then(function (j) {
                if (!j.success) { aviso(j.message, true); cb.checked = !cb.checked; return; }
                cb.closest('tr').classList.toggle('crudstatus-indisponivel', !cb.checked);
            });
        });

        // ---------------------------------------------------------------- modal de edição
        var f = function (id) { return $('#crudstatus-f-' + id); };

        function previa() {
            var p = $('#crudstatus-previa');
            var cor = f('cor-texto').value || '#6c757d';
            p.innerHTML = '';
            var i = document.createElement('i');
            i.className = 'itilstatus ti ti-' + (f('icone').value || 'circle');
            i.style.color = cor;
            var b = document.createElement('strong');
            b.textContent = f('nome').value || (atual && atual.padrao ? atual.padrao.nome : 'Status');
            p.appendChild(i);
            p.appendChild(b);
            f('icone-nome').textContent = f('icone').value || '';
        }

        function definirCor(c) {
            if (!/^#[0-9a-fA-F]{6}$/.test(c)) { return; }
            f('cor').value = c.toLowerCase();
            f('cor-texto').value = c.toLowerCase();
            previa();
        }

        function desenharIcones() {
            var grade = $('#crudstatus-grade-icones');
            if (!icones) { return; }
            var termo = f('busca-icone').value.trim().toLowerCase();
            var sel = f('icone').value;
            var lista = icones.filter(function (n) { return !termo || n.indexOf(termo) !== -1; });
            lista.sort(function (a, b) { return (a === sel ? -1 : b === sel ? 1 : 0); });
            var mostrar = lista.slice(0, 300);
            grade.innerHTML = '';
            mostrar.forEach(function (n) {
                var b = document.createElement('button');
                b.type = 'button';
                b.title = n;
                b.dataset.icone = n;
                if (n === sel) { b.className = 'selecionado'; }
                var i = document.createElement('i');
                i.className = 'ti ti-' + n;
                b.appendChild(i);
                grade.appendChild(b);
            });
            $('#crudstatus-icones-total').textContent = lista.length > mostrar.length
                ? 'Mostrando ' + mostrar.length + ' de ' + lista.length + ' ícones: refine a busca.'
                : lista.length + ' ícone(s)';
        }

        $('#crudstatus-grade-icones').addEventListener('click', function (ev) {
            var b = ev.target.closest('button[data-icone]');
            if (!b) { return; }
            f('icone').value = b.dataset.icone;
            f('busca-icone').value = '';
            desenharIcones();
            previa();
        });
        var espera = null;
        f('busca-icone').addEventListener('input', function () {
            clearTimeout(espera);
            espera = setTimeout(desenharIcones, 200);
        });

        function carregarIcones() {
            if (icones) { desenharIcones(); return; }
            pedir('icones', {}, 'GET').then(function (j) {
                icones = (j.icones || []).map(String);
                desenharIcones();
            });
        }

        f('nome').addEventListener('input', function () {
            if (atual === null && !chaveManual) { f('chave').value = slug(f('nome').value); }
            previa();
        });
        f('chave').addEventListener('input', function () { chaveManual = true; });
        f('cor').addEventListener('input', function () { definirCor(f('cor').value); });
        f('cor-texto').addEventListener('input', function () { definirCor(f('cor-texto').value); });
        $('#crudstatus-cores').addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-cor]');
            if (b) { definirCor(b.dataset.cor); }
        });
        f('nome-padrao').addEventListener('click', function () { if (atual && atual.padrao) { f('nome').value = atual.padrao.nome; previa(); } });
        f('cor-padrao').addEventListener('click', function () { if (atual && atual.padrao) { definirCor(atual.padrao.cor); } });
        f('icone-padrao').addEventListener('click', function () { if (atual && atual.padrao) { f('icone').value = atual.padrao.icone; desenharIcones(); previa(); } });

        function abrir(s, tipo) {
            atual = s;
            chaveManual = !!s;
            var nativo = !!(s && s.padrao);
            f('tipo').value = tipo;
            f('original').value = s ? s.numero : '';
            f('nome').value = s ? s.nome : '';
            f('numero').value = s ? s.numero : (dados.proximo[tipo] || 100);
            f('chave').value = s ? s.chave : '';
            f('categoria').value = s ? s.categoria : 'andamento';
            f('reabrivel').checked = s ? !!s.reabrivel : false;
            f('disponivel').checked = s ? !!s.disponivel : true;
            f('icone').value = s ? s.icone : 'circle';
            f('busca-icone').value = '';
            definirCor(s ? s.cor : '#6c757d');
            ['numero', 'chave', 'categoria', 'reabrivel'].forEach(function (k) { f(k).disabled = nativo; });
            ['nome-padrao', 'cor-padrao', 'icone-padrao'].forEach(function (k) { f(k).style.display = nativo ? '' : 'none'; });
            f('dica-nativo').style.display = nativo ? '' : 'none';
            f('nome').placeholder = nativo ? s.padrao.nome : 'Ex.: Aguardando fornecedor';
            $('#crudstatus-modal-titulo').textContent = s ? ('Editar status: ' + s.nome) : ('Novo status de ' + (dados.tipos[tipo] || '').toLowerCase());
            retorno($('#crudstatus-retorno-modal'), '', true);
            previa();
            carregarIcones();
            if (modal) { modal.show(); }
        }

        $('#crudstatus-salvar').addEventListener('click', function () {
            var btn = this;
            var envio = {
                tipo: f('tipo').value, original: f('original').value, nome: f('nome').value, numero: f('numero').value,
                chave: f('chave').value, categoria: f('categoria').value, reabrivel: f('reabrivel').checked ? '1' : '',
                disponivel: f('disponivel').checked ? '1' : '', icone: f('icone').value, cor: f('cor-texto').value
            };
            btn.disabled = true;
            pedir('salvar', envio).then(function (j) {
                btn.disabled = false;
                retorno($('#crudstatus-retorno-modal'), j.message, j.success);
                if (j.success) { aviso(j.message, false); recarregar(envio.tipo); }
            }).catch(function () { btn.disabled = false; retorno($('#crudstatus-retorno-modal'), 'Falha de comunicação.', false); });
        });

        // ---------------------------------------------------------------- exclusão
        function abrirExclusao(s) {
            atual = s;
            $('#crudstatus-excluir-texto').textContent = 'Excluir o status "' + s.nome + '" (número ' + s.numero + ')?';
            var bloco = $('#crudstatus-excluir-destino-bloco');
            var sel = $('#crudstatus-excluir-destino');
            sel.innerHTML = '';
            if (s.uso > 0) {
                $('#crudstatus-excluir-uso').textContent = s.uso + ' item(ns) estão neste status. Escolha para qual status eles vão antes de excluir.';
                (dados.listas[s.tipo] || []).forEach(function (o) {
                    if (o.numero === s.numero) { return; }
                    var op = document.createElement('option');
                    op.value = o.numero;
                    op.textContent = o.nome + ' (' + o.numero + ')';
                    sel.appendChild(op);
                });
                bloco.style.display = '';
            } else {
                bloco.style.display = 'none';
            }
            retorno($('#crudstatus-retorno-excluir'), '', true);
            if (modalExc) { modalExc.show(); }
        }

        $('#crudstatus-confirmar-excluir').addEventListener('click', function () {
            var btn = this;
            if (!atual || btn.dataset.armado === '1') { return; }
            btn.disabled = true;
            retorno($('#crudstatus-retorno-excluir'), atual.uso > 0 ? 'Movendo os itens…' : '', true);
            pedir('excluir', { tipo: atual.tipo, numero: atual.numero, destino: atual.uso > 0 ? $('#crudstatus-excluir-destino').value : '' }).then(function (j) {
                btn.disabled = false;
                retorno($('#crudstatus-retorno-excluir'), j.message, j.success);
                if (j.success) { aviso(j.message, false); recarregar(atual.tipo); }
            }).catch(function () { btn.disabled = false; retorno($('#crudstatus-retorno-excluir'), 'Falha de comunicação.', false); });
        });
    }

    // ------------------------------------------------------------------ integração com o núcleo
    function iniciarNucleo() {
        var pagina = $('#crudstatus-nucleo');
        if (!pagina) { return; }
        pagina.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-crudstatus-nucleo]');
            if (!btn || btn.disabled || btn.dataset.armado === '1') { return; }
            btn.disabled = true;
            pedir(btn.getAttribute('data-crudstatus-nucleo'), {}).then(function (j) {
                btn.disabled = false;
                retorno($('#crudstatus-retorno'), j.message, j.success);
                if (j.success) { setTimeout(function () { window.location.reload(); }, 900); }
            }).catch(function () { btn.disabled = false; retorno($('#crudstatus-retorno'), 'Falha de comunicação.', false); });
        });
    }

    function iniciar() {
        iniciarStatus();
        iniciarNucleo();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
