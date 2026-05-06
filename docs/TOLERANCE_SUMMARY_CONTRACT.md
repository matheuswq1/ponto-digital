# Tolerance Summary Contract (v1)

Contrato estável do endpoint `GET /api/v1/reports/tolerance-summary` e da estrutura `meta.reconciliation`.

## Comportamento garantido

- **Ordem de chaves** em `meta.reconciliation` é fixa e definida em `App\Contracts\ToleranceSummaryContract::RECONCILIATION_KEYS` (PHP, testes e fragmento OpenAPI devem permanecer alinhados).
- **`identity_holds`** valida a **partição interna** considerada na agregação (consistência matemática dos buckets vs. dias considerados para tolerância em dias úteis).
- **`reconciliation_complete`** valida **consistência global** do universo iterado no período (linha do tempo completa vs. reconciliação); pode falhar com iterável “suja” mesmo quando `identity_holds` permanece verdadeiro.
- **`rows_without_snapshot`** conta linhas sem snapshot utilizável para o motor de tolerância no período filtrado.
- **Versão de snapshot** (`tolerance_snapshot.version`, `engine`) é **extensível**: motores futuros (v2+) devem preservar o formato esperado para dias úteis ou degradar de forma explícita; testes cobrem compatibilidade quando o formato weekday permanece compatível.

## Artefactos relacionados

- Contrato PHP: `app/Contracts/ToleranceSummaryContract.php`
- Fragmento OpenAPI: `docs/api/tolerance-summary-openapi.yaml`
- Snapshot JSON (Spatie): `tests/Feature/__snapshots__/`

## Evolução (planeamento)

- Snapshot v2 com eventos (`late_entry`, `early_exit`, `extra_exit`, …).
- Modo debug opcional comparando engines v1 vs. v2 no mesmo endpoint.
