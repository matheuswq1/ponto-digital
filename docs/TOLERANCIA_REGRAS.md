# Regras oficiais — tolerância e saldo diário (referência)

Documento curto para alinhar produto, RH e desenvolvimento. O comportamento exato está no código (`WorkDayService`, `CltToleranceEngine`); isto descreve a **semântica** pretendida.

## Saldo (`extra_minutes` na `work_days`)

| Situação | Valor |
|----------|--------|
| Resultado favorece o colaborador no critério do modo em uso | Positivo |
| Resultado desfavorece | Negativo |
| Sem efeito | Zero |

Transações automáticas do banco de horas usam crédito (`extra`) ou déficit conforme o sinal.

## `raw_diff_minutes` (trabalhado − esperado)

- É **referência de auditoria** (jornada líquida × esperado da escala/depto).
- Nos caminhos **CLT por evento** (`weekday_clt_*` com `clt_primary`), o **saldo final** vem do motor CLT (`extra_minutes_final`), **não** da aplicação direta de `raw_diff_minutes`.
- Pode divergir do saldo final sem indicar erro — apenas convenciones diferentes (relógio por marca vs efeito na jornada).

## Modos CLT (resumo)

| Modo constante | Almoço no motor | Progressive |
|----------------|-----------------|-------------|
| `clt_event_based` | Um evento `lunch_duration`; delta por **relógio** (retorno − prazo sintético) | Não |
| `clt_event_strict` | Saída almoço × gabarito; retorno × saída real + duração | Não |
| `clt_event_progressive_cap` | Dois eventos × gabarito (`lunch_out`, `lunch_return`) | Sim (legado estável) |
| `clt_event_progressive_duration` | Um evento `lunch_duration`; delta **efeito jornada** = configurado − duração real | Sim |

## Convenção “efeito jornada” no almoço (`clt_event_progressive_duration`)

- `delta_almoço = minutos_configurados − duração_real`.
- Intervalo **menor** que o previsto → delta **positivo** neste eixo (mais tempo líquido trabalhado).
- Horários `expected`/`actual` no snapshot do evento mantêm o prazo sintético de retorno vs retorno real para leitura humana; o motor usa `delta_minutes_override` com esta convenção.

## Progressive cap (comum aos modos progressive)

- Até ±5 min por evento → bucket.
- 6–9 min → ±5 no bucket + resto no saldo.
- ≥10 min ou |bucket|≥10 após atualizar → liberta bucket e **encerra a tolerância do dia** (eventos seguintes vão integralmente ao saldo).

## Fonte dos horários previstos

Prioridade: **departamento com gabarito** → senão **escala do colaborador**. Os quatro pontos do cartão (`e1`, `s1`, `e2`, `s2`) derivam de entrada/saída da jornada e minutos de almoço configurados (`Department` / `WorkSchedule`).
