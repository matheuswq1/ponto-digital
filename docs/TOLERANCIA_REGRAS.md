# Regras oficiais — tolerância e saldo diário (referência)

Documento curto para alinear produto, RH e desenvolvimento. O comportamento exato está no código (`WorkDayService`, `CltToleranceEngine`); isto descreve a **semântica** pretendida.

## Saldo (`extra_minutes` na `work_days`)

| Situação | Valor |
|----------|--------|
| Resultado favorece o colaborador no critério do modo em uso | Positivo |
| Resultado desfavorece | Negativo |
| Sem efeito | Zero |

Transações automáticas do banco de horas usam crédito (`extra`) ou déficit conforme o sinal.

## `raw_diff_minutes` (trabalhado − esperado)

- É **referência de auditoria** (jornada líquida × esperado da escala/depto).
- Nos caminhos **CLT por batida** (`weekday_clt_event_progressive_duration` com `clt_primary`), o **saldo final** vem do motor CLT (`extra_minutes_final`), **não** da aplicação direta de `raw_diff_minutes`.
- Pode divergir do saldo final sem indicar erro — apenas convenções diferentes (relógio por marca vs efeito na jornada).

## Modo único CLT (`clt_event_progressive_duration`)

- Motor **bucket progressivo** (até ±5 min no bucket; 6–9 min reparte; ≥10 min ou |bucket|≥10 libera e encerra a tolerância do dia; eventos seguintes vão integralmente ao saldo).
- **Entrada** e **saída final** comparadas ao **gabarito**.
- **Almoço**: um único evento `lunch_duration`. **Não** se compara a hora da **saída para almoço** ao gabarito; apenas **duração real do intervalo** vs **minutos configurados**, com convenção **efeito jornada**:  
  `delta = minutos_configurados − duração_real` (`delta_minutes_override`).
- Intervalo **menor** que o previsto → delta **positivo** neste eixo (mais tempo líquido trabalhado).

Snapshots mais antigos podem ainda exibir `calculation_path` legados (`weekday_clt_event_based`, `weekday_clt_event_strict`, `weekday_clt_event_progressive_cap`) até novo recálculo.

## Progressive bucket (resumo)

- Até ±5 min por evento → bucket.
- 6–9 min → ±5 no bucket + resto no saldo.
- ≥10 min ou |bucket|≥10 após atualizar → liberta bucket e **encerra a tolerância do dia** (eventos seguintes vão integralmente ao saldo).

## Fonte dos horários previstos

Prioridade: **departamento com gabarito** → senão **escala do colaborador**. Os quatro pontos do cartão (`e1`, `s1`, `e2`, `s2`) derivam de entrada/saída da jornada e minutos de almoço configurados (`Department` / `WorkSchedule`). Para o intervalo, só os tempos de **entrada/saída da jornada** fixam o gabarito de marcações auxiliares; o critério de saldo no almoço é a **duração**, não o relógio de saída para almoço.
