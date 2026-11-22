# Disponibilidade futura

Em **Contratos de Locação → Consultar disponibilidade**, ao lado de **Novo contrato**,
escolha produto, início, fim e quantidade. Em **Produtos → Ver produto**, a seção
**Próximas disponibilidades** mostra mudanças na quantidade prevista para locações
de um dia e leva à consulta em Contratos de Locação com o produto preenchido. Produtos ativos
novos entram automaticamente, sem lista fixa de códigos ou tamanhos.

No cadastro de contrato, a seleção existente de contêineres define a quantidade.
O aviso junto aos itens informa a previsão para todo o período, as unidades já
livres e as condicionadas. **Atualizar disponibilidade / tentar novamente** refaz
a leitura. Rascunhos continuam sem reservar unidades; a consulta também não grava
estoque nem agenda.

## Cálculo e datas

`src/Models/Disponibilidade.php` concentra o cálculo. `UnidadeFisica::livresNoPeriodo`
delega a ele; contratos e agendamento de manutenção também usam essa regra.
Cada unidade só é contada se puder atender ao intervalo completo, sem combinar
contêineres diferentes em dias diferentes. A consulta retorna apenas dados do
produto e totais operacionais, mediante permissão `contratos/visualizar`.
A linha do tempo nos detalhes do produto mantém a permissão `produtos/visualizar`.

As datas seguem **America/Sao_Paulo**. O último dia informado é **inclusivo**:
23/11 a 30/11 ocupa de 23/11 00:00 até 01/12 00:00. São considerados estados,
agenda, reservas, locações, manutenções, devoluções registradas e cancelamentos.
Unidades inativas ficam fora. Estado LOCADA/MANUTENCAO sem agenda que explique
a liberação fica indisponível por segurança. Previsão vencida sem retorno ou
conclusão continua bloqueada, mesmo em consultas muito posteriores.

Contratos sem fim continuam usando o sentinela existente `9999-12-31` e não
prometem uma data de liberação. Unidades locadas com devolução futura podem
ser previstas após o fim e a preparação, condicionadas ao retorno efetivo.
Devolução registrada usa `devolvida_em`, inclusive quando antecipada.

Foi preservado o ciclo de manutenção existente: a **agendada** ocupa o dia
previsto na agenda, e a disponibilidade posterior é condicionada à conclusão.
Ao **iniciar**, o sistema bloqueia por prazo indeterminado; não há campo de
previsão de conclusão no cadastro atual. Assim, manutenção em andamento só
libera após concluir. Agendamento vencido sem conclusão também permanece
bloqueado. Uma agenda de manutenção com fim finito é tratada como previsão;
ela nunca é apresentada como disponibilidade garantida.

## Preparação e horizonte

Os padrões ficam em `config/disponibilidade.php` e são carregados mesmo em
instalações que já tenham `config/config.php`:

```php
define('DISPONIBILIDADE_PREPARACAO_HORAS', 24);
define('DISPONIBILIDADE_HORIZONTE_DIAS', 365);
```

Para ajustar localmente, defina essas constantes em `config/config.php`.
Use horas inteiras não negativas e horizonte inteiro positivo. O fim de cada
ocupação recebe o intervalo de preparação; a nova locação também precisa
deixar esse intervalo antes da próxima reserva/manutenção. Não há duplicação
de preparação no mesmo espaço entre ocupações.

Com o padrão, locação até 30/11 inclui esse dia, termina em 01/12 00:00 e
permite outra entrega a partir de **02/12 00:00**. Uma devolução efetiva às
10h libera após 24h; como contratos começam à meia-noite, a primeira data
selecionável é a meia-noite seguinte a essa liberação. Mesmo configurando zero
horas, o dia final continua inclusivo e não permite duas locações nesse dia.

Quando falta quantidade, a busca mantém a duração e verifica as fronteiras de
liberação com início entre a data solicitada e os **365 dias seguintes**, inclusive.
O fim da alternativa pode ultrapassar esse horizonte. Sem resultado, a interface
informa a data limite e não afirma indisponibilidade permanente. A linha do tempo
dos detalhes usa o mesmo horizonte contado a partir de hoje e omite quantidades
consecutivas iguais. Todas as respostas são previsões sujeitas a novas operações.

## Transações

A emissão trava produtos em ordem estável e suas unidades com `FOR UPDATE`,
confere novamente se os produtos estão ativos, calcula as unidades livres e
grava a agenda dentro da transação existente. As leituras da agenda nesse fluxo
também usam `FOR UPDATE`: são leituras atuais mesmo se a transação já abriu um
snapshot no isolamento REPEATABLE READ. Não se usa uma consulta anterior do
navegador como autorização para reservar. Falha desfaz a emissão; um rascunho
preexistente continua rascunho. Estoque e manutenções compartilham a trava do
produto. Conflitos de banco podem exigir nova tentativa, sem reserva parcial.

## Instalação e testes

Não há migração nem alteração de dados existentes. Atualize os arquivos e
recarregue a página; os recursos usam as permissões já cadastradas.
O procedimento SQL didático `ConsultarUnidadesDisponiveis` continua sendo uma
contagem do estado atual e não é usado para previsão ou emissão de contrato.

Testes sem banco:

```powershell
C:/xampp/php/php.exe tests/disponibilidade.php
```

Integração e concorrência usam um **MariaDB descartável na porta 13377** e
nunca leem `config/config.php`. Inicialize-o em diretório separado; por exemplo:

```powershell
New-Item -ItemType Directory -Force tests/.runtime
C:/xampp/mysql/bin/mysql_install_db.exe --datadir=C:/xampp/htdocs/SGCB/tests/.runtime/mysql --port=13377
# Em outro terminal, enquanto os testes executam:
C:/xampp/mysql/bin/mysqld.exe --defaults-file=C:/xampp/htdocs/SGCB/tests/.runtime/mysql/my.ini --bind-address=127.0.0.1 --console
# No terminal do projeto:
C:/xampp/php/php.exe tests/disponibilidade-integracao.php
```

O teste cria um banco aleatório `sgcb_teste_disponibilidade_*`, carrega o esquema
e seeds fictícios, executa duas emissões em processos PHP com snapshots anteriores
à reserva e exige uma única emissão aceita. Remove seu banco ao terminar. A opção
`--keep` preserva o banco descartável para inspeção. Não use a porta de um banco
real nesse teste. `tests/.runtime` está ignorado no versionamento.
