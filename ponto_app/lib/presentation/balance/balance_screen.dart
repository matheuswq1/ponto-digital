import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'balance_provider.dart';
import 'hour_bank_provider.dart';
import 'request_leave_screen.dart';
import '../../data/models/work_day_model.dart';
import '../../data/models/hour_bank_request_model.dart';
import '../../core/theme/app_theme.dart';

class BalanceScreen extends ConsumerStatefulWidget {
  const BalanceScreen({super.key});

  @override
  ConsumerState<BalanceScreen> createState() => _BalanceScreenState();
}

class _BalanceScreenState extends ConsumerState<BalanceScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final balanceAsync = ref.watch(hourBankBalanceProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Banco de Horas'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        elevation: 0,
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: Colors.white,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white60,
          labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
          tabs: const [
            Tab(text: 'Saldo'),
            Tab(text: 'Movimentações'),
            Tab(text: 'Solicitações'),
          ],
        ),
        actions: [
          balanceAsync.maybeWhen(
            data: (balance) => balance.isPositive
                ? IconButton(
                    icon: const Icon(Icons.add_circle_outline),
                    tooltip: 'Solicitar folga',
                    onPressed: () => _openRequestLeave(balance),
                  )
                : const SizedBox.shrink(),
            orElse: () => const SizedBox.shrink(),
          ),
        ],
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _SaldoTab(onRequestLeave: _openRequestLeave),
          _MovimentacoesTab(),
          _SolicitacoesTab(),
        ],
      ),
    );
  }

  void _openRequestLeave(HourBankBalanceModel balance) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => RequestLeaveScreen(balance: balance),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────
// Aba 1: Saldo total + detalhamento mensal
// ─────────────────────────────────────────────────────────────────
class _SaldoTab extends ConsumerWidget {
  final void Function(HourBankBalanceModel) onRequestLeave;
  const _SaldoTab({required this.onRequestLeave});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final balanceAsync = ref.watch(hourBankBalanceProvider);
    final selectedMonth = ref.watch(selectedMonthProvider);
    final summaryAsync = ref.watch(monthSummaryProvider(selectedMonth));

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(hourBankBalanceProvider);
        ref.invalidate(monthSummaryProvider(selectedMonth));
      },
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Card saldo acumulado total
          balanceAsync.when(
            loading: () => const _LoadingCard(),
            error: (e, _) => _ErrorCard(
              error: e.toString(),
              onRetry: () => ref.invalidate(hourBankBalanceProvider),
            ),
            data: (balance) => _TotalBalanceCard(
              balance: balance,
              onRequestLeave: () => onRequestLeave(balance),
            ),
          ),

          const SizedBox(height: 20),

          // Seletor de mês
          _MonthSelector(selected: selectedMonth),
          const SizedBox(height: 12),

          // Cards do mês
          summaryAsync.when(
            loading: () => const _LoadingCard(),
            error: (e, _) => _ErrorCard(
              error: e.toString(),
              onRetry: () => ref.invalidate(monthSummaryProvider(selectedMonth)),
            ),
            data: (summary) => Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _SummaryCards(summary: summary),
                const SizedBox(height: 20),
                const Text(
                  'Detalhamento diário',
                  style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary),
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
          ),
          const SizedBox(height: 40),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────
// Aba 2: Histórico de movimentações
// ─────────────────────────────────────────────────────────────────
class _MovimentacoesTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final txAsync = ref.watch(hourBankTransactionsProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(hourBankTransactionsProvider),
      child: txAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => _ErrorCard(
            error: e.toString(),
            onRetry: () => ref.invalidate(hourBankTransactionsProvider)),
        data: (txList) {
          if (txList.isEmpty) {
            return const Center(
              child: Text('Nenhuma movimentação registrada.',
                  style: TextStyle(color: AppColors.textSecondary)),
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: txList.length,
            separatorBuilder: (_, __) => const SizedBox(height: 8),
            itemBuilder: (_, i) => _TransactionTile(tx: txList[i]),
          );
        },
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────
// Aba 3: Solicitações de folga
// ─────────────────────────────────────────────────────────────────
class _SolicitacoesTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final reqAsync = ref.watch(hourBankRequestsProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(hourBankRequestsProvider),
      child: reqAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => _ErrorCard(
            error: e.toString(),
            onRetry: () => ref.invalidate(hourBankRequestsProvider)),
        data: (requests) {
          if (requests.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.event_available_outlined,
                      size: 64, color: AppColors.textHint),
                  const SizedBox(height: 16),
                  const Text('Nenhuma solicitação ainda.',
                      style: TextStyle(color: AppColors.textSecondary)),
                ],
              ),
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: requests.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (_, i) => _RequestTile(request: requests[i]),
          );
        },
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────
// Card saldo total acumulado
// ─────────────────────────────────────────────────────────────────
class _TotalBalanceCard extends StatelessWidget {
  final HourBankBalanceModel balance;
  final VoidCallback onRequestLeave;
  const _TotalBalanceCard(
      {required this.balance, required this.onRequestLeave});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: balance.isPositive
              ? [const Color(0xFF059669), const Color(0xFF10B981)]
              : [const Color(0xFFDC2626), const Color(0xFFEF4444)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: (balance.isPositive ? AppColors.success : AppColors.error)
                .withValues(alpha: 0.3),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        children: [
          Text('Saldo acumulado total',
              style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.85), fontSize: 13)),
          const SizedBox(height: 8),
          Text(
            balance.formatted,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 44,
              fontWeight: FontWeight.bold,
              letterSpacing: 2,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              _BalancePill(
                  label: 'Créditos',
                  value: _fmt(balance.creditMinutes, '+'),
                  color: Colors.white),
              const SizedBox(width: 16),
              _BalancePill(
                  label: 'Débitos',
                  value: _fmt(balance.debitMinutes, ''),
                  color: Colors.white70),
            ],
          ),
          if (balance.isPositive) ...[
            const SizedBox(height: 16),
            GestureDetector(
              onTap: onRequestLeave,
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 24, vertical: 10),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(30),
                  border: Border.all(color: Colors.white38),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.event_available_outlined,
                        color: Colors.white, size: 16),
                    SizedBox(width: 8),
                    Text('Solicitar folga compensatória',
                        style: TextStyle(
                            color: Colors.white,
                            fontSize: 13,
                            fontWeight: FontWeight.w600)),
                  ],
                ),
              ),
            ),
          ],
          if (balance.pendingRequests > 0) ...[
            const SizedBox(height: 8),
            Text(
              '${balance.pendingRequests} solicitação(ões) pendente(s)',
              style: const TextStyle(color: Colors.white70, fontSize: 11),
            ),
          ],
        ],
      ),
    );
  }

  String _fmt(int minutes, String prefix) {
    final abs = minutes.abs();
    return '$prefix${(abs ~/ 60).toString().padLeft(2, '0')}:${(abs % 60).toString().padLeft(2, '0')}';
  }
}

class _BalancePill extends StatelessWidget {
  final String label;
  final String value;
  final Color color;
  const _BalancePill(
      {required this.label, required this.value, required this.color});

  @override
  Widget build(BuildContext context) => Column(
        children: [
          Text(value,
              style: TextStyle(
                  color: color,
                  fontWeight: FontWeight.bold,
                  fontSize: 16)),
          Text(label, style: TextStyle(color: color, fontSize: 11)),
        ],
      );
}

// ─────────────────────────────────────────────────────────────────
// Tile de transação
// ─────────────────────────────────────────────────────────────────
class _TransactionTile extends StatelessWidget {
  final HourBankTransactionModel tx;
  const _TransactionTile({required this.tx});

  @override
  Widget build(BuildContext context) {
    final color = tx.isCredit ? AppColors.success : AppColors.error;
    final sign = tx.isCredit ? '+' : '';
    final abs = tx.minutes.abs();
    final fmt =
        '$sign${(abs ~/ 60).toString().padLeft(2, '0')}:${(abs % 60).toString().padLeft(2, '0')}';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.divider),
      ),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              tx.isCredit ? Icons.arrow_upward : Icons.arrow_downward,
              color: color,
              size: 18,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  tx.description ?? tx.typeLabel,
                  style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w500,
                      color: AppColors.textPrimary),
                ),
                Text(
                  '${tx.dateFormatted} · ${tx.typeLabel}',
                  style: const TextStyle(
                      fontSize: 11, color: AppColors.textSecondary),
                ),
              ],
            ),
          ),
          Text(fmt,
              style: TextStyle(
                  color: color,
                  fontWeight: FontWeight.bold,
                  fontSize: 15)),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────
// Tile de solicitação
// ─────────────────────────────────────────────────────────────────
class _RequestTile extends StatelessWidget {
  final HourBankRequestModel request;
  const _RequestTile({required this.request});

  @override
  Widget build(BuildContext context) {
    final (color, bgColor, icon) = switch (request.status) {
      'aprovado' => (AppColors.success, const Color(0xFFECFDF5), Icons.check_circle_outline),
      'rejeitado' => (AppColors.error, const Color(0xFFFFF1F2), Icons.cancel_outlined),
      _ => (AppColors.warning, const Color(0xFFFFFBEB), Icons.access_time),
    };

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.divider),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Folga em ${request.dateFormatted}',
                      style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: AppColors.textPrimary),
                    ),
                    Text(
                      '${request.hoursRequested} solicitadas · ${request.createdAt}',
                      style: const TextStyle(
                          fontSize: 12, color: AppColors.textSecondary),
                    ),
                  ],
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: bgColor,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(icon, size: 13, color: color),
                    const SizedBox(width: 4),
                    Text(request.statusLabel,
                        style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: color)),
                  ],
                ),
              ),
            ],
          ),
          if (request.justification.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(request.justification,
                style: const TextStyle(
                    fontSize: 12, color: AppColors.textSecondary)),
          ],
          if (request.approvalNotes != null &&
              request.approvalNotes!.isNotEmpty) ...[
            const SizedBox(height: 8),
            Container(
              width: double.infinity,
              padding:
                  const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: bgColor,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                'Obs. gestor: ${request.approvalNotes}',
                style: TextStyle(fontSize: 12, color: color),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _LoadingCard extends StatelessWidget {
  const _LoadingCard();
  @override
  Widget build(BuildContext context) => const SizedBox(
        height: 120,
        child: Center(child: CircularProgressIndicator()),
      );
}

class _ErrorCard extends StatelessWidget {
  final String error;
  final VoidCallback onRetry;
  const _ErrorCard({required this.error, required this.onRetry});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppColors.error.withValues(alpha: 0.05),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.error.withValues(alpha: 0.2)),
        ),
        child: Row(
          children: [
            const Icon(Icons.error_outline, color: AppColors.error, size: 20),
            const SizedBox(width: 10),
            Expanded(
                child: Text(error,
                    style: const TextStyle(
                        fontSize: 13, color: AppColors.error))),
            TextButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      );
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

