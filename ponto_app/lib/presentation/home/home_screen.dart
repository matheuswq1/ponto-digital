import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../auth/auth_provider.dart';
import '../point/register_point_provider.dart';
import 'today_provider.dart';
import '../../data/models/time_record_model.dart';
import '../../core/theme/app_theme.dart';
import '../../core/constants/app_constants.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  @override
  void initState() {
    super.initState();
    // Carrega dados ao abrir a tela
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(todayProvider.notifier).refresh();
    });
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);
    final todayState = ref.watch(todayProvider);
    final pendingCount = ref.watch(pendingOfflineCountProvider);
    final user = authState.user;

    return Scaffold(
      backgroundColor: AppColors.background,
      body: RefreshIndicator(
        onRefresh: () async => ref.read(todayProvider.notifier).refresh(),
        child: CustomScrollView(
          slivers: [
            _buildAppBar(context, ref, user?.firstName ?? ''),
            SliverPadding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              sliver: SliverList(
                delegate: SliverChildListDelegate([
                  // Banner offline pendente
                  pendingCount.when(
                    data: (count) => count > 0
                        ? _buildOfflineBanner(context, ref, count)
                        : const SizedBox.shrink(),
                    loading: () => const SizedBox.shrink(),
                    error: (_, __) => const SizedBox.shrink(),
                  ),

                  const SizedBox(height: 20),

                  // Card do dia atual
                  _buildTodayCard(context, todayState),

                  const SizedBox(height: 20),

                  // Botão principal de bater ponto
                  if (todayState.data != null && !todayState.data!.isComplete)
                    _buildPunchButton(context, ref, todayState.data!),

                  if (todayState.data?.isComplete == true)
                    _buildCompletedCard(),

                  const SizedBox(height: 20),

                  // Registros do dia
                  if (todayState.data != null)
                    _buildTodayRecords(todayState.data!.records),

                  const SizedBox(height: 100),
                ]),
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: _buildBottomNav(context),
    );
  }

  Widget _buildAppBar(BuildContext context, WidgetRef ref, String name) {
    return SliverAppBar(
      expandedHeight: 140,
      floating: false,
      pinned: true,
      backgroundColor: AppColors.primary,
      actions: [
        IconButton(
          icon: const Icon(Icons.person_outline, color: Colors.white),
          tooltip: 'Perfil',
          onPressed: () => context.pushNamed('profile'),
        ),
        IconButton(
          icon: const Icon(Icons.logout, color: Colors.white),
          onPressed: () async {
            await ref.read(authProvider.notifier).logout();
          },
        ),
      ],
      flexibleSpace: FlexibleSpaceBar(
        background: Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [AppColors.primaryDark, AppColors.primaryLight],
            ),
          ),
          padding: const EdgeInsets.fromLTRB(20, 60, 20, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              Text(
                'Olá, $name 👋',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                DateFormat("EEEE, d 'de' MMMM", 'pt_BR').format(DateTime.now()),
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.85),
                  fontSize: 13,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildOfflineBanner(BuildContext context, WidgetRef ref, int count) {
    return Container(
      margin: const EdgeInsets.only(top: 16),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.warning.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.warning.withValues(alpha: 0.4)),
      ),
      child: Row(
        children: [
          const Icon(Icons.wifi_off, color: AppColors.warning, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              '$count ponto(s) pendente(s) de sincronização',
              style: const TextStyle(color: AppColors.warning, fontSize: 13),
            ),
          ),
          TextButton(
            onPressed: () async {
              final notifier = ref.read(registerPointProvider.notifier);
              final result = await notifier.syncOffline();
              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                  content: Text('${result['synced']} ponto(s) sincronizado(s)'),
                  backgroundColor: AppColors.success,
                ));
                ref.invalidate(pendingOfflineCountProvider);
              }
            },
            style: TextButton.styleFrom(foregroundColor: AppColors.warning),
            child: const Text('Sincronizar', style: TextStyle(fontSize: 12)),
          ),
        ],
      ),
    );
  }

  Widget _buildTodayCard(BuildContext context, TodayState state) {
    if (state.isLoading) {
      return const _ShimmerCard();
    }

    final now = DateTime.now();
    final timeStr = DateFormat('HH:mm').format(now);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppColors.primary, AppColors.primaryLight],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.3),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Horário atual',
                style: TextStyle(color: Colors.white70, fontSize: 13),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  state.data?.isComplete == true ? 'Completo ✓' : 'Em andamento',
                  style: const TextStyle(color: Colors.white, fontSize: 11),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            timeStr,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 48,
              fontWeight: FontWeight.bold,
              letterSpacing: 2,
            ),
          ),
          const SizedBox(height: 16),
          // Linha de progresso: pares dinâmicos entrada/saída
          _buildPairsRow(state.data),
        ],
      ),
    );
  }

  Widget _buildPunchButton(BuildContext context, WidgetRef ref, TodayStatusModel today) {
    final nextType = today.nextType ?? 'entrada';
    final label = AppConstants.pointTypeLabels[nextType] ?? nextType;
    final color = _typeColor(nextType);

    return GestureDetector(
      onTap: () => context.goNamed('register-point', extra: nextType),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 20),
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: color.withValues(alpha: 0.4),
              blurRadius: 20,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.touch_app, color: Colors.white, size: 28),
            const SizedBox(width: 12),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Bater Ponto',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                Text(
                  label,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.85),
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCompletedCard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.success.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.success.withValues(alpha: 0.3)),
      ),
      child: const Row(
        children: [
          Icon(Icons.check_circle, color: AppColors.success, size: 28),
          SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Jornada concluída!',
                style: TextStyle(
                  color: AppColors.success,
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
              Text(
                'Todos os pontos do dia foram registrados.',
                style: TextStyle(color: AppColors.textSecondary, fontSize: 12),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildTodayRecords(List records) {
    if (records.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Registros de hoje',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 12),
        ...records.map((r) => _RecordTile(record: r as dynamic)),
      ],
    );
  }

  Widget _buildBottomNav(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        boxShadow: [BoxShadow(color: AppColors.shadow, blurRadius: 12)],
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _NavItem(icon: Icons.home_rounded, label: 'Início', onTap: () {}),
              _NavItem(
                icon: Icons.history_rounded,
                label: 'Histórico',
                onTap: () => context.goNamed('history'),
              ),
              _NavItem(
                icon: Icons.bar_chart_rounded,
                label: 'Banco Horas',
                onTap: () => context.goNamed('balance'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Color _typeColor(String type) {
    return switch (type) {
      'entrada' => AppColors.entrada,
      'saida' => AppColors.saida,
      _ => AppColors.primary,
    };
  }

  /// Exibe os pares entrada/saída como indicadores circulares dinâmicos.
  Widget _buildPairsRow(TodayStatusModel? data) {
    if (data == null || data.records.isEmpty) {
      return Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          _PairDot(index: 0, entrada: null, saida: null, isNext: true),
        ],
      );
    }

    final pairs = data.pairs;
    final showEmpty = !data.isComplete;

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          for (int i = 0; i < pairs.length; i++) ...[
            if (i > 0) _StepConnector(active: true),
            _PairDot(
              index: i,
              entrada: pairs[i].entrada,
              saida: pairs[i].saida,
              isNext: false,
            ),
          ],
          if (showEmpty && pairs.isNotEmpty) ...[
            _StepConnector(active: false),
            _PairDot(
              index: pairs.length,
              entrada: null,
              saida: null,
              isNext: true,
            ),
          ],
          if (pairs.isEmpty && showEmpty)
            _PairDot(index: 0, entrada: null, saida: null, isNext: true),
        ],
      ),
    );
  }
}

/// Um par entrada/saída representado por dois pontos sobrepostos em coluna.
class _PairDot extends StatelessWidget {
  final int index;
  final TimeRecordModel? entrada;
  final TimeRecordModel? saida;
  final bool isNext;

  const _PairDot({
    required this.index,
    required this.entrada,
    required this.saida,
    required this.isNext,
  });

  String _fmt(TimeRecordModel r) =>
      r.datetimeLocal.split(' ').last.substring(0, 5);

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        // Entrada
        _dot(
          done: entrada != null,
          isNext: isNext && entrada == null,
          label: entrada != null ? _fmt(entrada!) : 'E${index + 1}',
        ),
        const SizedBox(height: 2),
        // Saída
        _dot(
          done: saida != null,
          isNext: entrada != null && saida == null,
          label: saida != null ? _fmt(saida!) : 'S${index + 1}',
        ),
      ],
    );
  }

  Widget _dot({required bool done, required bool isNext, required String label}) {
    return Column(
      children: [
        Container(
          width: 26,
          height: 26,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: done
                ? Colors.white
                : isNext
                    ? Colors.white.withValues(alpha: 0.55)
                    : Colors.white.withValues(alpha: 0.2),
          ),
          child: Icon(
            done ? Icons.check : (isNext ? Icons.radio_button_unchecked : Icons.circle_outlined),
            size: 14,
            color: done ? AppColors.primary : (isNext ? Colors.white : Colors.white38),
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          style: TextStyle(
            color: done ? Colors.white : (isNext ? Colors.white70 : Colors.white38),
            fontSize: 8,
            fontWeight: done ? FontWeight.bold : FontWeight.normal,
          ),
        ),
      ],
    );
  }
}

class _StepConnector extends StatelessWidget {
  final bool active;
  const _StepConnector({required this.active});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        height: 2,
        color: active ? Colors.white : Colors.white.withValues(alpha: 0.25),
        margin: const EdgeInsets.only(bottom: 20),
      ),
    );
  }
}

class _RecordTile extends StatelessWidget {
  final dynamic record;
  const _RecordTile({required this.record});

  @override
  Widget build(BuildContext context) {
    final color = switch (record.type as String) {
      'entrada' => AppColors.entrada,
      'saida' => AppColors.saida,
      _ => AppColors.primary,
    };
    final time = (record.datetimeLocal as String).split(' ').last.substring(0, 5);

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.divider),
      ),
      child: Row(
        children: [
          Container(
            width: 10,
            height: 10,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              record.typeLabel as String,
              style: const TextStyle(
                fontWeight: FontWeight.w500,
                color: AppColors.textPrimary,
              ),
            ),
          ),
          Text(
            time,
            style: const TextStyle(
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
              fontSize: 16,
            ),
          ),
          if (record.offline == true) ...[
            const SizedBox(width: 8),
            const Icon(Icons.wifi_off, size: 14, color: AppColors.warning),
          ],
          if (record.photoUrl != null) ...[
            const SizedBox(width: 8),
            const Icon(Icons.photo_camera, size: 14, color: AppColors.textSecondary),
          ],
        ],
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _NavItem({required this.icon, required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: AppColors.primary, size: 26),
          const SizedBox(height: 2),
          Text(label,
              style: const TextStyle(fontSize: 11, color: AppColors.textSecondary)),
        ],
      ),
    );
  }
}

class _ShimmerCard extends StatelessWidget {
  const _ShimmerCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 180,
      decoration: BoxDecoration(
        color: AppColors.surfaceVariant,
        borderRadius: BorderRadius.circular(20),
      ),
    );
  }
}

