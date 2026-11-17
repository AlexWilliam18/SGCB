'use strict';
/**
 * Teste manual das buscas de CEP (public/assets/js/cep.js) e CNPJ (public/assets/js/cnpj.js).
 *
 * Roda o código real dos dois arquivos com a rede (fetch) e os campos da tela simulados:
 * não acessa a internet nem o banco. Cobre o achado A05 do relatório de testes de 14/11/2026
 * (resposta atrasada, digitação durante a busca e troca para CEP geral) e o preenchimento pelo CNPJ.
 *
 * Como rodar (na raiz do projeto):
 *   node tests/manual/teste_cnpj_cep.js
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let falhas = 0;
let total = 0;
function confere(condicao, descricao) {
  total++;
  console.log((condicao ? '  OK      ' : '  FALHOU  ') + descricao);
  if (!condicao) falhas++;
}
function secao(titulo) {
  console.log('\n== ' + titulo);
}

// ---------------------------------------------------------------------------
// Simulações mínimas do navegador: campo de formulário com eventos e fetch controlável.
// ---------------------------------------------------------------------------

class Campo {
  constructor(tagName = 'INPUT', value = '') {
    this.tagName = tagName;
    this.value = value;
    this.disabled = false;
    this.readOnly = false;
    this.ouvintes = {};
    this.atributos = {};
    this.focado = false;
    this.eventos = []; // eventos disparados (para conferir "marca não salvo")
  }
  addEventListener(tipo, fn) {
    (this.ouvintes[tipo] ||= []).push(fn);
  }
  dispatchEvent(evento) {
    this.eventos.push(evento.type);
    (this.ouvintes[evento.type] || []).forEach(fn => fn(evento));
    return true;
  }
  setAttribute(nome, valor) {
    this.atributos[nome] = valor;
  }
  removeAttribute(nome) {
    delete this.atributos[nome];
  }
  focus() {
    this.focado = true;
  }
  // Pessoa digitando: troca o valor e dispara "input", como o navegador faz.
  digitar(valor) {
    this.value = valor;
    this.dispatchEvent({ type: 'input' });
  }
}

// fetch falso: cada chamada fica pendente até o teste responder (permite simular demora e ordem).
const pendentes = [];
function fetchFalso(url, opcoes = {}) {
  return new Promise((resolve, reject) => {
    const item = { url, resolve, reject, sinal: opcoes.signal };
    opcoes.signal?.addEventListener('abort', () => {
      const erro = new Error('abortado');
      erro.name = 'AbortError';
      reject(erro);
    });
    pendentes.push(item);
  });
}
function responder(item, status, corpo) {
  item.resolve({ status, ok: status >= 200 && status < 300, json: async () => corpo });
}
const esperar = () => new Promise(r => setImmediate(r));
async function esvaziar() {
  for (let i = 0; i < 10; i++) await esperar();
}

// Carrega os arquivos reais no mesmo "window".
const contexto = {
  window: {},
  fetch: fetchFalso,
  AbortController,
  Event: class {
    constructor(type, opcoes) {
      this.type = type;
      this.bubbles = opcoes?.bubbles;
    }
  },
  setTimeout,
  clearTimeout,
  console,
};
vm.createContext(contexto);
for (const arquivo of ['cep.js', 'cnpj.js']) {
  // SGCB_JS_DIR permite rodar contra outra cópia dos scripts (ex.: versão antiga, para ver o teste falhar).
  const pasta = process.env.SGCB_JS_DIR || path.join(__dirname, '../../public/assets/js');
  const codigo = fs.readFileSync(path.join(pasta, arquivo), 'utf8');
  vm.runInContext(codigo, contexto, { filename: arquivo });
}
const { BuscaCep, BuscaCnpj } = contexto.window;

function formularioEndereco(valores = {}) {
  const campos = {
    cep: new Campo(),
    rua: new Campo('INPUT', valores.rua || ''),
    numero: new Campo('INPUT', valores.numero || ''),
    bairro: new Campo('INPUT', valores.bairro || ''),
    cidade: new Campo('INPUT', valores.cidade || ''),
    uf: new Campo('SELECT', valores.uf || ''),
  };
  const status = { textContent: '', dataset: {} };
  BuscaCep.ligar(campos, status);
  return { campos, status };
}

const viaCep = (logradouro, bairro, localidade, uf) => ({ logradouro, bairro, localidade, uf });

(async () => {
  // -------------------------------------------------------------------------
  secao('CEP: máscara e preenchimento');
  confere(BuscaCep.mascaraCep('12245100') === '12245-100', 'máscara 12245-100');
  confere(BuscaCep.mascaraCep('12a3') === '123', 'máscara ignora letras');
  {
    const { campos, status } = formularioEndereco();
    campos.cep.digitar('12245100');
    confere(pendentes.length === 1 && pendentes[0].url.includes('viacep.com.br/ws/12245100'), 'consulta o ViaCEP ao completar 8 dígitos');
    responder(pendentes.shift(), 200, viaCep('Rua A', 'Centro', 'São José dos Campos', 'SP'));
    await esvaziar();
    confere(campos.rua.value === 'Rua A' && campos.bairro.value === 'Centro', 'rua e bairro preenchidos');
    confere(campos.cidade.value === 'São José dos Campos' && campos.uf.value === 'SP', 'cidade e UF preenchidas');
    confere(campos.numero.focado, 'cursor vai para o Número');
    confere(campos.rua.eventos.includes('input') && campos.uf.eventos.includes('change'), 'dispara input/change (a tela marca "não salvo")');
    confere(status.textContent === '', 'sem aviso quando o endereço vem completo');
  }

  // -------------------------------------------------------------------------
  secao('A05: digitação durante a espera é preservada');
  {
    const { campos, status } = formularioEndereco();
    campos.cep.digitar('12245100');
    campos.rua.value = 'Rua digitada pela pessoa'; // digitou enquanto a resposta não chegava
    responder(pendentes.shift(), 200, viaCep('Rua do CEP', 'Bairro do CEP', 'São José dos Campos', 'SP'));
    await esvaziar();
    confere(campos.rua.value === 'Rua digitada pela pessoa', 'rua digitada durante a busca NÃO é sobrescrita');
    confere(campos.bairro.value === 'Bairro do CEP', 'campos não mexidos recebem o endereço do CEP');
    confere(/mantidos/.test(status.textContent), 'aviso explica que os campos digitados foram mantidos');
    confere(!campos.numero.focado, 'cursor não pula de campo enquanto a pessoa digita');
  }

  // -------------------------------------------------------------------------
  secao('A05: CEP completo → CEP geral não mistura endereços');
  {
    const { campos, status } = formularioEndereco();
    campos.cep.digitar('12245100');
    responder(pendentes.shift(), 200, viaCep('Rua Antiga', 'Bairro Antigo', 'São José dos Campos', 'SP'));
    await esvaziar();
    campos.cep.digitar('11660000'); // CEP geral de outra cidade
    responder(pendentes.shift(), 200, viaCep('', '', 'Caraguatatuba', 'SP'));
    await esvaziar();
    confere(campos.cidade.value === 'Caraguatatuba', 'cidade atualizada para a do CEP novo');
    confere(campos.rua.value === '' && campos.bairro.value === '', 'rua e bairro do CEP anterior são limpos');
    confere(/informe a rua/.test(status.textContent), 'pede a rua e o bairro do CEP geral');
    confere(campos.rua.focado, 'cursor vai para a Rua');
  }

  // -------------------------------------------------------------------------
  secao('A05: resposta atrasada é descartada');
  {
    const { campos } = formularioEndereco();
    campos.cep.digitar('12245100');
    const primeira = pendentes.shift();
    campos.cep.digitar('12020000'); // trocou o CEP antes da 1ª resposta
    const segunda = pendentes.shift();
    confere(primeira.sinal.aborted, 'a consulta do CEP antigo é cancelada');
    responder(segunda, 200, viaCep('Rua Nova', 'Centro', 'Taubaté', 'SP'));
    await esvaziar();
    confere(campos.rua.value === 'Rua Nova' && campos.cidade.value === 'Taubaté', 'vale só a resposta do CEP atual');
  }
  {
    const { campos } = formularioEndereco({ rua: 'Rua já digitada' });
    campos.cep.digitar('12245100');
    campos.cep.digitar('1224'); // apagou dígitos: a consulta pendente perde a validade
    const item = pendentes.shift();
    confere(item.sinal.aborted, 'apagar dígitos cancela a consulta pendente');
    confere(campos.rua.value === 'Rua já digitada', 'nada é alterado depois do cancelamento');
  }

  // -------------------------------------------------------------------------
  secao('CEP: falhas e reserva');
  {
    const { campos, status } = formularioEndereco();
    campos.cep.digitar('99999999');
    responder(pendentes.shift(), 200, { erro: true });
    await esvaziar();
    confere(/não encontrado/.test(status.textContent), 'CEP inexistente avisa e não preenche');
  }
  {
    const { campos, status } = formularioEndereco();
    campos.cep.digitar('12245100');
    responder(pendentes.shift(), 500, null); // ViaCEP fora do ar
    await esvaziar();
    const reserva = pendentes.shift();
    confere(reserva && reserva.url.includes('brasilapi.com.br/api/cep'), 'ViaCEP fora do ar → tenta a BrasilAPI');
    responder(reserva, 200, { street: 'Rua Reserva', neighborhood: 'Centro', city: 'Jacareí', state: 'SP' });
    await esvaziar();
    confere(campos.rua.value === 'Rua Reserva' && campos.uf.value === 'SP', 'endereço da BrasilAPI preenchido');
    confere(status.textContent === '', 'sem aviso de erro');
  }
  {
    const { campos, status } = formularioEndereco();
    campos.cep.digitar('12245100');
    responder(pendentes.shift(), 500, null);
    await esvaziar();
    responder(pendentes.shift(), 500, null);
    await esvaziar();
    confere(/Preencha o endereço manualmente/.test(status.textContent), 'os dois serviços fora do ar → aviso, sem bloquear');
  }
  {
    const { campos } = formularioEndereco({ rua: 'Rua salva', cidade: 'Taubaté' });
    campos.cep.value = '12020-000'; // edição: CEP já salvo
    campos.cep.dispatchEvent({ type: 'focus' });
    campos.cep.dispatchEvent({ type: 'blur' });
    confere(pendentes.length === 0, 'passar pelo CEP salvo sem mudar não consulta (edição preservada)');
  }

  // -------------------------------------------------------------------------
  secao('CNPJ: preenche só os campos vazios');
  function formularioCnpj(valores = {}) {
    const documento = new Campo();
    const endereco = {
      cep: new Campo(),
      rua: new Campo(),
      numero: new Campo(),
      bairro: new Campo(),
      cidade: new Campo(),
      uf: new Campo('SELECT'),
      complemento: new Campo(),
    };
    const campos = {
      nome_razao_social: new Campo('INPUT', valores.nome || ''),
      nome_fantasia: new Campo(),
      email: new Campo('INPUT', valores.email || ''),
      telefone: new Campo(),
      endereco,
    };
    const status = { textContent: '', dataset: {} };
    BuscaCnpj.ligar(documento, campos, status, {
      valido: cnpj => cnpj !== '11111111111111',
      formatar: { cep: BuscaCep.mascaraCep, telefone: v => '(' + v.slice(0, 2) + ') ' + v.slice(2) },
    });
    return { documento, campos, status };
  }
  const receita = {
    razao_social: 'EMPRESA EXEMPLO LTDA',
    nome_fantasia: 'EXEMPLO',
    email: 'CONTATO@EXEMPLO.COM.BR',
    ddd_telefone_1: '1233221100',
    cep: '12245100',
    descricao_tipo_de_logradouro: 'RUA',
    logradouro: 'DAS FLORES',
    numero: '10',
    complemento: 'SALA 2',
    bairro: 'CENTRO',
    municipio: 'SAO JOSE DOS CAMPOS',
    uf: 'SP',
  };
  {
    const { documento, campos, status } = formularioCnpj({ nome: 'Nome digitado' });
    documento.digitar('33.444.555/0001-81');
    const item = pendentes.shift();
    confere(item && item.url.endsWith('/api/cnpj/v1/33444555000181'), 'consulta a BrasilAPI com o CNPJ só com números');
    responder(item, 200, receita);
    await esvaziar();
    confere(campos.nome_razao_social.value === 'Nome digitado', 'razão social digitada NÃO é substituída');
    confere(campos.nome_fantasia.value === 'EXEMPLO' && campos.email.value === 'contato@exemplo.com.br', 'fantasia e e-mail (minúsculo) preenchidos');
    confere(campos.telefone.value === '(12) 33221100', 'telefone com a máscara da tela');
    confere(campos.endereco.cep.value === '12245-100' && campos.endereco.rua.value === 'RUA DAS FLORES', 'CEP e rua (tipo + logradouro) preenchidos');
    confere(!campos.endereco.cep.eventos.includes('input'), 'CEP preenchido sem disparar a busca de CEP por cima');
    confere(campos.endereco.cidade.value === 'SAO JOSE DOS CAMPOS' && campos.endereco.uf.value === 'SP', 'cidade e UF preenchidas');
    confere(/campos vazios/.test(status.textContent), 'aviso pede para conferir antes de salvar');
  }
  {
    const { documento } = formularioCnpj();
    documento.digitar('111.111.111-11'); // CPF: não consulta
    confere(pendentes.length === 0, 'CPF (11 dígitos) não consulta CNPJ');
    documento.digitar('11.111.111/1111-11');
    confere(pendentes.length === 0, 'CNPJ inválido não consulta');
  }
  {
    const { documento, campos, status } = formularioCnpj();
    documento.digitar('33444555000181');
    responder(pendentes.shift(), 404, null);
    await esvaziar();
    confere(/não encontrado/.test(status.textContent) && campos.nome_fantasia.value === '', 'CNPJ não encontrado avisa e não preenche');
  }
  {
    const { documento, campos } = formularioCnpj();
    documento.digitar('33444555000181');
    const antiga = pendentes.shift();
    documento.digitar('3344455500'); // apagou parte do CNPJ
    confere(antiga.sinal.aborted, 'mudar o CNPJ cancela a consulta antiga');
    confere(campos.nome_razao_social.value === '', 'resposta antiga não preenche nada');
  }
  {
    const { documento, status } = formularioCnpj();
    documento.digitar('33444555000181');
    responder(pendentes.shift(), 500, null);
    await esvaziar();
    confere(/manualmente/.test(status.textContent), 'serviço fora do ar → aviso, sem bloquear o cadastro');
  }

  console.log('\n' + (falhas ? `${falhas} FALHA(S) em ${total} verificações` : `TODOS OS ${total} TESTES PASSARAM`));
  process.exitCode = falhas ? 1 : 0;
})();
