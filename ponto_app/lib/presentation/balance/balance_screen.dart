import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'balance_provider.dart';
import 'hour_bank_provider.dart';
import '../../data/models/work_day_model.dart';
import '../../data/models/hour_bank_request_model.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/day_calendar_label_chips.dart';

bool _canGoToNextMonth(DateTime selected) {
  final now = DateTime.now();
  return selected.year < now.year ||
      (selected.year == now.year && selected.month < now.month);
}

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
    final selectedMonth = ref.watch(selectedMonthProvider);

    return Scaffold(
      backgroundColor: AppColors.background,
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
          labelStyle:
              const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
          tabs: const [
            Tab(text: 'Saldo'),
            Tab(text: 'Movimentações'),
            Tab(text: 'Solicitações'),
          ],
        ),
        actions: [
          balanceAsync.maybeWhen(
            data: (balance) => balance.isPositive
                ? Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: TextButton.icon(
                      style:
                          TextButton.styleFrom(foregroundColor: Colors.white),
                      icon:
                          const Icon(Icons.event_available_outlined, size: 18),
                      label: const Text('Solicitar folga',
                          style: TextStyle(fontSize: 12)),
                      onPressed: () => _openRequestLeave(balance),
                    ),
                  )
                : const SizedBox.shrink(),
            orElse: () => const SizedBox.shrink(),
          ),
        ],
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Material(
            color: AppColors.primary,
            elevation: 2,
            shadowColor: Colors.black26,
            child: _HourBankMonthBar(
              selected: selectedMonth,
              canGoNext: _canGoToNextMonth(selectedMonth),
            ),
          ),
          Expanded(
            child: TabBarView(
              controller: _tabController,
              children: [
                _SaldoTab(onRequestLeave: _openRequestLeave),
                const _MovimentacoesTab(),
                const _SolicitacoesTab(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _openRequestLeave(HourBankBalanceModel balance) {
    context.pushNamed('request-leave', extra: balance);
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
        ref.invalidate(hourBankTransactionsProvider);
      },
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          const _HourBankHelpCard(),
          const SizedBox(height: 16),

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

          Text(
            DateFormat("MMMM yyyy", 'pt_BR').format(selectedMonth),
            style: const TextStyle(
              fontSize: 17,
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'Resumo da jornada neste mês (comparado à escala esperada).',
            style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
          ),
          const SizedBox(height: 12),

          // Cards do mês
          summaryAsync.when(
            loading: () => const _LoadingCard(),
            error: (e, _) => _ErrorCard(
              error: e.toString(),
              onRetry: () =>
                  ref.invalidate(monthSummaryProvider(selectedMonth)),
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
  const _MovimentacoesTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final txAsync = ref.watch(hourBankTransactionsProvider);
    final month = ref.watch(selectedMonthProvider);
    final monthLabel = DateFormat("MMMM 'de' yyyy", 'pt_BR').format(month);

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(hourBankTransactionsProvider);
        ref.invalidate(hourBankBalanceProvider);
      },
      child: txAsync.when(
        loading: () => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const [
            SizedBox(height: 120),
            Center(child: CircularProgressIndicator()),
          ],
        ),
        error: (e, _) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            _ErrorCard(
              error: e.toString(),
              onRetry: () => ref.invalidate(hourBankTransactionsProvider),
            ),
          ],
        ),
        data: (txList) {
          if (txList.isEmpty) {
            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(24),
              children: [
                Icon(Icons.receipt_long_outlined,
                    size: 56, color: AppColors.textHint.withValues(alpha: 0.8)),
                const SizedBox(height: 16),
                Text(
                  'Nenhuma movimentação em $monthLabel',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Créditos e débitos do banco só aparecem quando há lançamentos '
                  '(por exemplo ajuste da jornada ou folga compensatória aprovada). '
                  'Experimente outro mês na barra azul acima.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 13,
                    height: 1.35,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            );
          }
          return ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            children: [
              Text(
                monthLabel,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 10),
              ...txList.map(
                (tx) => Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: _TransactionTile(tx: tx),
                ),
              ),
            ],
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
  const _SolicitacoesTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final reqAsync = ref.watch(hourBankRequestsProvider);

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(hourBankRequestsProvider),
      child: reqAsync.when(
        loading: () => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const [
            SizedBox(height: 120),
            Center(child: CircularProgressIndicator()),
          ],
        ),
        error: (e, _) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            _ErrorCard(
              error: e.toString(),
              onRetry: () => ref.invalidate(hourBankRequestsProvider),
            ),
          ],
        ),
        data: (requests) {
          if (requests.isEmpty) {
            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(24),
              children: [
                Icon(Icons.event_available_outlined,
                    size: 56, color: AppColors.textHint.withValues(alpha: 0.8)),
                const SizedBox(height: 16),
                const Text(
                  'Nenhuma solicitação ainda',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Quando tiver saldo positivo, use «Solicitar folga» para pedir '
                  'folga compensatória ao gestor.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 13,
                    height: 1.35,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            );
          }
          return ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
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
          Text(
              balance.isPositive
                  ? 'Saldo positivo (crédito)'
                  : 'Saldo negativo (débito)',
              style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.85), fontSize: 13)),
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            child: FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.center,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    balance.isPositive ? '+' : '−',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.7),
                      fontSize: 28,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(width: 2),
                  Text(
                    balance.formatted,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 44,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 2,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            alignment: WrapAlignment.center,
            spacing: 16,
            runSpacing: 10,
            children: [
              _BalancePill(
                  icon: Icons.arrow_upward,
                  label: 'Créditos',
                  value: _fmt(balance.creditMinutes, '+'),
                  color: Colors.white),
              _BalancePill(
                  icon: Icons.arrow_downward,
                  label: 'Débitos',
                  value: _fmt(balance.debitMinutes, '−'),
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
                child: const FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Row(
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
  final IconData icon;
  final String label;
  final String value;
  final Color color;
  const _BalancePill(
      {required this.icon,
      required this.label,
      required this.value,
      required this.color});

  @override
  Widget build(BuildContext context) => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: color, size: 14),
          const SizedBox(width: 4),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(value,
                  style: TextStyle(
                      color: color, fontWeight: FontWeight.bold, fontSize: 15)),
              Text(label, style: TextStyle(color: color, fontSize: 10)),
            ],
          ),
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
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w500,
                      color: AppColors.textPrimary),
                ),
                Text(
                  '${tx.dateFormatted} · ${tx.typeLabel}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                      fontSize: 11, color: AppColors.textSecondary),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.only(left: 8),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 96),
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerRight,
                child: Text(
                  tx.formatted,
                  maxLines: 1,
                  style: TextStyle(
                    color: color,
                    fontWeight: FontWeight.bold,
                    fontSize: 15,
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
                ),
              ),
            ),
          ),
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
      'aprovado' => (
          AppColors.success,
          const Color(0xFFECFDF5),
          Icons.check_circle_outline
        ),
      'rejeitado' => (
          AppColors.error,
          const Color(0xFFFFF1F2),
          Icons.cancel_outlined
        ),
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
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
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
                    style:
                        const TextStyle(fontSize: 13, color: AppColors.error))),
            TextButton(
                onPressed: onRetry, child: const Text('Tentar novamente')),
          ],
        ),
      );
}

/// Barra de mês compartilhada (Saldo + Movimentações filtram por este mês).
class _HourBankMonthBar extends ConsumerWidget {
  final DateTime selected;
  final bool canGoNext;

  const _HourBankMonthBar({
    required this.selected,
    required this.canGoNext,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 4, 4, 10),
      child: Row(
        children: [
          IconButton(
            tooltip: 'Mês anterior',
            icon: const Icon(Icons.chevron_left, color: Colors.white),
            onPressed: () {
              ref.read(selectedMonthProvider.notifier).state =
                  DateTime(selected.year, selected.month - 1);
            },
          ),
          Expanded(
            child: Column(
              children: [
                Text(
                  DateFormat("MMMM yyyy", 'pt_BR').format(selected),
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                Text(
                  'Altere o mês para ver o resumo e as movimentações',
                  textAlign: TextAlign.center,
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.75),
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Próximo mês',
            icon: Icon(
              Icons.chevron_right,
              color: canGoNext ? Colors.white : Colors.white38,
            ),
            onPressed: canGoNext
                ? () {
                    ref.read(selectedMonthProvider.notifier).state =
                        DateTime(selected.year, selected.month + 1);
                  }
                : null,
          ),
        ],
      ),
    );
  }
}

class _HourBankHelpCard extends StatelessWidget {
  const _HourBankHelpCard();

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: AppColors.divider),
      ),
      clipBehavior: Clip.antiAlias,
      child: Theme(
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
          childrenPadding:
              const EdgeInsets.only(left: 16, right: 16, bottom: 14),
          leading: const Icon(Icons.lightbulb_outline_rounded,
              color: AppColors.info, size: 22),
          title: const Text(
            'O que cada número significa?',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: AppColors.textPrimary,
            ),
          ),
          subtitle: Text(
            'Toque para ler os detalhes',
            style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
          ),
          children: const [
            _HelpRow(
              title: 'Saldo total (cartão colorido no topo)',
              body:
                  'É o saldo acumulado do seu banco de horas: soma de todas as '
                  'movimentações registadas (créditos e débitos). Este valor é o '
                  'que vale para solicitar folga compensatória quando estiver positivo.',
            ),
            SizedBox(height: 12),
            _HelpRow(
              title: 'Saldo do mês (resumo abaixo)',
              body: 'Mostra a diferença entre as horas trabalhadas e as horas '
                  'esperadas pela sua escala apenas no mês selecionado. Ajuda a '
                  'entender o mês; não substitui o saldo total do banco.',
            ),
            SizedBox(height: 12),
            _HelpRow(
              title: 'Movimentações',
              body:
                  'Lista os lançamentos do banco no mesmo mês da barra azul — '
                  'créditos (+) e débitos (−), como no histórico oficial.',
            ),
            SizedBox(height: 12),
            _HelpRow(
              title: 'Detalhamento diário',
              body: 'Cada dia mostra os registos de ponto e o saldo da jornada '
                  'calculado pelo sistema para esse dia.',
            ),
          ],
        ),
      ),
    );
  }
}

class _HelpRow extends StatelessWidget {
  final String title;
  final String body;

  const _HelpRow({required this.title, required this.body});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          body,
          style: TextStyle(
            fontSize: 12,
            height: 1.4,
            color: AppColors.textSecondary,
          ),
        ),
      ],
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
                color: (isPositive ? AppColors.success : AppColors.error)
                    .withValues(alpha: 0.3),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Column(
            children: [
              Text(
                'Saldo do mês',
                style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.85), fontSize: 14),
              ),
              const SizedBox(height: 8),
              SizedBox(
                width: double.infinity,
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.center,
                  child: Text(
                    summary.balanceHours,
                    maxLines: 1,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 42,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 2,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 4),
              Text(
                isPositive ? 'Horas extras' : 'Horas em débito',
                style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.85), fontSize: 12),
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
                color: summary.totalAbsences > 0
                    ? AppColors.error
                    : AppColors.textSecondary,
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
              style: const TextStyle(
                  fontSize: 11, color: AppColors.textSecondary)),
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
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Data
          SizedBox(
            width: 42,
            child: FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.topCenter,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    day.dateFormatted.split('/')[0],
                    maxLines: 1,
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
                    maxLines: 1,
                    style: const TextStyle(
                        fontSize: 11, color: AppColors.textSecondary),
                  ),
                ],
              ),
            ),
          ),
          const VerticalDivider(width: 16, color: AppColors.divider),

          // Horários
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: _TimeChip(
                          time: day.entryTime, label: 'E'),
                    ),
                    const SizedBox(width: 4),
                    Expanded(
                      child: _TimeChip(
                          time: day.lunchStart, label: 'SA'),
                    ),
                    const SizedBox(width: 4),
                    Expanded(
                      child: _TimeChip(
                          time: day.lunchEnd, label: 'VA'),
                    ),
                    const SizedBox(width: 4),
                    Expanded(
                      child: _TimeChip(time: day.exitTime, label: 'S'),
                    ),
                  ],
                ),
                if (day.totalMinutes > 0) ...[
                  const SizedBox(height: 4),
                  Text(
                    '${day.totalHours}h trabalhadas',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        fontSize: 11, color: AppColors.textSecondary),
                  ),
                ],
              ],
            ),
          ),

          const SizedBox(width: 6),

          // Saldo
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 92),
            child: FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerRight,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    day.extraHours,
                    maxLines: 1,
                    style: TextStyle(
                      color: balanceColor,
                      fontWeight: FontWeight.bold,
                      fontSize: 15,
                    ),
                  ),
                  if (day.dayCalendarLabelsPt.isNotEmpty)
                    DayCalendarLabelChips(
                      labels: day.dayCalendarLabelsPt,
                      fontSize: 9,
                    )
                  else if (day.status != 'normal')
                    _StatusBadge(status: day.status),
                ],
              ),
            ),
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
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
      decoration: BoxDecoration(
        color: time != null
            ? AppColors.primary.withValues(alpha: 0.08)
            : AppColors.surfaceVariant,
        borderRadius: BorderRadius.circular(5),
      ),
      child: FittedBox(
        fit: BoxFit.scaleDown,
        alignment: Alignment.center,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              label,
              maxLines: 1,
              style: TextStyle(
                fontSize: 8,
                color: time != null ? AppColors.primary : AppColors.textHint,
              ),
            ),
            Text(
              time ?? '--:--',
              maxLines: 1,
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.bold,
                color:
                    time != null ? AppColors.textPrimary : AppColors.textHint,
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ],
        ),
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
