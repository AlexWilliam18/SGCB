/**
 * Busca de endereço pelo CEP, usada no cadastro de cliente e no endereço de entrega do contrato.
 * Fica em window.BuscaCep.
 *
 * Ao completar os 8 dígitos do CEP, consulta o ViaCEP (https://viacep.com.br) e, se ele não
 * responder, o BrasilAPI (https://brasilapi.com.br). Os dois são gratuitos e não pedem chave.
 * Preenche rua, bairro, cidade e estado; o número e o complemento continuam com a pessoa.
 * Se a consulta falhar, nada é bloqueado: o endereço pode ser digitado normalmente.
 */
window.BuscaCep = (() => {
  const TEMPO_LIMITE = 6000; // ms; depois disso desiste e deixa a pessoa digitar

  const digitos = valor => String(valor ?? '').replace(/\D/g, '');
  // 12345678 → 12345-678, formatando a cada número digitado.
  const mascaraCep = valor => digitos(valor).slice(0, 8).replace(/(\d{5})(\d)/, '$1-$2');

  // fetch com tempo limite; o sinal externo cancela a consulta quando o CEP muda no meio dela.
  async function buscarJson(url, sinalExterno) {
    const controle = new AbortController();
    const cancelar = () => controle.abort();
    const relogio = setTimeout(cancelar, TEMPO_LIMITE);
    sinalExterno.addEventListener('abort', cancelar);
    try {
      const resposta = await fetch(url, { signal: controle.signal, headers: { Accept: 'application/json' } });
      return { status: resposta.status, dados: resposta.ok ? await resposta.json() : null };
    } finally {
      clearTimeout(relogio);
      sinalExterno.removeEventListener('abort', cancelar);
    }
  }

  /**
   * Consulta o CEP (8 dígitos) e devolve { rua, bairro, cidade, uf },
   * null se o CEP não existe, ou lança erro se nenhum serviço respondeu.
   */
  async function consultar(cep, sinal) {
    // 1º ViaCEP: CEP inexistente volta 200 com { erro: true } (ou "true", em texto).
    try {
      const { dados } = await buscarJson(`https://viacep.com.br/ws/${cep}/json/`, sinal);
      if (dados) {
        if (dados.erro) return null;
        return { rua: dados.logradouro || '', bairro: dados.bairro || '', cidade: dados.localidade || '', uf: dados.uf || '' };
      }
    } catch (erro) {
      if (sinal.aborted) throw erro; // CEP mudou: não tenta o reserva
    }
    // 2º BrasilAPI (reserva): CEP inexistente volta 404.
    const { status, dados } = await buscarJson(`https://brasilapi.com.br/api/cep/v1/${cep}`, sinal);
    if (status === 404) return null;
    if (!dados) throw new Error('Serviço de CEP indisponível.');
    return { rua: dados.street || '', bairro: dados.neighborhood || '', cidade: dados.city || '', uf: dados.state || '' };
  }

  // Troca o valor do campo e avisa a página (marca "não salvo", limpa erro, recalcula resumo).
  function preencher(campo, valor) {
    if (!campo || campo.disabled || campo.readOnly) return;
    campo.value = valor;
    campo.dispatchEvent(new Event(campo.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true }));
  }

  /**
   * Liga a busca a um formulário de endereço.
   * campos: { cep, rua, numero, bairro, cidade, uf } (elementos <input>/<select>).
   * status: elemento onde aparecem os avisos ("Buscando…", "CEP não encontrado…").
   */
  function ligar(campos, status) {
    const { cep } = campos;
    if (!cep) return;
    let consultado = '', // último CEP consultado (evita repetir a busca ao mexer no campo)
      emAndamento = null, // AbortController da consulta atual
      aoEntrar = '', // CEP no momento em que o campo recebeu o foco
      falhou = false; // a última consulta não teve resposta (vale tentar de novo)

    const avisar = (texto, tipo = '') => {
      if (!status) return;
      status.textContent = texto;
      status.dataset.tipo = tipo; // '' | 'carregando' | 'ok' | 'erro' (cor no CSS)
    };

    async function buscar() {
      const numero = digitos(cep.value);
      if (numero.length !== 8 || numero === consultado || cep.disabled) return;
      consultado = numero;
      falhou = false;
      emAndamento?.abort();
      const controle = (emAndamento = new AbortController());
      avisar('Buscando endereço…', 'carregando');
      cep.setAttribute('aria-busy', 'true');
      try {
        const endereco = await consultar(numero, controle.signal);
        if (controle.signal.aborted) return;
        if (!endereco) {
          avisar('CEP não encontrado. Confira o número ou preencha o endereço.', 'erro');
          return;
        }
        // CEP geral de cidade pequena vem sem rua e bairro: só a cidade e o estado são preenchidos.
        preencher(campos.cidade, endereco.cidade);
        preencher(campos.uf, endereco.uf);
        if (endereco.rua) preencher(campos.rua, endereco.rua);
        if (endereco.bairro) preencher(campos.bairro, endereco.bairro);
        // Endereço completo: sem aviso (o cursor já vai para o Número). CEP só da cidade: pede rua e bairro.
        avisar(endereco.rua ? '' : 'CEP da cidade: informe a rua e o bairro.', endereco.rua ? '' : 'ok');
        (endereco.rua ? campos.numero : campos.rua)?.focus();
      } catch {
        if (controle.signal.aborted) return;
        consultado = ''; // permite tentar de novo saindo e voltando ao campo
        falhou = true;
        avisar('Não foi possível consultar o CEP agora. Preencha o endereço manualmente.', 'erro');
      } finally {
        if (emAndamento === controle) cep.removeAttribute('aria-busy');
      }
    }

    // Máscara a cada número e busca assim que o 8º dígito é digitado (ou colado).
    cep.addEventListener('input', () => {
      cep.value = mascaraCep(cep.value);
      if (digitos(cep.value).length < 8) {
        emAndamento?.abort();
        consultado = '';
        avisar('');
      }
      buscar();
    });
    // Saindo do campo, busca só se o CEP mudou ou se a última consulta falhou. Assim, na edição,
    // passar pelo CEP já salvo não sobrescreve o endereço gravado.
    cep.addEventListener('focus', () => (aoEntrar = digitos(cep.value)));
    cep.addEventListener('blur', () => {
      if (falhou || digitos(cep.value) !== aoEntrar) buscar();
    });
  }

  return { ligar, consultar, mascaraCep };
})();
