import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'balance_provider.dart';
import '../../data/models/work_day_model.dart';
import '../../core/theme/app_theme.dart';

class BalanceScreen extends ConsumerWidget {
  const BalanceScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final selectedMonth = ref.watch(selectedMonthProvider);
    final summaryAsync = ref.watch(monthSummaryProvider(selectedMonth));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Banco de Horas'),
      ),
      body: Column(
        children: [
          // Seletor de mês
          _MonthSelector(selected: selectedMonth),

          // Conteúdo
          Expanded(
            child: summaryAsync.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.error_outline, size: 48, color: AppColors.error),
                    const SizedBox(height: 12),
                    Text(e.toString()),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      onPressed: () => ref.invalidate(monthSummaryProvider(selectedMonth)),
                      child: const Text('Tentar novamente'),
                    ),
                  ],
                ),
              ),
              data: (summary) => _buildContent(summary),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildContent(MonthSummaryModel summary) {
    return RefreshIndicator(
      onRefresh: () async {},
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _SummaryCards(summary: summary),
          const SizedBox(height: 20),
          const Text(
            'Detalhamento diário',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 12),
          if (summary.workDays.isEmpty)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(32),
                child: Text('Nenhum registro neste mês.',
                    style: TextStyle(color: AppColors.textSecondary)),
              ),
            )
          else
            ...summary.workDays.map((day) => _WorkDayTile(day: day)),
        ],
      ),
    );
  }
}

class _MonthSelector extends ConsumerWidget {
  final DateTime selected;
  const _MonthSelector({required this.selected});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Container(
      color: AppColors.primary,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          IconButton(
            icon: const Icon(Icons.chevron_left, color: Colors.white),
            onPressed: () => ref.read(selectedMonthProvider.notifier).state =
                DateTime(selected.year, selected.month - 1),
          ),
          Text(
            DateFormat("MMMM 'de' yyyy", 'pt_BR').format(selected),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.bold,
            ),
          ),
          IconButton(
            icon: const Icon(Icons.chevron_right, color: Colors.white),
            onPressed: selected.isBefore(DateTime.now())
                ? () => ref.read(selectedMonthProvider.notifier).state =
                    DateTime(selected.year, selected.month + 1)
                : null,
          ),
        ],
      ),
    );
  }
}

class _SummaryCards extends StatelessWidget {
  final MonthSummaryModel summary;
  const _SummaryCards({required this.summary});

  @override
  Widget build(BuildContext context) {
    final isPositive = summary.totalExtraMinutes >= 0;

    return Column(
      children: [
        // Saldo de horas — card principal
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: isPositive
                  ? [AppColors.success, const Color(0xFF059669)]
                  : [AppColors.error, const Color(0xFFB91C1C)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(18),
            boxShadow: [
              BoxShadow(
                color: (isPositive ? AppColors.success : AppColors.error).withValues(alpha: 0.3),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Column(
            children: [
              Text(
                'Saldo do mês',
                style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 14),
              ),
              const SizedBox(height: 8),
              Text(
                summary.balanceHours,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 42,
                  fontWeight: FontWeight.bold,
                  letterSpacing: 2,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                isPositive ? 'Horas extras' : 'Horas em débito',
                style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 12),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),

        // Cards secundários
        Row(
          children: [
            Expanded(
              child: _MiniCard(
                label: 'Trabalhado',
                value: summary.workedHours,
                icon: Icons.timer_outlined,
                color: AppColors.info,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _MiniCard(
                label: 'Esperado',
                value: summary.expectedHours,
                icon: Icons.schedule,
                color: AppColors.textSecondary,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _MiniCard(
                label: 'Faltas',
                value: summary.totalAbsences.toString(),
                icon: Icons.person_off_outlined,
                color: summary.totalAbsences > 0 ? AppColors.error : AppColors.textSecondary,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _MiniCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;

  const _MiniCard({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.divider),
      ),
      child: Column(
        children: [
          Icon(icon, color: color, size: 22),
          const SizedBox(height: 6),
          Text(
            value,
            style: TextStyle(
              fontWeight: FontWeight.bold,
              fontSize: 16,
              color: color,
            ),
          ),
          const SizedBox(height: 2),
          Text(label,
              style: const TextStyle(fontSize: 11, color: AppColors.textSecondary)),
        ],
      ),
    );
  }
}

class _WorkDayTile extends StatelessWidget {
  final WorkDayModel day;
  const _WorkDayTile({required this.day});

  @override
  Widget build(BuildContext context) {
    final balanceColor = day.isPositive
        ? AppColors.success
        : day.isNegative
            ? AppColors.error
            : AppColors.textSecondary;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.divider),
      ),
      child: Row(
        children: [
          // Data
          SizedBox(
            width: 46,
            child: Column(
              children: [
                Text(
                  day.dateFormatted.split('/')[0],
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    color: AppColors.textPrimary,
                  ),
                ),
                Text(
                  day.weekDay.length > 3
                      ? day.weekDay.substring(0, 3).toLowerCase()
                      : day.weekDay.toLowerCase(),
                  style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
                ),
              ],
            ),
          ),
          const VerticalDivider(width: 24, color: AppColors.divider),

          // Horários
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    _TimeChip(time: day.entryTime, label: 'E'),
                    const SizedBox(width: 4),
                    _TimeChip(time: day.lunchStart, label: 'SA'),
                    const SizedBox(width: 4),
                    _TimeChip(time: day.lunchEnd, label: 'VA'),
                    const SizedBox(width: 4),
                    _TimeChip(time: day.exitTime, label: 'S'),
                  ],
                ),
                if (day.totalMinutes > 0) ...[
                  const SizedBox(height: 4),
                  Text(
                    '${day.totalHours}h trabalhadas',
                    style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
                  ),
                ],
              ],
            ),
          ),

          // Saldo
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                day.extraHours,
                style: TextStyle(
                  color: balanceColor,
                  fontWeight: FontWeight.bold,
                  fontSize: 15,
                ),
              ),
              if (day.status != 'normal')
                _StatusBadge(status: day.status),
            ],
          ),
        ],
      ),
    );
  }
}

class _TimeChip extends StatelessWidget {
  final String? time;
  final String label;
  const _TimeChip({this.time, required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 3),
      decoration: BoxDecoration(
        color: time != null
            ? AppColors.primary.withValues(alpha: 0.08)
            : AppColors.surfaceVariant,
        borderRadius: BorderRadius.circular(5),
      ),
      child: Column(
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 8,
              color: time != null ? AppColors.primary : AppColors.textHint,
            ),
          ),
          Text(
            time ?? '--:--',
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.bold,
              color: time != null ? AppColors.textPrimary : AppColors.textHint,
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String status;
  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      'falta' => ('Falta', AppColors.error),
      'feriado' => ('Feriado', AppColors.info),
      'folga' => ('Folga', AppColors.success),
      'afastamento' => ('Afastado', AppColors.warning),
      _ => (status, AppColors.textSecondary),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(label, style: TextStyle(color: color, fontSize: 9)),
    );
  }
}

