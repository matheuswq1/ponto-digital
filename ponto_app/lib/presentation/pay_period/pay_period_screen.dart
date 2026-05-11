import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../core/theme/app_theme.dart';
import '../../data/datasources/pay_period_datasource.dart';
import '../../data/models/pay_period_models.dart';
import '../../data/models/work_day_model.dart';
import 'pay_period_provider.dart';

class PayPeriodsScreen extends ConsumerWidget {
  const PayPeriodsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(myPayPeriodsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Espelho de ponto'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.error_outline, size: 48, color: AppColors.error),
                const SizedBox(height: 12),
                Text('$e', textAlign: TextAlign.center),
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: () => ref.invalidate(myPayPeriodsProvider),
                  child: const Text('Tentar novamente'),
                ),
              ],
            ),
          ),
        ),
        data: (rows) {
          if (rows.isEmpty) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(32),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.event_note_rounded,
                        size: 72, color: Colors.grey.shade400),
                    const SizedBox(height: 16),
                    Text(
                      'Sem períodos fechados para conferência.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.grey.shade700,
                        fontSize: 15,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Quando o RH fechar um período (ex.: dia 25 a 25), ele aparecerá aqui para você aceitar ou contestar.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.grey.shade600,
                        fontSize: 13,
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(myPayPeriodsProvider),
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: rows.length,
              itemBuilder: (context, i) {
                final row = rows[i];
                return _PayPeriodCard(
                  row: row,
                  onTap: () => context.pushNamed(
                    'pay-period-detail',
                    pathParameters: {'closureId': '${row.closure.id}'},
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _PayPeriodCard extends StatelessWidget {
  final MyPayPeriodRow row;
  final VoidCallback onTap;

  const _PayPeriodCard({required this.row, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final fmt = DateFormat('dd/MM/yyyy');
    DateTime? parse(String s) {
      try {
        return DateFormat('yyyy-MM-dd').parse(s);
      } catch (_) {
        return null;
      }
    }

    final start = parse(row.closure.periodStart);
    final end = parse(row.closure.periodEnd);
    final rangeLabel = start != null && end != null
        ? '${fmt.format(start)} — ${fmt.format(end)}'
        : '${row.closure.periodStart} — ${row.closure.periodEnd}';

    final (color, label) = switch (row.status) {
      'aprovado' => (AppColors.success, 'Aceito'),
      'rejeitado' => (AppColors.error, 'Contestado'),
      _ => (AppColors.warning, 'Pendente'),
    };

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: Colors.grey.shade300),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      rangeLabel,
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 15,
                      ),
                    ),
                  ),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      label,
                      style: TextStyle(
                        color: color,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
              if (row.closure.notes != null &&
                  row.closure.notes!.trim().isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  row.closure.notes!,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                ),
              ],
              const SizedBox(height: 8),
              Row(
                children: [
                  Icon(Icons.touch_app_outlined,
                      size: 16, color: Colors.grey.shade600),
                  const SizedBox(width: 6),
                  Text(
                    'Toque para ver detalhes e responder',
                    style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class PayPeriodDetailScreen extends ConsumerStatefulWidget {
  final int closureId;

  const PayPeriodDetailScreen({super.key, required this.closureId});

  @override
  ConsumerState<PayPeriodDetailScreen> createState() =>
      _PayPeriodDetailScreenState();
}

class _PayPeriodDetailScreenState extends ConsumerState<PayPeriodDetailScreen> {
  bool _submitting = false;

  Future<void> _submit(bool approve) async {
    if (_submitting) return;
    String? notes;
    if (!approve) {
      notes = await showDialog<String>(
        context: context,
        builder: (ctx) {
          final controller = TextEditingController();
          return AlertDialog(
            title: const Text('Motivo da contestação'),
            content: TextField(
              controller: controller,
              maxLines: 4,
              decoration: const InputDecoration(
                hintText:
                    'Explique o que está incorreto (opcional mas recomendado).',
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx),
                child: const Text('Cancelar'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(ctx, controller.text),
                child: const Text('Enviar contestação'),
              ),
            ],
          );
        },
      );
      if (!mounted || notes == null) return;
    }

    setState(() => _submitting = true);
    try {
      await ref.read(payPeriodDatasourceProvider).respond(
            closureId: widget.closureId,
            approve: approve,
            notes: notes,
          );
      ref.invalidate(payPeriodDetailProvider(widget.closureId));
      ref.invalidate(myPayPeriodsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              approve
                  ? 'Espelho aceito com sucesso.'
                  : 'Contestação registada. O RH será informado.',
            ),
            backgroundColor: approve ? AppColors.success : AppColors.warning,
          ),
        );
        context.pop();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('$e'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(payPeriodDetailProvider(widget.closureId));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Conferir período'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text('$e', textAlign: TextAlign.center),
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: () =>
                      ref.invalidate(payPeriodDetailProvider(widget.closureId)),
                  child: const Text('Tentar novamente'),
                ),
              ],
            ),
          ),
        ),
        data: (detail) => _DetailBody(
          detail: detail,
          submitting: _submitting,
          onApprove: () => _submit(true),
          onReject: () => _submit(false),
        ),
      ),
    );
  }
}

class _DetailBody extends StatelessWidget {
  final PayPeriodDetailData detail;
  final bool submitting;
  final VoidCallback onApprove;
  final VoidCallback onReject;

  const _DetailBody({
    required this.detail,
    required this.submitting,
    required this.onApprove,
    required this.onReject,
  });

  @override
  Widget build(BuildContext context) {
    final fmt = DateFormat('dd/MM/yyyy');
    DateTime? parse(String s) {
      try {
        return DateFormat('yyyy-MM-dd').parse(s);
      } catch (_) {
        return null;
      }
    }

    final start = parse(detail.closure.periodStart);
    final end = parse(detail.closure.periodEnd);
    final rangeLabel = start != null && end != null
        ? '${fmt.format(start)} — ${fmt.format(end)}'
        : '${detail.closure.periodStart} — ${detail.closure.periodEnd}';

    final pending = detail.acknowledgement.isPending;

    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(
                rangeLabel,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              if (detail.closure.notes != null &&
                  detail.closure.notes!.trim().isNotEmpty) ...[
                const SizedBox(height: 8),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.blue.shade50,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.blue.shade100),
                  ),
                  child: Text(
                    detail.closure.notes!,
                    style: TextStyle(fontSize: 13, color: Colors.blue.shade900),
                  ),
                ),
              ],
              const SizedBox(height: 16),
              Text(
                'Revise os dias e totais abaixo. Ao aceitar, você confirma que o espelho está correto para este período.',
                style: TextStyle(fontSize: 13, color: Colors.grey.shade700),
              ),
              const SizedBox(height: 16),
              _SummaryStrip(summary: detail.summary),
              const SizedBox(height: 20),
              Text(
                'Detalhamento (${detail.workDays.length} dias)',
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 15,
                ),
              ),
              const SizedBox(height: 10),
              ...detail.workDays.map((d) => _WorkDayRow(day: d)),
              if (detail.workDays.isEmpty)
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(
                    'Nenhum dia de trabalho registado neste intervalo.',
                    style: TextStyle(color: Colors.grey.shade600),
                  ),
                ),
              const SizedBox(height: 100),
            ],
          ),
        ),
        if (pending)
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: submitting ? null : onReject,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.error,
                        side: const BorderSide(color: AppColors.error),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                      child: const Text('Contestar'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: FilledButton(
                      onPressed: submitting ? null : onApprove,
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.success,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                      child: submitting
                          ? const SizedBox(
                              height: 22,
                              width: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text('Aceitar espelho'),
                    ),
                  ),
                ],
              ),
            ),
          )
        else
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: detail.acknowledgement.status == 'aprovado'
                      ? AppColors.success.withValues(alpha: 0.1)
                      : AppColors.error.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  detail.acknowledgement.status == 'aprovado'
                      ? 'Você já aceitou este espelho.'
                      : 'Você contestou este espelho.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontWeight: FontWeight.w600,
                    color: detail.acknowledgement.status == 'aprovado'
                        ? AppColors.success
                        : AppColors.error,
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _SummaryStrip extends StatelessWidget {
  final PayPeriodSummary summary;

  const _SummaryStrip({required this.summary});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _Mini(
            label: 'Trabalhado',
            value: summary.workedHours,
            color: AppColors.info,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _Mini(
            label: 'Esperado',
            value: summary.expectedHours,
            color: Colors.grey,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _Mini(
            label: 'Saldo',
            value: summary.balanceHours,
            color: summary.balanceMinutes >= 0
                ? AppColors.success
                : AppColors.error,
          ),
        ),
      ],
    );
  }
}

class _Mini extends StatelessWidget {
  final String label;
  final String value;
  final Color color;

  const _Mini({
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Column(
        children: [
          Text(
            label,
            style: TextStyle(fontSize: 11, color: Colors.grey.shade700),
          ),
          const SizedBox(height: 4),
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              value,
              style: TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 14,
                color: color,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _WorkDayRow extends StatelessWidget {
  final WorkDayModel day;

  const _WorkDayRow({required this.day});

  static String _aggregatedTimes(WorkDayModel day) {
    final parts = <String>[
      if (day.entryTime != null && day.entryTime!.trim().isNotEmpty)
        day.entryTime!.trim(),
      if (day.lunchStart != null && day.lunchStart!.trim().isNotEmpty)
        day.lunchStart!.trim(),
      if (day.lunchEnd != null && day.lunchEnd!.trim().isNotEmpty)
        day.lunchEnd!.trim(),
      if (day.exitTime != null && day.exitTime!.trim().isNotEmpty)
        day.exitTime!.trim(),
    ];
    return parts.isEmpty ? '—' : parts.join(' · ');
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.grey.shade300),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 44,
            child: Text(
              day.dateFormatted.split('/').first,
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              textAlign: TextAlign.center,
            ),
          ),
          Expanded(
            child: day.timeRecords.isNotEmpty
                ? Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: day.timeRecords
                        .map(
                          (r) => Padding(
                            padding: const EdgeInsets.only(bottom: 4),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                SizedBox(
                                  width: 42,
                                  child: Text(
                                    r.time,
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w700,
                                      fontSize: 12,
                                    ),
                                  ),
                                ),
                                Expanded(
                                  child: Text(
                                    r.typeLabel,
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: Colors.grey.shade800,
                                      height: 1.25,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        )
                        .toList(),
                  )
                : Text(
                    _aggregatedTimes(day),
                    style: TextStyle(fontSize: 12, color: Colors.grey.shade800),
                  ),
          ),
          Text(
            day.extraHours,
            style: TextStyle(
              fontWeight: FontWeight.w600,
              fontSize: 13,
              color: day.isPositive
                  ? AppColors.success
                  : day.isNegative
                      ? AppColors.error
                      : Colors.grey,
            ),
          ),
        ],
      ),
    );
  }
}
