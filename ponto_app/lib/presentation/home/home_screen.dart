import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../auth/auth_provider.dart';
import '../point/register_point_provider.dart';
import 'today_provider.dart';
import 'notifications_provider.dart';
import '../../data/models/time_record_model.dart';
import '../../core/theme/app_theme.dart';
import '../../core/constants/app_constants.dart';
import '../../core/widgets/skeleton.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> with WidgetsBindingObserver, SingleTickerProviderStateMixin {
  int _selectedNavIndex = 0;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      // Só refresca pontos se não tiver dados (o provider já carrega no construtor)
      final today = ref.read(todayProvider);
      if (today.data == null && !today.isLoading) {
        ref.read(todayProvider.notifier).refresh();
      }
      // Perfil: throttle de 5 min interno no notifier
      ref.read(authProvider.notifier).refreshProfile();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      // Quando o app volta do background, força refresh para pegar mudanças do servidor
      ref.read(authProvider.notifier).forceRefreshProfile();
      ref.read(todayProvider.notifier).refresh();
    }
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);
    final todayState = ref.watch(todayProvider);
    final pendingCount = ref.watch(pendingOfflineCountProvider);
    final user = authState.user;
    final appPunchDisabled = user?.employee?.appPunchDisabled ?? false;
    final profileLoading = authState.isRefreshingProfile || authState.isLoading;

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

                  // Registro de ponto — skeleton enquanto perfil recarrega
                  if (profileLoading)
                    const SkeletonPunchButton()
                  else if (appPunchDisabled)
                    _buildTotemOnlyCard()
                  else ...[
                    if (todayState.isLoading && todayState.data == null)
                      const SkeletonPunchButton()
                    else if (todayState.data != null &&
                        !todayState.data!.isComplete)
                      _buildPunchButton(context, ref, todayState.data!)
                    else if (todayState.data?.isComplete == true)
                      _buildCompletedCard()
                    else ...[
                      // Falha ao carregar /dia ou estado vazio — ainda permite bater ponto
                      if (todayState.error != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: Text(
                            todayState.error!,
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppColors.error,
                            ),
                            textAlign: TextAlign.center,
                          ),
                        ),
                      _buildPunchFallbackButton(context, ref),
                    ],
                  ],

                  const SizedBox(height: 20),

                  // Módulos do app (hub)
                  _buildModuleGrid(context),

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
    final unread = ref.watch(unreadNotificationsCountProvider);
    return SliverAppBar(
      expandedHeight: 140,
      floating: false,
      pinned: true,
      backgroundColor: AppColors.primary,
      actions: [
        // Sino de notificações com badge
        Stack(
          clipBehavior: Clip.none,
          children: [
            IconButton(
              icon: const Icon(Icons.notifications_outlined, color: Colors.white),
              tooltip: 'Notificações',
              onPressed: () => _showNotificationsSheet(context, ref),
            ),
            if (unread > 0)
              Positioned(
                top: 8,
                right: 8,
                child: Container(
                  width: 16,
                  height: 16,
                  decoration: BoxDecoration(
                    color: const Color(0xFFEF4444),
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 1.5),
                  ),
                  child: Center(
                    child: Text(
                      unread > 9 ? '9+' : '$unread',
                      style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
                    ),
                  ),
                ),
              ),
          ],
        ),
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

  void _showNotificationsSheet(BuildContext context, WidgetRef ref) {
    ref.read(notificationsProvider.notifier).markAllRead();
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _NotificationsSheet(ref: ref),
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
          const _LiveClock(),
          const SizedBox(height: 16),
          _buildRecordsTimeline(state.data),
        ],
      ),
    );
  }

  /// Timeline horizontal mostrando cada ponto batido com horário visível.
  Widget _buildRecordsTimeline(TodayStatusModel? data) {
    final records = data?.records ?? [];

    if (records.isEmpty) {
      return Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          _TimelineDot(
            label: 'Entrada',
            time: null,
            color: AppColors.entrada,
            isNext: true,
          ),
        ],
      );
    }

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          for (int i = 0; i < records.length; i++) ...[
            if (i > 0)
              Container(
                width: 24,
                height: 2,
                color: Colors.white.withValues(alpha: 0.4),
              ),
            _TimelineDot(
              label: records[i].type == 'entrada' ? 'Entrada' : 'Saída',
              time: records[i].datetimeLocal.split(' ').last.substring(0, 5),
              color: records[i].type == 'entrada' ? AppColors.entrada : AppColors.saida,
              isNext: false,
            ),
          ],
          // Próximo ponto esperado
          if (data != null && !data.isComplete) ...[
            Container(
              width: 24,
              height: 2,
              color: Colors.white.withValues(alpha: 0.2),
            ),
            _TimelineDot(
              label: data.nextType == 'entrada' ? 'Entrada' : 'Saída',
              time: null,
              color: data.nextType == 'entrada' ? AppColors.entrada : AppColors.saida,
              isNext: true,
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildPunchButton(BuildContext context, WidgetRef ref, TodayStatusModel today) {
    final nextType = today.nextType ?? 'entrada';
    final label = AppConstants.pointTypeLabels[nextType] ?? nextType;
    final color = _typeColor(nextType);

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: () => context.push('/home/register-point', extra: nextType),
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

  /// Resumo do dia indisponível — mantém «Bater ponto» com tipo [entrada] por defeito.
  Widget _buildPunchFallbackButton(BuildContext context, WidgetRef ref) {
    const nextType = 'entrada';
    final label = AppConstants.pointTypeLabels[nextType] ?? nextType;
    final color = _typeColor(nextType);

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: () => context.push('/home/register-point', extra: nextType),
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
                  '$label · resumo do dia indisponível',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.85),
                    fontSize: 12,
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

  Widget _buildTotemOnlyCard() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            const Color(0xFF6366F1).withValues(alpha: 0.08),
            const Color(0xFF8B5CF6).withValues(alpha: 0.06),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: const Color(0xFF6366F1).withValues(alpha: 0.25),
          width: 1.5,
        ),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: const Color(0xFF6366F1).withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(Icons.tablet_android, color: Color(0xFF6366F1), size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Ponto exclusivo pelo totem',
                  style: TextStyle(
                    color: Color(0xFF4338CA),
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Dirija-se ao totem para registrar entrada ou saída.',
                  style: TextStyle(
                    color: AppColors.textSecondary,
                    fontSize: 12,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: const Color(0xFF6366F1).withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Text(
              'Consultas\ndisponíveis',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: Color(0xFF4338CA),
                fontSize: 10,
                fontWeight: FontWeight.w600,
                height: 1.3,
              ),
            ),
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

  Widget _buildModuleGrid(BuildContext context) {
    final modules = [
      _ModuleTile(
        icon: Icons.history_rounded,
        label: 'Histórico',
        subtitle: 'Registros de ponto',
        color: const Color(0xFF3B82F6),
        onTap: () => context.goNamed('history'),
      ),
      _ModuleTile(
        icon: Icons.account_balance_wallet_rounded,
        label: 'Banco de Horas',
        subtitle: 'Saldo e transações',
        color: const Color(0xFF10B981),
        onTap: () => context.goNamed('balance'),
      ),
      _ModuleTile(
        icon: Icons.receipt_long_rounded,
        label: 'Holerites',
        subtitle: 'Contracheques',
        color: const Color(0xFFEF4444),
        onTap: () => context.goNamed('payslips'),
      ),
      _ModuleTile(
        icon: Icons.campaign_rounded,
        label: 'Comunicados',
        subtitle: 'Avisos da empresa',
        color: const Color(0xFF6366F1),
        onTap: () => context.goNamed('communications'),
      ),
      _ModuleTile(
        icon: Icons.beach_access_rounded,
        label: 'Férias',
        subtitle: 'Solicitar e acompanhar',
        color: const Color(0xFFF59E0B),
        onTap: () => context.goNamed('vacation'),
      ),
      _ModuleTile(
        icon: Icons.edit_calendar_rounded,
        label: 'Solicitações',
        subtitle: 'Correções e adições',
        color: const Color(0xFF64748B),
        onTap: () => context.goNamed('edit-requests'),
      ),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Serviços',
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.bold,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 12),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
          childAspectRatio: 1.6,
          children: modules.map((m) => _buildModuleTile(context, m)).toList(),
        ),
      ],
    );
  }

  Widget _buildModuleTile(BuildContext context, _ModuleTile module) {
    return GestureDetector(
      onTap: module.onTap,
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.divider),
          boxShadow: [
            BoxShadow(
              color: module.color.withValues(alpha: 0.08),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Container(
              padding: const EdgeInsets.all(7),
              decoration: BoxDecoration(
                color: module.color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(module.icon, color: module.color, size: 20),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  module.label,
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                    color: AppColors.textPrimary,
                  ),
                ),
                Text(
                  module.subtitle,
                  style: const TextStyle(
                    fontSize: 11,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBottomNav(BuildContext context) {
    final unread = ref.watch(unreadNotificationsCountProvider);

    final items = [
      _BottomNavItemData(icon: Icons.home_rounded, label: 'Início', color: AppColors.primary),
      _BottomNavItemData(icon: Icons.history_rounded, label: 'Histórico', color: const Color(0xFF3B82F6)),
      _BottomNavItemData(icon: Icons.account_balance_wallet_rounded, label: 'Banco Horas', color: const Color(0xFF10B981)),
      _BottomNavItemData(icon: Icons.notifications_outlined, label: 'Avisos', color: const Color(0xFF6366F1), badge: unread),
    ];

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            blurRadius: 24,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
          child: Row(
            children: List.generate(items.length, (i) {
              final item = items[i];
              final isSelected = _selectedNavIndex == i;
              return Expanded(
                child: GestureDetector(
                  onTap: () {
                    HapticFeedback.lightImpact();
                    setState(() => _selectedNavIndex = i);
                    switch (i) {
                      case 1: context.goNamed('history');
                      case 2: context.goNamed('balance');
                      case 3: _showNotificationsSheet(context, ref);
                      default: break;
                    }
                  },
                  behavior: HitTestBehavior.opaque,
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    curve: Curves.easeInOut,
                    padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
                    decoration: BoxDecoration(
                      color: isSelected ? item.color.withValues(alpha: 0.1) : Colors.transparent,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Stack(
                          clipBehavior: Clip.none,
                          children: [
                            AnimatedContainer(
                              duration: const Duration(milliseconds: 200),
                              padding: const EdgeInsets.all(6),
                              decoration: BoxDecoration(
                                color: isSelected ? item.color.withValues(alpha: 0.15) : Colors.transparent,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Icon(
                                item.icon,
                                size: 24,
                                color: isSelected ? item.color : AppColors.textSecondary,
                              ),
                            ),
                            if (item.badge > 0)
                              Positioned(
                                top: -2,
                                right: -4,
                                child: Container(
                                  width: 15,
                                  height: 15,
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFEF4444),
                                    shape: BoxShape.circle,
                                    border: Border.all(color: Colors.white, width: 1.5),
                                  ),
                                  child: Center(
                                    child: Text(
                                      item.badge > 9 ? '9+' : '${item.badge}',
                                      style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.bold),
                                    ),
                                  ),
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 3),
                        AnimatedDefaultTextStyle(
                          duration: const Duration(milliseconds: 200),
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: isSelected ? FontWeight.w700 : FontWeight.w400,
                            color: isSelected ? item.color : AppColors.textSecondary,
                          ),
                          child: Text(item.label),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }),
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

}

/// Ponto individual na timeline horizontal de pontos do dia.
class _TimelineDot extends StatelessWidget {
  final String label;
  final String? time; // null = ainda não batido
  final Color color;
  final bool isNext;

  const _TimelineDot({
    required this.label,
    required this.time,
    required this.color,
    required this.isNext,
  });

  @override
  Widget build(BuildContext context) {
    final done = time != null;
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        // Horário (ocupa espaço fixo para alinhar os círculos)
        SizedBox(
          height: 20,
          child: done
              ? Text(
                  time!,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                  ),
                )
              : Text(
                  '--:--',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.35),
                    fontSize: 13,
                  ),
                ),
        ),
        const SizedBox(height: 6),
        // Círculo
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: done
                ? color
                : isNext
                    ? color.withValues(alpha: 0.25)
                    : Colors.white.withValues(alpha: 0.15),
            border: Border.all(
              color: done ? color : (isNext ? color : Colors.white24),
              width: isNext ? 2 : 0,
            ),
          ),
          child: Icon(
            done
                ? Icons.check
                : isNext
                    ? Icons.access_time
                    : Icons.circle_outlined,
            size: 16,
            color: done
                ? Colors.white
                : isNext
                    ? color
                    : Colors.white30,
          ),
        ),
        const SizedBox(height: 6),
        // Label
        Text(
          label,
          style: TextStyle(
            color: done ? Colors.white : (isNext ? Colors.white70 : Colors.white38),
            fontSize: 10,
            fontWeight: done ? FontWeight.w600 : FontWeight.normal,
          ),
        ),
      ],
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

class _BottomNavItemData {
  final IconData icon;
  final String label;
  final Color color;
  final int badge;

  const _BottomNavItemData({
    required this.icon,
    required this.label,
    required this.color,
    this.badge = 0,
  });
}

/// Bottom sheet de notificações recentes
class _NotificationsSheet extends ConsumerWidget {
  final WidgetRef ref;
  const _NotificationsSheet({required this.ref});

  @override
  Widget build(BuildContext context, WidgetRef watchRef) {
    final notifications = watchRef.watch(notificationsProvider);

    return DraggableScrollableSheet(
      initialChildSize: 0.55,
      minChildSize: 0.35,
      maxChildSize: 0.85,
      expand: false,
      builder: (_, controller) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(
          children: [
            const SizedBox(height: 10),
            Container(
              width: 40, height: 4,
              decoration: BoxDecoration(
                color: AppColors.divider,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 16),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Row(
                children: [
                  const Text(
                    'Notificações',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const Spacer(),
                  if (notifications.isNotEmpty)
                    TextButton(
                      onPressed: () => watchRef.read(notificationsProvider.notifier).clear(),
                      child: const Text('Limpar', style: TextStyle(fontSize: 12)),
                    ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: notifications.isEmpty
                  ? const Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.notifications_none, size: 48, color: AppColors.textSecondary),
                          SizedBox(height: 12),
                          Text(
                            'Nenhuma notificação',
                            style: TextStyle(color: AppColors.textSecondary, fontSize: 14),
                          ),
                        ],
                      ),
                    )
                  : ListView.separated(
                      controller: controller,
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      itemCount: notifications.length,
                      separatorBuilder: (_, __) => const Divider(height: 1, indent: 64),
                      itemBuilder: (_, i) {
                        final n = notifications[i];
                        final iconData = switch (n.icon) {
                          'ponto' => Icons.fingerprint,
                          'edit' => Icons.edit_note,
                          'add' => Icons.add_circle_outline,
                          'warning' => Icons.warning_amber_rounded,
                          _ => Icons.notifications_outlined,
                        };
                        final iconColor = switch (n.icon) {
                          'ponto' => AppColors.primary,
                          'edit' => const Color(0xFF3B82F6),
                          'add' => const Color(0xFF10B981),
                          'warning' => AppColors.warning,
                          _ => const Color(0xFF6366F1),
                        };
                        return ListTile(
                          contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
                          leading: Container(
                            width: 40, height: 40,
                            decoration: BoxDecoration(
                              color: iconColor.withValues(alpha: 0.1),
                              shape: BoxShape.circle,
                            ),
                            child: Icon(iconData, color: iconColor, size: 20),
                          ),
                          title: Text(
                            n.title,
                            style: TextStyle(
                              fontWeight: n.read ? FontWeight.w500 : FontWeight.bold,
                              fontSize: 13,
                              color: AppColors.textPrimary,
                            ),
                          ),
                          subtitle: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(n.body, style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                              const SizedBox(height: 2),
                              Text(
                                _formatTime(n.createdAt),
                                style: const TextStyle(fontSize: 10, color: AppColors.textSecondary),
                              ),
                            ],
                          ),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }

  String _formatTime(DateTime dt) {
    final now = DateTime.now();
    final diff = now.difference(dt);
    if (diff.inMinutes < 1) return 'Agora';
    if (diff.inMinutes < 60) return 'Há ${diff.inMinutes} min';
    if (diff.inHours < 24) return 'Há ${diff.inHours}h';
    return DateFormat('dd/MM HH:mm').format(dt);
  }
}

/// Relógio ao vivo com Timer próprio — completamente isolado do widget pai.
/// O setState() chama apenas este widget, sem provocar rebuilds nos providers.
class _LiveClock extends StatefulWidget {
  const _LiveClock();

  @override
  State<_LiveClock> createState() => _LiveClockState();
}

class _LiveClockState extends State<_LiveClock> {
  late Timer _timer;
  DateTime _now = DateTime.now();

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => _now = DateTime.now());
    });
  }

  @override
  void dispose() {
    _timer.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final hm = DateFormat('HH:mm').format(_now);
    final ss = DateFormat(':ss').format(_now);
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      crossAxisAlignment: CrossAxisAlignment.baseline,
      textBaseline: TextBaseline.alphabetic,
      children: [
        Text(
          hm,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 56,
            fontWeight: FontWeight.bold,
            letterSpacing: 2,
          ),
        ),
        Text(
          ss,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.65),
            fontSize: 28,
            fontWeight: FontWeight.w400,
            letterSpacing: 1,
          ),
        ),
      ],
    );
  }
}

class _ModuleTile {
  final IconData icon;
  final String label;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;

  const _ModuleTile({
    required this.icon,
    required this.label,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });
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

