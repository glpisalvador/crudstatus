/* Gerado pelo plugin CRUD Status - não editar */
(function () {
    'use strict';
    var mapa = {"Ticket":{"1":"ti ti-circle-filled new","10":"ti ti-help approval","2":"ti ti-circle assigned","3":"ti ti-calendar planned","4":"ti ti-circle-filled waiting","5":"ti ti-circle solved","6":"ti ti-circle-filled closed","50":"ti ti-clock","51":"ti ti-check","52":"ti ti-x"},"Problem":{"1":"ti ti-circle-filled new","7":"ti ti-circle-check-filled accepted","2":"ti ti-circle assigned","3":"ti ti-calendar planned","4":"ti ti-circle-filled waiting","5":"ti ti-circle solved","8":"ti ti-eye observe","6":"ti ti-circle-filled closed"},"Change":{"1":"ti ti-circle-filled new","9":"ti ti-circle eval","10":"ti ti-help approval","7":"ti ti-circle-check-filled accepted","4":"ti ti-circle-filled waiting","11":"ti ti-help test","12":"ti ti-circle qualif","5":"ti ti-circle solved","8":"ti ti-eye observe","6":"ti ti-circle-filled closed","14":"ti ti-ban canceled","13":"ti ti-circle-x refused"}};
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
