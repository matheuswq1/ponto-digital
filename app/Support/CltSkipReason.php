<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Contrato estável de códigos de skip para modo CLT por batida.
 *
 * Não remover valores existentes (compatível com auditoria); apenas acrescentar novos.
 * Para OpenAPI / documentação externa, use {@see self::values()}.
 */
final class CltSkipReason
{
    public const MISSING_GABARITO = 'missing_gabarito';

    /** Quantidade de batidas diferente do gabarito esperado. */
    public const WRONG_RECORD_COUNT = 'wrong_record_count';

    /** Tipos ou ordem de entrada/saída não coincidem com o esperado. */
    public const TYPE_SEQUENCE_MISMATCH = 'type_sequence_mismatch';

    /** Reservado: uso quando produto distinguir explicitamente “dia incompleto”. */
    public const INCOMPLETE_DAY = 'incomplete_day';

    public const UNKNOWN = 'unknown';

    /** Fallback jurídico / cadastro — gabarito CLT não disponível. */
    public const CATEGORY_RULE = 'rule';

    /** Dados do dia, pareamento ou sequência inconsistentes com o esperado. */
    public const CATEGORY_STRUCTURAL = 'structural';

    /**
     * Lista oficial para observabilidade, filtros de BI e geração de schema OpenAPI.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::MISSING_GABARITO,
            self::WRONG_RECORD_COUNT,
            self::TYPE_SEQUENCE_MISMATCH,
            self::INCOMPLETE_DAY,
            self::UNKNOWN,
        ];
    }

    /**
     * Base para documentação / OpenAPI / geradores de schema (exportável).
     *
     * @return array{reasons: list<string>, categories: list<string>, confidence: list<string>}
     */
    public static function schema(): array
    {
        return [
            'reasons' => self::values(),
            'categories' => [self::CATEGORY_STRUCTURAL, self::CATEGORY_RULE],
            'confidence' => ['high', 'medium', 'low'],
        ];
    }

    public static function normalize(string $reason): string
    {
        $r = strtolower(trim($reason));

        return in_array($r, self::values(), true) ? $r : self::UNKNOWN;
    }

    /**
     * Distingue problema de cadastro/regra de jornada vs problema estrutural dos registros do dia.
     */
    public static function category(string $reason): string
    {
        return match (self::normalize($reason)) {
            self::MISSING_GABARITO => self::CATEGORY_RULE,
            default => self::CATEGORY_STRUCTURAL,
        };
    }

    public static function labelPt(string $reason): string
    {
        return match (self::normalize($reason)) {
            self::MISSING_GABARITO => 'Sem gabarito de jornada com horários previstos por marcação.',
            self::WRONG_RECORD_COUNT => 'Quantidade de batidas diferente do gabarito esperado.',
            self::TYPE_SEQUENCE_MISMATCH => 'Ordem ou tipo das batidas não coincide com o esperado (entrada/saída).',
            self::INCOMPLETE_DAY => 'Dia de registro incompleto ou número insuficiente de marcações para aplicar CLT.',
            default => 'Motivo não especificado — rever pareamento ou cadastro da jornada.',
        };
    }
}
