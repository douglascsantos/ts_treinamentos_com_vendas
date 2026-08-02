(function () {
  'use strict';

  /* Menu mobile (drawer) */
  var toggle = document.querySelector('.menu-toggle');
  var drawer = document.getElementById('mobile-nav');
  var overlay = document.getElementById('mobile-nav-overlay');

  function closeDrawer() {
    drawer.classList.remove('is-open');
    overlay.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
    setTimeout(function () {
      drawer.hidden = true;
      overlay.hidden = true;
    }, 300);
  }

  function openDrawer() {
    drawer.hidden = false;
    overlay.hidden = false;
    requestAnimationFrame(function () {
      drawer.classList.add('is-open');
      overlay.classList.add('is-open');
    });
    toggle.setAttribute('aria-expanded', 'true');
  }

  if (toggle && drawer && overlay) {
    toggle.addEventListener('click', function () {
      var isOpen = drawer.classList.contains('is-open');
      isOpen ? closeDrawer() : openDrawer();
    });
    overlay.addEventListener('click', closeDrawer);
    drawer.querySelectorAll('a:not(.menu-disabled)').forEach(function (link) {
      link.addEventListener('click', closeDrawer);
    });
  }

  /* FAQ accordion */
  document.querySelectorAll('.faq-question').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var answer = document.getElementById(btn.getAttribute('aria-controls'));
      var isOpen = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!isOpen));
      if (answer) answer.hidden = isOpen;
    });
  });

  /* Modal genérico (usado no popup de contrato) — abre via [data-modal-open="id-do-modal"],
     fecha clicando fora da caixa, no botão .modal-close, ou com Esc. */
  document.querySelectorAll('[data-modal-open]').forEach(function (opener) {
    opener.addEventListener('click', function (ev) {
      ev.preventDefault();
      var modal = document.getElementById(opener.getAttribute('data-modal-open'));
      if (modal) {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
      }
    });
  });
  document.querySelectorAll('.modal-overlay').forEach(function (modal) {
    function fecharModal() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    }
    modal.addEventListener('click', function (ev) {
      if (ev.target === modal) fecharModal();
    });
    modal.querySelectorAll('.modal-close').forEach(function (btn) {
      btn.addEventListener('click', fecharModal);
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && modal.classList.contains('is-open')) fecharModal();
    });
  });

  /* Abas dentro do modal (quando há mais de um contrato pra visualizar) */
  document.querySelectorAll('.modal-tabs').forEach(function (tabs) {
    var buttons = tabs.querySelectorAll('.modal-tab-btn');
    var box = tabs.closest('.modal-box');
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var targetId = btn.getAttribute('data-tab-target');
        buttons.forEach(function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
        if (box) {
          box.querySelectorAll('.modal-tab-panel').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.id === targetId);
          });
          box.scrollTop = 0;
        }
      });
    });
  });

  /* Máscaras simples de campo — CPF (000.000.000-00) e telefone/WhatsApp
     ((00) 00000-0000), via [data-mask="cpf"] / [data-mask="phone"]. */
  function maskCpf(value) {
    var d = value.replace(/\D/g, '').slice(0, 11);
    d = d.replace(/(\d{3})(\d)/, '$1.$2');
    d = d.replace(/(\d{3})(\d)/, '$1.$2');
    d = d.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    return d;
  }
  function maskPhone(value) {
    var d = value.replace(/\D/g, '').slice(0, 11);
    if (d.length > 10) {
      d = d.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
    } else if (d.length > 5) {
      d = d.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    } else if (d.length > 2) {
      d = d.replace(/(\d{2})(\d{0,5})/, '($1) $2');
    } else if (d.length > 0) {
      d = d.replace(/(\d{0,2})/, '($1');
    }
    return d.replace(/-$/, '').replace(/\)\s$/, ')');
  }
  function maskCep(value) {
    var d = value.replace(/\D/g, '').slice(0, 8);
    return d.replace(/(\d{5})(\d{1,3})$/, '$1-$2');
  }
  document.querySelectorAll('input[data-mask="cpf"]').forEach(function (input) {
    input.addEventListener('input', function () { input.value = maskCpf(input.value); });
  });
  document.querySelectorAll('input[data-mask="phone"]').forEach(function (input) {
    input.addEventListener('input', function () { input.value = maskPhone(input.value); });
  });
  document.querySelectorAll('input[data-mask="cep"]').forEach(function (input) {
    input.addEventListener('input', function () { input.value = maskCep(input.value); });
  });

  /* Confirmação de senha em tempo real (cadastro do aluno) */
  var senha = document.getElementById('cad_senha');
  var confirmar = document.getElementById('cad_senha_confirmar');
  if (senha && confirmar) {
    function checarSenhas() {
      confirmar.setCustomValidity(confirmar.value && confirmar.value !== senha.value ? 'As senhas não coincidem.' : '');
    }
    senha.addEventListener('input', checarSenhas);
    confirmar.addEventListener('input', checarSenhas);
  }

  /* Upload de foto: tirar com câmera/webcam (getUserMedia) ou enviar arquivo.
     Usado em auth/google-completar-cadastro.php — no-op se os elementos não existem na página. */
  var btnAbrirCamera = document.getElementById('btnAbrirCamera');
  var fotoArquivo = document.getElementById('fotoArquivo');
  var fotoPreviewImg = document.getElementById('fotoPreviewImg');
  var cameraBox = document.getElementById('cameraBox');
  var cameraVideo = document.getElementById('cameraVideo');
  var cameraCanvas = document.getElementById('cameraCanvas');
  var btnCapturarFoto = document.getElementById('btnCapturarFoto');
  var btnCancelarCamera = document.getElementById('btnCancelarCamera');
  var cameraStream = null;

  function pararCamera() {
    if (cameraStream) {
      cameraStream.getTracks().forEach(function (track) { track.stop(); });
      cameraStream = null;
    }
    if (cameraBox) { cameraBox.hidden = true; }
  }

  function mostrarPreview(blobUrl) {
    if (!fotoPreviewImg) { return; }
    fotoPreviewImg.src = blobUrl;
    fotoPreviewImg.hidden = false;
  }

  if (btnAbrirCamera && cameraBox && cameraVideo) {
    btnAbrirCamera.addEventListener('click', function () {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('Seu navegador não tem suporte a câmera. Envie um arquivo.');
        return;
      }
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } }).then(function (stream) {
        cameraStream = stream;
        cameraVideo.srcObject = stream;
        cameraBox.hidden = false;
      }).catch(function () {
        alert('Não foi possível acessar a câmera. Você pode enviar um arquivo em vez disso.');
      });
    });
  }

  if (btnCapturarFoto && cameraCanvas && cameraVideo && fotoArquivo) {
    btnCapturarFoto.addEventListener('click', function () {
      cameraCanvas.width = cameraVideo.videoWidth;
      cameraCanvas.height = cameraVideo.videoHeight;
      var ctx = cameraCanvas.getContext('2d');
      ctx.drawImage(cameraVideo, 0, 0);
      cameraCanvas.toBlob(function (blob) {
        if (!blob) { return; }
        try {
          var file = new File([blob], 'foto.jpg', { type: 'image/jpeg' });
          var dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);
          fotoArquivo.files = dataTransfer.files;
        } catch (err) {
          /* Navegador sem suporte a DataTransfer/File — segue só com a prévia. */
        }
        mostrarPreview(URL.createObjectURL(blob));
        pararCamera();
      }, 'image/jpeg', 0.9);
    });
  }

  if (btnCancelarCamera) {
    btnCancelarCamera.addEventListener('click', pararCamera);
  }

  if (fotoArquivo) {
    fotoArquivo.addEventListener('change', function () {
      if (fotoArquivo.files && fotoArquivo.files[0]) {
        mostrarPreview(URL.createObjectURL(fotoArquivo.files[0]));
      }
    });
  }

  /* Menu "Bem-vindo, {nome}" (desktop, [data-dropdown]) — abre/fecha no clique
     do botão, fecha clicando fora ou com Esc. Só um por vez (se existisse mais
     de um dropdown no futuro, abrir um fecha os outros). */
  var dropdowns = document.querySelectorAll('[data-dropdown]');
  function fecharDropdowns(exceto) {
    dropdowns.forEach(function (dd) {
      if (dd === exceto) { return; }
      dd.classList.remove('is-open');
      var trigger = dd.querySelector('.nav-dropdown-trigger');
      if (trigger) { trigger.setAttribute('aria-expanded', 'false'); }
    });
  }
  dropdowns.forEach(function (dd) {
    var trigger = dd.querySelector('.nav-dropdown-trigger');
    if (!trigger) { return; }
    trigger.addEventListener('click', function (ev) {
      ev.stopPropagation();
      var abrir = !dd.classList.contains('is-open');
      fecharDropdowns();
      dd.classList.toggle('is-open', abrir);
      trigger.setAttribute('aria-expanded', String(abrir));
    });
  });
  document.addEventListener('click', function () { fecharDropdowns(); });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') { fecharDropdowns(); }
  });

  /* Padrão "lista primeiro": botão [data-toggle-target="id"] mostra/esconde um
     bloco escondido por padrão (formulário de cadastro) — usado no painel da
     equipe pra manter a tela limpa no mobile (lista em cima, formulário só
     aparece ao apertar "+"). Se o bloco já começa aberto (ex.: formulário de
     edição pré-preenchido, com erro de validação pra corrigir), o botão parte
     como "Cancelar". */
  document.querySelectorAll('[data-toggle-target]').forEach(function (btn) {
    var target = document.getElementById(btn.getAttribute('data-toggle-target'));
    if (!target) { return; }
    var textoAbrir = btn.textContent;
    var textoFechar = btn.getAttribute('data-toggle-close-text') || 'Cancelar';
    function sync() {
      var aberto = !target.hidden;
      btn.setAttribute('aria-expanded', String(aberto));
      btn.textContent = aberto ? textoFechar : textoAbrir;
    }
    btn.addEventListener('click', function () {
      target.hidden = !target.hidden;
      sync();
      if (!target.hidden) {
        target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });
    sync();
  });
})();
