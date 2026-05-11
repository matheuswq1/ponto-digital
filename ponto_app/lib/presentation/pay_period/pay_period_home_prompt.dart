import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../core/theme/app_theme.dart';
import '../../data/models/pay_period_models.dart';

/// Lembrete em formato popup quando há espelho(s) de ponto por aceitar.
Future<void> showPayPeriodPendingReminderDialog(
  BuildContext context,
  List<MyPayPeriodRow> pending,
) async {
  if (pending.isEmpty || !context.mounted) return;

  final fmt = DateFormat('dd/MM/yyyy', 'pt_BR');
  final lines = pending
      .map((r) {
        try {
          final a = DateTime.parse(r.closure.periodStart);
          final b = DateTime.parse(r.closure.periodEnd);
          return '• ${fmt.format(a)} — ${fmt.format(b)}';
        } catch (_) {
          return '• Período #${r.closure.id}';
        }
      })
      .take(5)
      .join('\n');

  await showDialog<void>(
    context: context,
    barrierDismissible: true,
    builder: (ctx) => AlertDialog(
      icon: Icon(
        Icons.fact_check_rounded,
        color: AppColors.primary,
        size: 40,
      ),
      title: Text(
        pending.length == 1
            ? 'Espelho aguarda a sua confirmação'
            : '${pending.length} espelhos aguardam confirmação',
      ),
      content: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'O seu período de ponto foi fechado pelo RH. Revise o espelho e aceite ou conteste.',
              style: TextStyle(fontSize: 14),
            ),
            const SizedBox(height: 12),
            Text(
              lines,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                height: 1.45,
              ),
            ),
            if (pending.length > 5)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  '… e mais ${pending.length - 5}',
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.grey.shade600,
                  ),
                ),
              ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(ctx).pop(),
          child: const Text('Depois'),
        ),
        FilledButton(
          onPressed: () {
            final router = GoRouter.of(ctx);
            Navigator.of(ctx).pop();
            router.pushNamed('pay-periods');
          },
          child: const Text('Ver espelhos'),
        ),
      ],
    ),
  );
}
