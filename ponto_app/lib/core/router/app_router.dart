import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../presentation/auth/auth_provider.dart';
import '../../presentation/auth/login_screen.dart';
import '../../presentation/auth/unlock_screen.dart';
import '../../presentation/auth/face_enroll_screen.dart';
import '../../presentation/home/home_screen.dart';
import '../../presentation/settings/settings_screen.dart';
import '../../presentation/point/register_point_screen.dart';
import '../../presentation/history/history_screen.dart';
import '../../presentation/balance/balance_screen.dart';
import '../../presentation/profile/profile_screen.dart';
import '../../presentation/edits/edit_requests_screen.dart';
import '../../presentation/edits/request_add_point_screen.dart';
import '../../presentation/edits/request_edit_screen.dart';
import '../../presentation/totem/totem_screen.dart';
import '../../presentation/balance/request_leave_screen.dart';
import '../../presentation/communications/communications_screen.dart';
import '../../presentation/payslips/payslips_screen.dart';
import '../../presentation/vacation/vacation_screen.dart';
import '../../presentation/pay_period/pay_period_screen.dart';
import '../../data/models/time_record_model.dart';
import '../../data/models/hour_bank_request_model.dart';

final routerProvider = Provider<GoRouter>((ref) {
  /// Não usar [ref.watch(authProvider)] aqui: cada mudança de auth (ex.: fim de
  /// [refreshProfile]) recriava o [GoRouter], cancelando navegações pendentes —
  /// o «Bater ponto» parecia não responder.
  final authRefreshTick = ValueNotifier<int>(0);
  ref.listen<AuthState>(authProvider, (_, __) {
    authRefreshTick.value++;
  });
  ref.onDispose(authRefreshTick.dispose);

  return GoRouter(
    initialLocation: '/login',
    refreshListenable: authRefreshTick,
    redirect: (context, state) {
      final authState = ref.read(authProvider);
      final isLoading = authState.status == AuthStatus.initial ||
          authState.status == AuthStatus.loading;
      final loc = state.matchedLocation;
      final isLogin = loc == '/login';
      final isUnlock = loc == '/unlock';

      if (isLoading) return null;
      if (!authState.hasSession) {
        if (!isLogin) return '/login';
        return null;
      }
      if (authState.isAwaitingBiometric) {
        if (!isUnlock) return '/unlock';
        return null;
      }
      final isTotem = authState.user?.role == 'totem';
      final isFaceEnroll = loc == '/face-enroll';
      final isTotemRoute = loc == '/totem';
      if (authState.isAuthenticated) {
        // Totem só pode acessar /totem
        if (isTotem && !isTotemRoute) return '/totem';
        // Demais usuários não podem entrar em /totem
        if (!isTotem && isTotemRoute) return '/home';
        if (!isTotem && (isLogin || isUnlock) && !isFaceEnroll) return '/home';
      }
      return null;
    },
    routes: [
      GoRoute(
        path: '/login',
        name: 'login',
        builder: (_, __) => const LoginScreen(),
      ),
      GoRoute(
        path: '/unlock',
        name: 'unlock',
        builder: (_, __) => const UnlockScreen(),
      ),
      GoRoute(
        path: '/totem',
        name: 'totem',
        builder: (_, __) => const TotemScreen(),
      ),
      GoRoute(
        path: '/face-enroll',
        name: 'face-enroll',
        builder: (_, state) {
          final extra = state.extra as Map<String, dynamic>?;
          final returnPointType = extra?['returnPointType'] as String?;
          return FaceEnrollScreen(returnPointType: returnPointType);
        },
      ),
      GoRoute(
        path: '/home',
        name: 'home',
        builder: (_, __) => const HomeScreen(),
        routes: [
          GoRoute(
            path: 'register-point',
            name: 'register-point',
            pageBuilder: (context, state) => CustomTransitionPage(
              child: RegisterPointScreen(
                pointType: state.extra as String? ?? 'entrada',
              ),
              transitionsBuilder: (_, animation, __, child) => SlideTransition(
                position: Tween<Offset>(
                  begin: const Offset(0, 1),
                  end: Offset.zero,
                ).animate(
                    CurvedAnimation(parent: animation, curve: Curves.easeOut)),
                child: child,
              ),
            ),
          ),
          GoRoute(
            path: 'history',
            name: 'history',
            builder: (_, __) => const HistoryScreen(),
          ),
          GoRoute(
            path: 'balance',
            name: 'balance',
            builder: (_, __) => const BalanceScreen(),
          ),
          GoRoute(
            path: 'profile',
            name: 'profile',
            builder: (_, __) => const ProfileScreen(),
          ),
          GoRoute(
            path: 'settings',
            name: 'settings',
            builder: (_, __) => const SettingsScreen(),
          ),
          GoRoute(
            path: 'edit-requests',
            name: 'edit-requests',
            builder: (_, __) => const EditRequestsScreen(),
          ),
          GoRoute(
            path: 'request-edit',
            name: 'request-edit',
            pageBuilder: (context, state) {
              final record = state.extra as TimeRecordModel;
              return CustomTransitionPage(
                key: state.pageKey,
                child: RequestEditScreen(record: record),
                transitionsBuilder: (_, animation, __, child) =>
                    SlideTransition(
                  position: Tween<Offset>(
                    begin: const Offset(0, 0.1),
                    end: Offset.zero,
                  ).animate(CurvedAnimation(
                      parent: animation, curve: Curves.easeOut)),
                  child: child,
                ),
              );
            },
          ),
          GoRoute(
            path: 'request-add-point',
            name: 'request-add-point',
            pageBuilder: (context, state) {
              final suggestedDate = state.extra as DateTime?;
              return CustomTransitionPage(
                key: state.pageKey,
                child: RequestAddPointScreen(suggestedDate: suggestedDate),
                transitionsBuilder: (_, animation, __, child) =>
                    SlideTransition(
                  position: Tween<Offset>(
                    begin: const Offset(0, 0.1),
                    end: Offset.zero,
                  ).animate(CurvedAnimation(
                      parent: animation, curve: Curves.easeOut)),
                  child: child,
                ),
              );
            },
          ),
          GoRoute(
            path: 'payslips',
            name: 'payslips',
            builder: (_, __) => const PayslipsScreen(),
          ),
          GoRoute(
            path: 'communications',
            name: 'communications',
            builder: (_, __) => const CommunicationsScreen(),
          ),
          GoRoute(
            path: 'vacation',
            name: 'vacation',
            builder: (_, __) => const VacationScreen(),
          ),
          GoRoute(
            path: 'pay-periods',
            name: 'pay-periods',
            builder: (_, __) => const PayPeriodsScreen(),
          ),
          GoRoute(
            path: 'pay-period-detail/:closureId',
            name: 'pay-period-detail',
            builder: (context, state) {
              final id = int.tryParse(
                      state.pathParameters['closureId'] ?? '') ??
                  0;
              return PayPeriodDetailScreen(closureId: id);
            },
          ),
          GoRoute(
            path: 'request-leave',
            name: 'request-leave',
            pageBuilder: (context, state) {
              final balance = state.extra as HourBankBalanceModel;
              return CustomTransitionPage(
                key: state.pageKey,
                child: RequestLeaveScreen(balance: balance),
                transitionsBuilder: (_, animation, __, child) =>
                    SlideTransition(
                  position: Tween<Offset>(
                    begin: const Offset(0, 1),
                    end: Offset.zero,
                  ).animate(CurvedAnimation(
                      parent: animation, curve: Curves.easeOut)),
                  child: child,
                ),
              );
            },
          ),
        ],
      ),
    ],
  );
});
