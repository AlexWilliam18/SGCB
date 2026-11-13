/**
 * Validações e máscaras de CPF, CNPJ, e-mail e telefone usadas nos cadastros.
 * Fica em window.Validacao para os scripts das páginas (cadastro-cliente.js, cadastro-usuario.js,
 * cadastro-contrato.js). As mesmas regras são conferidas de novo no PHP (Modelo::validarEmail,
 * Modelo::validarTelefone e Cliente::validarCpfCnpj), porque o navegador pode ser burlado.
 */
window.Validacao = (() => {
  const digitos = valor => String(valor ?? '').replace(/\D/g, '');

  // Calcula os dois dígitos verificadores a partir da base e compara com o número completo.
  // pesos: uma lista de pesos para cada dígito verificador (CPF 10..2 e 11..2; CNPJ 5..2,9..2 e 6..2,9..2).
  function conferirDigitos(numero, tamanhoBase, pesos) {
    let base = numero.slice(0, tamanhoBase);
    for (const p of pesos) {
      const resto = [...base].reduce((soma, n, i) => soma + Number(n) * p[i], 0) % 11;
      base += String(resto < 2 ? 0 : 11 - resto);
    }
    return base === numero;
  }

  // CPF: 11 dígitos, não pode ser tudo igual (ex.: 111.111.111-11) e os verificadores têm de bater.
  function cpf(valor) {
    const d = digitos(valor);
    if (!/^\d{11}$/.test(d) || new Set(d).size === 1) return false;
    return conferirDigitos(d, 9, [
      [10, 9, 8, 7, 6, 5, 4, 3, 2],
      [11, 10, 9, 8, 7, 6, 5, 4, 3, 2],
    ]);
  }

  // CNPJ: 14 dígitos, mesma ideia do CPF com outros pesos.
  function cnpj(valor) {
    const d = digitos(valor);
    if (!/^\d{14}$/.test(d) || new Set(d).size === 1) return false;
    return conferirDigitos(d, 12, [
      [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
      [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
    ]);
  }

  // Aceita CPF ou CNPJ, decidindo pela quantidade de dígitos.
  const cpfCnpj = valor => (digitos(valor).length === 14 ? cnpj(valor) : cpf(valor));

  // E-mail: nome@dominio.tld, sem espaços, domínio com ponto e extensão de 2+ letras.
  // (O type="email" do navegador aceita "nome@empresa", sem extensão; aqui isso é recusado.)
  function email(valor) {
    const e = String(valor ?? '').trim();
    if (e.length > 254) return false;
    return /^[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,}$/.test(e)
      && !/\.\./.test(e) && !e.startsWith('.') && !e.includes('.@');
  }

  // Telefone brasileiro com DDD: fixo (10 dígitos, começa com 2 a 5) ou celular (11 dígitos, começa com 9).
  // DDD: dois dígitos de 11 a 99, sem zero (não existe DDD 10, 20, 30...).
  function telefone(valor) {
    const d = digitos(valor);
    if (!/^[1-9][1-9]/.test(d)) return false;
    if (d.length === 11) return d[2] === '9';
    if (d.length === 10) return /[2-5]/.test(d[2]);
    return false;
  }

  // Máscara enquanto digita: 000.000.000-00 (até 11 dígitos) ou 00.000.000/0000-00 (12 a 14).
  function mascaraCpfCnpj(valor) {
    const d = digitos(valor).slice(0, 14);
    if (d.length <= 11) return mascaraCpf(d);
    return d
      .replace(/^(\d{2})(\d)/, '$1.$2')
      .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d)/, '.$1/$2')
      .replace(/(\d{4})(\d)/, '$1-$2');
  }

  // Máscara só de CPF (campo do responsável no contrato).
  function mascaraCpf(valor) {
    return digitos(valor)
      .slice(0, 11)
      .replace(/^(\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
  }

  // Máscara enquanto digita, número a número:
  //   1 → (1   12 → (12   123 → (12) 3   1234567 → (12) 3456-7
  // Celular (3º dígito 9) já usa o formato de 5 + 4 desde o começo, para o hífen não "pular" no 11º dígito:
  //   1298765 → (12) 98765   12987654321 → (12) 98765-4321
  // Fixo: 1234567890 → (12) 3456-7890
  function mascaraTelefone(valor) {
    const d = digitos(valor).slice(0, 11);
    if (!d) return '';
    if (d.length <= 2) return '(' + d;
    const ddd = `(${d.slice(0, 2)}) `,
      numero = d.slice(2),
      corte = numero[0] === '9' ? 5 : 4; // celular: 5 dígitos antes do hífen; fixo: 4
    return numero.length <= corte ? ddd + numero : `${ddd}${numero.slice(0, corte)}-${numero.slice(corte)}`;
  }

  // Máscara de dinheiro enquanto digita, preenchendo a partir dos centavos (como em app de banco):
  //   5 → 0,05   50 → 0,50   500 → 5,00   150000 → 1.500,00
  // Até 12 dígitos inteiros + 2 de centavos (o limite que a API aceita). Sem dígitos → vazio.
  function mascaraMoeda(valor) {
    const d = digitos(valor).replace(/^0+/, '').slice(0, 14);
    if (!d) return '';
    const centavos = d.padStart(3, '0'); // garante "0,0X" com 1 ou 2 dígitos
    const inteiro = centavos.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); // pontos de milhar
    return `${inteiro},${centavos.slice(-2)}`;
  }

  // Mesma máscara com o "R$ " na frente (campos de valor do cadastro de produto).
  const mascaraReais = valor => (mascaraMoeda(valor) ? 'R$ ' + mascaraMoeda(valor) : '');

  // "1.234,56" ou "R$ 1.234,56" → 1234.56 (número). Vazio → 0. Usado para enviar à API e somar na tela.
  function valorMoeda(texto) {
    const limpo = String(texto ?? '').replace(/[^\d,]/g, '');
    return limpo ? Number(limpo.replace(',', '.')) || 0 : 0;
  }

  // Formata um número vindo da API (ex.: 1500 ou "1500.00") para "1.500,00".
  const formatarMoeda = numero => mascaraMoeda((Number(numero) || 0).toFixed(2)) || '0,00';

  /**
   * Aplica a máscara mantendo o cursor no lugar certo, também ao corrigir no meio do número.
   * O cursor é guardado como "quantos dígitos há antes dele" e recolocado depois do mesmo
   * número de dígitos no texto já formatado.
   * apagouSimbolo: o Backspace/Delete apagou só um símbolo da máscara ( "(", ")", " ", "-" ),
   * então apaga junto o dígito vizinho; senão a máscara recolocaria o símbolo e o cursor ficaria preso.
   * Função pura (sem DOM), para poder ser testada.
   */
  function formatarComCursor(valor, cursor, mascara, apagouSimbolo = '') {
    let d = digitos(valor),
      antes = digitos(valor.slice(0, cursor)).length;
    if (apagouSimbolo === 'tras' && antes > 0) {
      d = d.slice(0, antes - 1) + d.slice(antes);
      antes--;
    } else if (apagouSimbolo === 'frente') {
      d = d.slice(0, antes) + d.slice(antes + 1);
    }
    const formatado = mascara(d);
    // Posição logo depois do "antes"-ésimo dígito no texto formatado.
    let pos = 0;
    for (let vistos = 0; pos < formatado.length && vistos < antes; pos++) if (/\d/.test(formatado[pos])) vistos++;
    return { valor: formatado, cursor: antes === 0 ? Math.min(pos, formatado.length) : pos };
  }

  /**
   * Liga máscara e conferência a um campo:
   *  - máscara a cada tecla (se houver), formatando conforme a pessoa digita;
   *    cursorNoFim: deixa o cursor sempre no fim (dinheiro, que cresce a partir dos centavos);
   *  - ao sair do campo, mostra o erro na hora, sem esperar o "Salvar" (se houver regra).
   * mostrarErro(campo, mensagem) é da página, porque cada tela exibe o erro de um jeito;
   * mensagem vazia ('') limpa o erro. Campo vazio não é conferido aqui: "obrigatório" fica no Salvar.
   */
  function ligar(campo, { mascara, regra, mensagem, mostrarErro, cursorNoFim = false }) {
    if (!campo) return;
    if (mascara) {
      let anterior = campo.value; // texto antes da tecla, para saber se só um símbolo foi apagado
      campo.addEventListener('focus', () => (anterior = campo.value)); // valor pode ter sido preenchido pelo script
      campo.addEventListener('input', evento => {
        const apagando = evento.inputType || '';
        const soSimbolo = apagando.startsWith('delete') && digitos(campo.value) === digitos(anterior);
        const direcao = !soSimbolo ? '' : apagando === 'deleteContentForward' ? 'frente' : 'tras';
        const { valor, cursor } = formatarComCursor(campo.value, campo.selectionStart ?? campo.value.length, mascara, direcao);
        campo.value = valor;
        // setSelectionRange só funciona com o campo em foco (ex.: não em preenchimento automático).
        const pos = cursorNoFim ? campo.value.length : cursor;
        if (document.activeElement === campo) campo.setSelectionRange(pos, pos);
        anterior = campo.value;
      });
    }
    if (!regra) return;
    campo.addEventListener('blur', () => {
      if (!campo.value.trim()) return;
      mostrarErro(campo, regra(campo.value) ? '' : mensagem);
    });
  }

  // Atalho para campos de dinheiro: máscara de moeda a cada tecla, cursor no fim e,
  // se o campo ficar vazio ao sair, volta para "0,00" (ou vazio, se vazioPermitido).
  function ligarMoeda(campo, { comReais = false, vazioPermitido = false } = {}) {
    if (!campo) return;
    ligar(campo, { mascara: comReais ? mascaraReais : mascaraMoeda, cursorNoFim: true });
    campo.addEventListener('blur', () => {
      if (!campo.value && !vazioPermitido) campo.value = comReais ? 'R$ 0,00' : '0,00';
    });
  }

  return { digitos, cpf, cnpj, cpfCnpj, email, telefone, mascaraCpf, mascaraCpfCnpj, mascaraTelefone, mascaraMoeda, mascaraReais, valorMoeda, formatarMoeda, formatarComCursor, ligar, ligarMoeda };
})();
