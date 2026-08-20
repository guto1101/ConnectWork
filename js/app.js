/* =====================================================================
   ConnectWork — Script do cliente

   O navegador coleta a posição e envia. Quem decide se a batida vale é o
   servidor: aqui não existe cálculo de cerca nem carimbo de hora.
   ===================================================================== */

(function () {
  'use strict';

  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  // -------------------------------------------------------------------
  // Toast
  // -------------------------------------------------------------------
  var toastEl = document.getElementById('toast');
  var toastTimer = null;

  function toast(texto) {
    if (!toastEl) { return; }
    toastEl.textContent = texto;
    toastEl.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('show'); }, 3200);
  }
  window.cwToast = toast;

  // -------------------------------------------------------------------
  // Menu lateral no celular
  // -------------------------------------------------------------------
  var menuToggle = document.getElementById('menuToggle');
  var sidebar = document.getElementById('sidebar');

  if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', function (ev) {
      ev.stopPropagation();
      sidebar.classList.toggle('open');
    });
    document.addEventListener('click', function (ev) {
      if (window.innerWidth > 780) { return; }
      if (sidebar.contains(ev.target) || menuToggle.contains(ev.target)) { return; }
      sidebar.classList.remove('open');
    });
  }

  // -------------------------------------------------------------------
  // Confirmação em ações destrutivas
  // -------------------------------------------------------------------
  Array.prototype.forEach.call(document.querySelectorAll('[data-confirma]'), function (el) {
    el.addEventListener('click', function (ev) {
      if (!window.confirm(el.getAttribute('data-confirma'))) {
        ev.preventDefault();
      }
    });
  });

  // -------------------------------------------------------------------
  // Relógio
  // -------------------------------------------------------------------
  var clockTime = document.getElementById('clockTime');
  if (clockTime) {
    setInterval(function () {
      var d = new Date();
      clockTime.textContent = [d.getHours(), d.getMinutes(), d.getSeconds()]
        .map(function (n) { return ('0' + n).slice(-2); }).join(':');
    }, 1000);
  }

  // -------------------------------------------------------------------
  // Ponto
  // -------------------------------------------------------------------
  if (!window.CW_PONTO) { return; }

  var cfg     = window.CW_PONTO;
  var gpsEl   = document.getElementById('gpsStatus');
  var retorno = document.getElementById('retornoBatida');
  var botoes  = document.querySelectorAll('[data-bater]');
  var posicao = null;

  function uuid() {
    if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
    var b = new Uint8Array(16);
    if (window.crypto && crypto.getRandomValues) {
      crypto.getRandomValues(b);
    } else {
      for (var i = 0; i < 16; i++) { b[i] = Math.floor(Math.random() * 256); }
    }
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    var h = [].map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
    return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' +
           h.slice(16, 20) + '-' + h.slice(20);
  }

  function alerta(tipo, texto) {
    if (!retorno) { return toast(texto); }
    retorno.innerHTML = '<div class="alert alert-' + tipo + '" role="alert"></div>';
    retorno.firstChild.textContent = texto;
  }

  /** Uma leitura de GPS, com prazo. Nunca rejeita: devolve null. */
  function obterPosicao() {
    return new Promise(function (resolve) {
      if (!navigator.geolocation) { return resolve(null); }
      navigator.geolocation.getCurrentPosition(
        function (p) { resolve(p); },
        function () { resolve(null); },
        { enableHighAccuracy: true, timeout: 12000, maximumAge: 15000 }
      );
    });
  }

  function mostrarGps(p) {
    if (!gpsEl) { return; }
    if (!p) {
      gpsEl.textContent = cfg.exigeGps
        ? 'GPS: sem localização. Autorize o acesso à localização no navegador para bater ponto.'
        : 'GPS: sem localização — o registro seguirá sem coordenadas.';
      return;
    }
    gpsEl.textContent = 'GPS: ' + p.coords.latitude.toFixed(5) + ', ' +
      p.coords.longitude.toFixed(5) + ' (precisão de ' + Math.round(p.coords.accuracy) + ' m)';
  }

  // Busca a posição desde o carregamento, para o clique não ter que esperar.
  obterPosicao().then(function (p) { posicao = p; mostrarGps(p); });

  Array.prototype.forEach.call(botoes, function (botao) {
    if (botao.disabled) { return; }
    botao.addEventListener('click', function () { bater(botao); });
  });

  function bater(botao) {
    var tipo = botao.getAttribute('data-bater');
    var textoOriginal = botao.textContent;

    Array.prototype.forEach.call(botoes, function (b) { b.disabled = true; });
    botao.textContent = 'Registrando…';
    if (gpsEl) { gpsEl.textContent = 'GPS: confirmando sua localização…'; }

    obterPosicao().then(function (p) {
      posicao = p || posicao;
      mostrarGps(posicao);

      return fetch(cfg.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({
          tipo: tipo,
          token: uuid(),                 // reenvio da mesma batida não duplica
          origem: 'web',
          latitude:  posicao ? posicao.coords.latitude  : null,
          longitude: posicao ? posicao.coords.longitude : null,
          precisao:  posicao ? posicao.coords.accuracy  : null
        })
      });
    }).then(function (resposta) {
      return resposta.json().then(function (dados) {
        return { http: resposta.status, dados: dados };
      });
    }).then(function (r) {
      if (r.dados && r.dados.ok) {
        alerta('ok', r.dados.mensagem);
        toast(r.dados.mensagem);
        setTimeout(function () { window.location.reload(); }, 1000);
        return;
      }
      if (r.http === 401) {
        alerta('erro', 'Sua sessão expirou. Entre novamente para bater ponto.');
      } else {
        alerta('erro', (r.dados && r.dados.erro) || 'Não foi possível registrar agora.');
      }
      restaurar(botao, textoOriginal);
    }).catch(function () {
      alerta('erro', 'Sem conexão com o servidor. A batida NÃO foi registrada — ' +
                     'tente de novo assim que a rede voltar.');
      restaurar(botao, textoOriginal);
    });
  }

  function restaurar(botao, texto) {
    botao.textContent = texto;
    Array.prototype.forEach.call(botoes, function (b) {
      if (!b.hasAttribute('title')) { b.disabled = false; }
    });
    botao.disabled = false;
  }
})();
