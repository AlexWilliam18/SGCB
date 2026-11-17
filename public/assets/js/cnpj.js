'use strict';
/**
 * Busca dos dados da empresa pelo CNPJ, usada no cadastro de cliente/fornecedor.
 * Fica em window.BuscaCnpj.
 *
 * Ao completar um CNPJ válido (14 dígitos), consulta a BrasilAPI (https://brasilapi.com.br),
 * gratuita e sem chave, e preenche razão social, nome fantasia, e-mail, telefone e endereço.
 *
 * Regras (as mesmas lições do achado A05 da busca de CEP):
 *   - só preenche campo VAZIO: nada que a pessoa digitou é substituído;
 *   - resposta atrasada (o CNPJ mudou no meio da consulta) é descartada;
 *   - se o serviço falhar, nada é bloqueado: o cadastro pode ser digitado normalmente.
 */
window.BuscaCnpj = (() => {
  const TEMPO_LIMITE = 8000; // ms

  const digitos = valor => String(valor ?? '').replace(/\D/g, '');

  /**
   * Consulta o CNPJ e devolve os dados no formato do formulário:
   * { nome_razao_social, nome_fantasia, email, telefone, endereco: { cep, rua, numero, bairro, cidade, uf, complemento } },
   * null se o CNPJ não existe na base, ou lança erro se o serviço não respondeu.
   */
  async function consultar(cnpj, sinalExterno) {
    const controle = new AbortController();
    const cancelar = () => controle.abort();
    const relogio = setTimeout(cancelar, TEMPO_LIMITE);
    sinalExterno?.addEventListener('abort', cancelar);
    try {
      const resposta = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`, {
        signal: controle.signal,
        headers: { Accept: 'application/json' },
      });
      if (resposta.status === 404 || resposta.status === 400) return null;
      if (!resposta.ok) throw new Error('Serviço de CNPJ indisponível.');
      const d = await resposta.json();
      // Logradouro vem separado do tipo ("RUA" + "DAS FLORES"); junta os dois quando o tipo não estiver repetido.
      const tipo = (d.descricao_tipo_de_logradouro || '').trim();
      const logradouro = (d.logradouro || '').trim();
      const rua = tipo && !logradouro.toUpperCase().startsWith(tipo.toUpperCase()) ? `${tipo} ${logradouro}` : logradouro;
      return {
        nome_razao_social: d.razao_social || '',
        nome_fantasia: d.nome_fantasia || '',
        email: (d.email || '').toLowerCase(),
        telefone: digitos(d.ddd_telefone_1 || ''),
        endereco: {
          cep: digitos(d.cep || ''),
          rua,
          numero: d.numero || '',
          bairro: d.bairro || '',
          cidade: d.municipio || '',
          uf: d.uf || '',
          complemento: d.complemento || '',
        },
      };
    } finally {
      clearTimeout(relogio);
      sinalExterno?.removeEventListener('abort', cancelar);
    }
  }

  /**
   * Liga a busca ao campo de CPF/CNPJ.
   * documento: <input> do CPF/CNPJ.
   * campos: { nome_razao_social, nome_fantasia, email, telefone, endereco: { cep, rua, numero, bairro, cidade, uf, complemento } }.
   * status: elemento para os avisos. opcoes.valido(cnpj): confere os dígitos verificadores antes de consultar.
   * opcoes.formatar: { telefone(valor), cep(valor) } para aplicar as máscaras da tela.
   */
  function ligar(documento, campos, status, opcoes = {}) {
    let consultado = '',
      emAndamento = null;

    const avisar = (texto, tipo = '') => {
      if (!status) return;
      status.textContent = texto;
      status.dataset.tipo = tipo;
    };

    // Preenche só se o campo estiver vazio; avisa a página (marca "não salvo" e limpa erro).
    // Devolve true se preencheu.
    function preencherVazio(campo, valor, comEvento = true) {
      if (!campo || !valor || campo.disabled || campo.readOnly || String(campo.value).trim() !== '') return false;
      campo.value = valor;
      if (comEvento) campo.dispatchEvent(new Event(campo.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true }));
      return true;
    }

    async function buscar() {
      const cnpj = digitos(documento.value);
      if (cnpj.length !== 14 || cnpj === consultado) return;
      if (opcoes.valido && !opcoes.valido(cnpj)) return; // CNPJ inválido: o erro do campo já aparece
      consultado = cnpj;
      emAndamento?.abort();
      const controle = (emAndamento = new AbortController());
      avisar('Buscando dados do CNPJ…', 'carregando');
      try {
        const dados = await consultar(cnpj, controle.signal);
        // Resposta antiga: o documento mudou enquanto ela chegava.
        if (controle.signal.aborted || digitos(documento.value) !== cnpj) return;
        if (!dados) {
          avisar('CNPJ não encontrado na base pública. Preencha os dados manualmente.', 'erro');
          return;
        }
        const fmt = opcoes.formatar || {};
        let preenchidos = 0;
        preenchidos += preencherVazio(campos.nome_razao_social, dados.nome_razao_social);
        preenchidos += preencherVazio(campos.nome_fantasia, dados.nome_fantasia);
        preenchidos += preencherVazio(campos.email, dados.email);
        preenchidos += preencherVazio(campos.telefone, fmt.telefone ? fmt.telefone(dados.telefone) : dados.telefone);
        const e = campos.endereco || {};
        // O CEP vai sem disparar o evento: assim a busca de CEP não roda por cima do endereço da Receita.
        const cepPreenchido = preencherVazio(e.cep, fmt.cep ? fmt.cep(dados.endereco.cep) : dados.endereco.cep, false);
        preenchidos += cepPreenchido;
        ['rua', 'numero', 'bairro', 'cidade', 'uf', 'complemento'].forEach(k => {
          preenchidos += preencherVazio(e[k], dados.endereco[k]);
        });
        if (cepPreenchido) e.cep.dispatchEvent(new Event('change', { bubbles: true })); // marca "não salvo"
        avisar(
          preenchidos
            ? 'Dados do CNPJ preenchidos nos campos vazios. Confira antes de salvar.'
            : 'CNPJ encontrado. Os campos já estavam preenchidos e foram mantidos.',
          'ok'
        );
      } catch {
        if (controle.signal.aborted) return;
        consultado = ''; // permite tentar de novo
        avisar('Não foi possível consultar o CNPJ agora. Preencha os dados manualmente.', 'erro');
      }
    }

    // Busca ao completar os 14 dígitos (digitando ou colando) e ao sair do campo.
    documento.addEventListener('input', () => {
      const n = digitos(documento.value).length;
      if (n < 14) {
        emAndamento?.abort();
        consultado = '';
        avisar('');
      }
      buscar();
    });
    documento.addEventListener('blur', buscar);
  }

  return { ligar, consultar };
})();
