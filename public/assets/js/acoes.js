'use strict';
/*
 * Ações dos diálogos de detalhes (Editar, Ativar/Inativar, Iniciar, Concluir, Registrar pagamento...).
 * Compartilhado pelas listagens: clientes.js, produtos.js, manutencoes.js e financeiro.js.
 * As gravações vão para a API com o token CSRF da sessão (window.SGCB_CSRF, definido no footer.php).
 */
window.SGCBAcoes = (() => {
  // POST em JSON na API. Se falhar, lança um erro com a mensagem que a API devolveu.
  async function executar(rota, dados = {}) {
    let response, data;
    try {
      response = await fetch(window.SGCB_API + rota, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.SGCB_CSRF || '' },
        body: JSON.stringify(dados),
      });
      data = await response.json().catch(() => null);
    } catch (_) {
      throw Error('Não foi possível falar com o servidor. Verifique a conexão.');
    }
    if (!response.ok) {
      // Junta a mensagem geral com as mensagens por campo (ex.: "Verifique os campos. Informe um valor...").
      const campos = data?.fields ? ' ' + Object.values(data.fields).join(' ') : '';
      throw Error((data?.error || 'Não foi possível concluir a ação.') + campos);
    }
    return data;
  }

  /*
   * Barra de botões no fim do diálogo. Cada botão é:
   *   { texto, href }                         → link (ex.: Editar abre a tela de cadastro);
   *   { texto, acao, confirmar?, classe? }    → botão que roda acao() (async); erros aparecem abaixo da barra.
   * classe: 'primaria' (verde) ou 'perigo' (vermelho).
   */
  function barra(container, botoes) {
    const actions = document.createElement('div'),
      feedback = document.createElement('p');
    actions.className = 'detail-actions';
    feedback.className = 'detail-feedback';
    feedback.setAttribute('role', 'alert');
    feedback.hidden = true;

    botoes.forEach(botao => {
      const el = document.createElement(botao.href ? 'a' : 'button');
      el.className = 'detail-action' + (botao.classe ? ' ' + botao.classe : '');
      el.textContent = botao.texto;
      if (botao.href) el.href = botao.href;
      else {
        el.type = 'button';
        el.addEventListener('click', async () => {
          if (botao.confirmar && !confirm(botao.confirmar)) return;
          const controles = actions.querySelectorAll('button');
          controles.forEach(b => (b.disabled = true));
          feedback.hidden = true;
          try {
            await botao.acao();
          } catch (erro) {
            feedback.textContent = erro.message || 'Não foi possível concluir a ação.';
            feedback.hidden = false;
          } finally {
            controles.forEach(b => (b.disabled = false));
          }
        });
      }
      actions.append(el);
    });

    container.append(actions, feedback);
    return { actions, feedback };
  }

  /*
   * Formulário curto dentro do diálogo (ex.: concluir manutenção, registrar pagamento).
   * campos: [{ nome, rotulo, tipo ('text' | 'date' | 'datetime-local' | 'select'), opcoes?, valor?, obrigatorio? }]
   * aoEnviar(dados) roda ao confirmar; erros aparecem abaixo do formulário.
   */
  function formulario(container, titulo, campos, textoBotao, aoEnviar) {
    const form = document.createElement('form'),
      heading = document.createElement('h3'),
      grid = document.createElement('div'),
      feedback = document.createElement('p'),
      submit = document.createElement('button');
    form.className = 'detail-form';
    form.noValidate = true;
    heading.textContent = titulo;
    grid.className = 'detail-form-grid';
    feedback.className = 'detail-feedback';
    feedback.setAttribute('role', 'alert');
    feedback.hidden = true;

    campos.forEach(campo => {
      const label = document.createElement('label'),
        texto = document.createElement('span'),
        input = document.createElement(campo.tipo === 'select' ? 'select' : 'input');
      texto.textContent = campo.rotulo + (campo.obrigatorio ? ' *' : '');
      input.name = campo.nome;
      if (campo.tipo === 'select') (campo.opcoes || []).forEach(([valor, nome]) => input.append(new Option(nome, valor)));
      else input.type = campo.tipo || 'text';
      if (campo.valor !== undefined) input.value = campo.valor;
      if (campo.placeholder) input.placeholder = campo.placeholder;
      label.append(texto, input);
      grid.append(label);
    });

    submit.type = 'submit';
    submit.className = 'detail-action primaria';
    submit.textContent = textoBotao;
    form.append(heading, grid, submit, feedback);
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const dados = Object.fromEntries(new FormData(form));
      const faltando = campos.find(c => c.obrigatorio && !String(dados[c.nome] || '').trim());
      if (faltando) {
        feedback.textContent = 'Preencha o campo ' + faltando.rotulo + '.';
        feedback.hidden = false;
        return;
      }
      submit.disabled = true;
      feedback.hidden = true;
      try {
        await aoEnviar(dados);
      } catch (erro) {
        feedback.textContent = erro.message || 'Não foi possível concluir a ação.';
        feedback.hidden = false;
      } finally {
        submit.disabled = false;
      }
    });

    container.append(form);
    return form;
  }

  // Data/hora atual no formato do <input type="datetime-local"> (fuso do navegador).
  function agoraLocal() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
  }

  return { executar, barra, formulario, agoraLocal };
})();
