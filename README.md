# 📋 Problemas de Gestão em Transporte por Van — Base de Pesquisa do Projeto BEMFA

> Documento de apoio à justificativa do projeto interdisciplinar do grupo **BEMFA**.
> Reúne 10 fontes (artigos, TCCs e sistemas) que documentam problemas reais na gestão de transporte de passageiros — a maioria em transporte escolar/van — e aponta, pra cada uma, o que o nosso sistema pode contemplar pra resolver essa lacuna.

---

## 📑 Sumário

1. [Sobre este documento](#-sobre-este-documento)
2. [Os 10 problemas identificados](#-os-10-problemas-identificados)
3. [Tabela-resumo](#-tabela-resumo)
4. [Conclusão — o gap que o BEMFA pode preencher](#-conclusão--o-gap-que-o-bemfa-pode-preencher)

---

## 📖 Sobre este documento

A gestão de vans (escolares, fretadas ou de fretamento privado) ainda é, na prática, feita **sem um sistema pensado especificamente pra isso**. O que a pesquisa mostra é que esse buraco se repete em vários contextos — de universidades italianas até municípios do interior do Nordeste brasileiro — e que as soluções que já existem resolvem só **um pedaço** do problema (só rastreamento, só pagamento, só roteirização), nunca o pacote completo.

Cada item abaixo segue o mesmo formato:
- **Problemas identificados** → o que a fonte aponta como dor real, documentada.
- **O que o nosso sistema pode contemplar** → como o BEMFA pode usar esse achado pra justificar uma funcionalidade.

---

## 🔍 Os 10 problemas identificados

### 1. Vanpooling and its effect on commuter stress (2018)
🔗 https://www.sciencedirect.com/science/article/pii/S2210539518300397

**Problemas identificados:**
- Passageiro de vanpool sente estresse por **não saber com antecedência** se vai ter vaga ou se a rota vai passar no horário certo.
- A incerteza pesa tanto quanto o atraso em si.

**O que o nosso sistema pode contemplar:**
- Mostrar vaga disponível em tempo real (ou com antecedência), não só no momento do embarque.
- Notificação proativa avisando o passageiro sobre a situação da rota antes que ele precise perguntar.

---

### 2. PoliUniPool: A carpooling system for universities (2011)
🔗 https://www.sciencedirect.com/science/article/pii/S187704281101442X

**Problemas identificados:**
- Pessoas com rotas parecidas não tinham como se encontrar — o "sistema" era boca a boca.
- Falta de automação pra casar quem precisa de transporte com quem tem vaga/rota compatível.

**O que o nosso sistema pode contemplar:**
- Casamento automático entre passageiro e rota/vaga disponível, em vez de depender de coincidência ou grupo de WhatsApp.

---

### 3. SISFRET — Sistema de Controle de Fretamento (ANTT) (~2019)
🔗 https://www.researchgate.net/publication/336720763_O_SISTEMA_DE_CONTROLE_DE_FRETAMENTO_CONTINUO_E_EVENTUAL_OU_TURISTICO_-SISFRET_ANALISE_NO_TURISMO_RODOVIARIO_EMISSIVO_DE_FOZ_DO_IGUACUPR

**Problemas identificados:**
- Sistema oficial do governo (ANTT) pra autorizar viagem de fretamento falha com frequência.
- Sem autorização válida, a empresa não pode nem rodar a viagem — logo, não pode vender vaga nela.

**O que o nosso sistema pode contemplar:**
- Painel de pendências/documentação (autorizações, vistorias, prazos) pra empresa não perder o controle desse tipo de bloqueio operacional.

---

### 4. SIGTE — Sistema para Gestão de Transporte Escolar (2021)
🔗 https://fef.br/upload_arquivos/geral/arq_63fdccbf62fe9.pdf

**Problemas identificados:**
- Prefeituras não sabem, de fato, quantos alunos vão usar cada rota em cada dia.
- Isso trava a escolha do veículo certo (grande demais ou pequeno demais) e aumenta o gasto de combustível.

**O que o nosso sistema pode contemplar:**
- Cadastro de demanda por rota/dia, permitindo prever ocupação antes da viagem sair.
- **Validação real:** testado com usuários, 100% disse que ajuda na gestão e 95% disse que reduz custo — bom argumento pra mostrar que resolver isso tem valor prático.

---

### 5. Joint optimization of bus scheduling and seat allocation for reservation-based travel (2024)
🔗 https://www.sciencedirect.com/science/article/abs/pii/S0968090X24001529

**Problemas identificados:**
- Em sistemas com reserva prévia de assento, horário de pico lota e horário fraco fica com vaga sobrando.
- Ninguém redistribui a capacidade do veículo entre os horários.

**O que o nosso sistema pode contemplar:**
- Lógica de alocação de vaga que considera o horário, não só a rota — mesmo que de forma mais simples que o modelo matemático do artigo.

---

### 6. BusKá — Sistema Integrado para Planejamento, Execução e Monitoramento do Transporte Escolar Público (2026) ⭐
🔗 https://sol.sbc.org.br/index.php/sbbd_estendido/article/view/44124

**Problemas identificados:**
- Planejamento do transporte escolar em muitas prefeituras ainda é manual, **feito por grupos em redes sociais**, sem dado preciso de demanda.
- Isso gera rota ineficiente, custo alto e falta de transparência.
- Superlotação e alocação errada de veículo aparecem como problemas recorrentes.
- Solução parecida que já existe no mercado (**VanBora**, TCC de 2021) foca só em **pagamento/controle financeiro** de van privada — não resolve vaga/ocupação.

**O que o nosso sistema pode contemplar:**
- As mesmas 3 frentes do BusKá (gestor / motorista / passageiro), mas aplicadas ao contexto de **van privada/fretada**, que é onde a solução de mercado (VanBora) ainda não cobre gestão de vaga.

---

### 7. STEQ / "School Bus" — Sistema de Transporte Escolar de Queimadas-PB (2024, TCC UFCG)
🔗 https://dspace.sti.ufcg.edu.br/handle/riufcg/38206

**Problemas identificados:**
- Foco só em rastreamento em tempo real e aviso pros responsáveis.
- **Não ajuda a planejar rota nem mostrar vaga disponível** — só localização.

**O que o nosso sistema pode contemplar:**
- Deixar claro, na documentação do projeto, que rastreamento é *complementar*, não substitui gestão de vaga/ocupação — esse é o diferencial do BEMFA.

---

### 8. NauBus — solução para gestão e monitoramento do transporte de estudantes (IFPB, repositório digital)
🔗 https://repositorio.ifpb.edu.br/handle/177683/5469

**Problemas identificados:**
- Gestão do transporte escolar em muitos municípios é **descentralizada e sem detalhes essenciais**, feita "por meio de ferramentas alternativas, como grupos de mensagens instantâneas" — confirma, de forma independente, o mesmo achado do BusKá.
- Plataformas existentes têm limitações sérias pra atender regiões específicas (no caso, o sertão paraibano).
- Dificuldade de organização de rotas, monitoramento de presença e comunicação com estudantes.

**O que o nosso sistema pode contemplar:**
- Reforça que o problema de "gestão via WhatsApp/grupo" não é exceção, é **padrão** em municípios menores — ótimo argumento de justificativa (dois trabalhos acadêmicos independentes, em regiões diferentes, chegaram no mesmo diagnóstico).

---

### 9. Sistema para gerenciamento de transporte escolar: uma aplicação prática de grafos (IFSP)
🔗 https://repositorio.ifsp.edu.br/items/8ec69dc6-1f08-4a3d-a686-3323f2ac7831/full

**Problemas identificados:**
- Foco específico em **iniciativas particulares de transporte escolar** (van privada), comuns em Campinas-SP e Itu-SP.
- Donos/motoristas de van sofrem **sobrecarga de tarefas manuais**, com desgaste físico e mental por acumular funções sem ferramenta de apoio.
- Falta de otimização de rota — o problema é resolvido com um algoritmo guloso (heurística do vizinho mais próximo).

**O que o nosso sistema pode contemplar:**
- É o artigo mais parecido com o "dono de van autônomo" que o BEMFA provavelmente vai atender — reforça a dor de sobrecarga operacional de quem toca a van sozinho, sem equipe de apoio.
- Sugestão automática de rota como funcionalidade que reduz carga mental do motorista.

---

### 10. Modelagem de software para atender o sistema Público de Transporte Escolar Municipal (CEATE)
🔗 https://ojs.brazilianjournals.com.br/ojs/index.php/BRJD/article/view/40832

**Problemas identificados:**
- Má gestão do transporte escolar afeta diretamente a **frequência dos alunos** e compromete a qualidade da educação.
- Caso real: instituição com ~980 alunos dependentes de transporte por morarem em zona rural/bairros distantes.
- Ainda não existe controle sistematizado de embarque/desembarque nominal por aluno (é isso que o CEATE do artigo tenta resolver).

**O que o nosso sistema pode contemplar:**
- Módulo de controle de embarque/desembarque nominal, permitindo cruzar presença no transporte com frequência escolar — útil se o BEMFA quiser atacar também o recorte de transporte escolar público, além do privado.

---

## 📊 Tabela-resumo

| # | Fonte | Problema central | O que puxa pro projeto |
|---|---|---|---|
| 1 | Vanpooling stress (2018) | Incerteza sobre vaga gera estresse | Visibilidade de vaga em tempo real |
| 2 | PoliUniPool (2011) | Pareamento rota/vaga manual | Casamento automático rota × vaga |
| 3 | SISFRET (2019) | Falha em autorização trava venda de vaga | Painel de pendências regulatórias |
| 4 | SIGTE (2021) | Sem dado de demanda por rota | Cadastro de demanda + validação real (95% aprovação) |
| 5 | Seat allocation (2024) | Vaga mal distribuída por horário | Alocação de vaga por horário |
| 6 | BusKá (2026) | Gestão via grupo de rede social | Sistema integrado gestor/motorista/passageiro |
| 7 | STEQ (2024) | Só rastreamento, sem gestão de vaga | Diferencial: foco em vaga, não só localização |
| 8 | NauBus (IFPB) | Gestão descentralizada via WhatsApp | Confirma padrão do problema em 2ª região |
| 9 | Grafos IFSP | Sobrecarga do motorista autônomo de van | Rota automática pra reduzir carga manual |
| 10 | CEATE (BRJD) | Falta de controle de embarque nominal | Módulo de embarque/desembarque por aluno |

---

## ✅ Conclusão — o gap que o BEMFA pode preencher

Juntando os 10 pontos:

1. **O problema é real e se repete** em contextos bem diferentes (EUA, Itália, e pelo menos 3 regiões diferentes do Brasil — Nordeste, interior de SP e Paraíba).
2. **Quando não tem sistema dedicado, vira gambiarra** — grupo de WhatsApp, rede social, planilha, boca a boca. Isso é citado de forma independente por pelo menos dois trabalhos (BusKá e NauBus), o que fortalece bastante o argumento.
3. **As soluções existentes são fragmentadas**: umas resolvem só rastreamento (STEQ), outras só pagamento (VanBora), outras só roteirização (grafos IFSP), outras são só modelo matemático sem sistema real (seat allocation 2024). **Nenhuma junta gestão de vaga + rota + comunicação num só lugar, pensada pra van privada/fretada.**
4. **Quando alguém testa uma solução assim com usuário real, funciona** — SIGTE e BusKá têm validação prática, não só teórica.
