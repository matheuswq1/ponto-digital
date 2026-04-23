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
import '../../presentation/edits/request_edit_screen.dart';
import '../../presentation/totem/totem_screen.dart';
import '../../data/models/time_record_model.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authProvider);

  return GoRouter(
    initialLocation: '/login',
    redirect: (context, state) {
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
                ).animate(CurvedAnimation(parent: animation, curve: Curves.easeOut)),
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
                transitionsBuilder: (_, animation, __, child) => SlideTransition(
                  position: Tween<Offset>(
                    begin: const Offset(0, 0.1),
                    end: Offset.zero,
                  ).animate(CurvedAnimation(parent: animation, curve: Curves.easeOut)),
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

