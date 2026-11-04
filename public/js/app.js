/* mapdevice — interações da interface (sem dependências) */
(function () {
  'use strict';

  var csrf = document.querySelector('meta[name="csrf-token"]');
  csrf = csrf ? csrf.getAttribute('content') : '';

  // Tema claro/escuro
  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var root = document.documentElement;
      var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('mapdevice-theme', next); } catch (e) {}
    });
  });

  // Menus suspensos
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-dropdown-toggle]');
    document.querySelectorAll('.dropdown.open').forEach(function (d) {
      if (!toggle || d !== toggle.closest('.dropdown')) d.classList.remove('open');
    });
    if (toggle) {
      e.preventDefault();
      toggle.closest('.dropdown').classList.toggle('open');
    }
  });

  // Menu lateral no celular
  document.querySelectorAll('[data-nav-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () { document.body.classList.toggle('nav-open'); });
  });

  // Confirmação antes de excluir
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Alertas somem sozinhos
  document.querySelectorAll('.alert[data-autohide], .flash[data-autohide]').forEach(function (el) {
    setTimeout(function () { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 400); }, 4000);
  });

  // Filtros / seletores que enviam o formulário ao mudar
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });

  function escapeHtml(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

  // ---------- Assistente IA ----------
  var chatLog = document.querySelector('[data-chat-log]');
  function scrollChat() { if (chatLog) chatLog.scrollTop = chatLog.scrollHeight; }
  scrollChat();

  // Atalhos preenchem a caixa de mensagem
  document.querySelectorAll('[data-prompt]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.querySelector('[data-chat-input]');
      if (!input) return;
      input.value = btn.getAttribute('data-prompt');
      input.focus();
      var pos = input.value.indexOf('(');
      if (pos >= 0) { input.selectionStart = pos; input.selectionEnd = input.value.indexOf(')', pos) + 1; }
    });
  });

  // Copiar resposta
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-copy]');
    if (!btn) return;
    var raw = btn.closest('.msg-body') ? btn.closest('.msg-body').querySelector('[data-copy-raw]') : null;
    var text = raw ? raw.value : '';
    var done = function () { var old = btn.innerHTML; btn.textContent = 'Copiado!'; setTimeout(function () { btn.innerHTML = old; }, 1500); };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done);
    } else {
      var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); done(); } catch (err) {} ta.remove();
    }
  });

  // Envio sem recarregar a página (com recarga normal como alternativa)
  var chatForm = document.querySelector('[data-chat-form]');
  if (chatForm && chatLog) {
    var input = chatForm.querySelector('[data-chat-input]');
    var sendBtn = chatForm.querySelector('[data-chat-send]');
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); chatForm.requestSubmit ? chatForm.requestSubmit() : chatForm.submit(); }
    });
    chatForm.addEventListener('submit', function (e) {
      var text = input.value.trim();
      if (!text || !window.fetch) return;
      e.preventDefault();
      var welcome = chatLog.querySelector('[data-chat-welcome]');
      if (welcome) welcome.remove();
      var end = chatLog.querySelector('#fim');
      var userMsg = document.createElement('div');
      userMsg.className = 'msg msg-user';
      userMsg.innerHTML = '<div class="msg-bubble pre">' + escapeHtml(text) + '</div>';
      var thinking = document.createElement('div');
      thinking.className = 'msg msg-ai msg-thinking';
      thinking.innerHTML = '<div class="msg-avatar">✦</div><div class="msg-body"><div class="msg-bubble"><span class="dots"><span></span><span></span><span></span></span> Pensando...</div></div>';
      if (end) { chatLog.insertBefore(userMsg, end); chatLog.insertBefore(thinking, end); }
      else { chatLog.appendChild(userMsg); chatLog.appendChild(thinking); }
      scrollChat();
      var data = new FormData(chatForm);
      input.value = '';
      sendBtn.disabled = true;
      fetch(chatForm.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: data })
        .then(function (r) {
          if (r.status === 422) return r.json().then(function (j) { throw new Error((j && j.message) || 'Verifique a mensagem.'); });
          if (r.status === 429) throw new Error('Muitas mensagens em pouco tempo. Aguarde um minuto.');
          if (!r.ok) throw new Error('Erro ' + r.status + ' no servidor.');
          return r.json();
        })
        .then(function (j) {
          if (j.redirect) { window.location.href = j.redirect; return; }
          thinking.outerHTML = j.html;
          scrollChat();
        })
        .catch(function (err) {
          thinking.remove();
          input.value = text;
          alert(err.message || 'Não foi possível enviar a mensagem.');
        })
        .then(function () { sendBtn.disabled = false; });
    });
  }

  // Cadastro de provedor: ao trocar o tipo, ajusta modelos, URL e dicas
  var driverSel = document.querySelector('[data-ai-driver]');
  if (driverSel) {
    var f = driverSel.form;
    driverSel.addEventListener('change', function () {
      var o = driverSel.selectedOptions[0];
      var models = (o.getAttribute('data-models') || '').split(',').filter(Boolean);
      var datalist = f.querySelector('[data-ai-models]');
      if (datalist) datalist.innerHTML = models.map(function (m) { return '<option value="' + escapeHtml(m) + '"></option>'; }).join('');
      var model = f.querySelector('[name="model"]');
      if (model && o.getAttribute('data-default-model')) model.value = o.getAttribute('data-default-model');
      var url = f.querySelector('[data-ai-base-url]');
      if (url) url.placeholder = o.getAttribute('data-base-url') || 'https://.../v1';
      var hint = o.getAttribute('data-hint');
      var hintEl = f.querySelector('[data-ai-hint]');
      if (hintEl) hintEl.innerHTML = hint ? '✦ <span>' + escapeHtml(hint) + '</span>' : '';
      var link = f.querySelector('[data-ai-keys-link]');
      var href = o.getAttribute('data-keys-url');
      if (link) { if (href) { link.href = href; link.hidden = false; } else { link.hidden = true; } }
      var name = f.querySelector('[name="name"]');
      if (name && !name.dataset.touched) name.value = o.textContent.replace(/\s*\(.*\)\s*$/, '').trim();
    });
    var nameInput = f.querySelector('[name="name"]');
    if (nameInput) nameInput.addEventListener('input', function () { this.dataset.touched = '1'; });

    // Carregar os modelos que a chave pode usar
    var loadBtn = f.querySelector('[data-ai-load-models]');
    var modelList = f.querySelector('[data-ai-model-list]');
    var modelStatus = f.querySelector('[data-ai-models-status]');
    if (loadBtn) {
      loadBtn.addEventListener('click', function () {
        var body = new FormData();
        body.append('driver', driverSel.value);
        body.append('base_url', (f.querySelector('[name="base_url"]') || {}).value || '');
        body.append('api_key', (f.querySelector('[name="api_key"]') || {}).value || '');
        if (loadBtn.getAttribute('data-provider-id')) body.append('provider_id', loadBtn.getAttribute('data-provider-id'));
        loadBtn.disabled = true;
        modelStatus.className = 'small muted';
        modelStatus.textContent = 'Consultando o provedor...';
        fetch(loadBtn.getAttribute('data-url'), { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: body })
          .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
            var models = (res.j && res.j.models) || [];
            modelStatus.className = 'small ' + (res.ok && models.length ? 'text-green' : 'text-red');
            modelStatus.textContent = (res.j && res.j.message) || 'Não foi possível carregar os modelos.';
            if (!res.ok || !models.length) { modelList.hidden = true; return; }
            var current = f.querySelector('[name="model"]').value;
            modelList.innerHTML = '<option value="">Escolha um modelo...</option>' + models.map(function (m) {
              return '<option value="' + escapeHtml(m) + '"' + (m === current ? ' selected' : '') + '>' + escapeHtml(m) + '</option>';
            }).join('');
            modelList.hidden = false;
          })
          .catch(function () { modelStatus.className = 'small text-red'; modelStatus.textContent = 'Não foi possível carregar os modelos.'; })
          .then(function () { loadBtn.disabled = false; });
      });
      if (modelList) modelList.addEventListener('change', function () { if (modelList.value) f.querySelector('[name="model"]').value = modelList.value; });
    }
  }
})();
