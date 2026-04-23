import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../data/datasources/auth_datasource.dart';
import '../../data/models/user_model.dart';
import '../../core/constants/app_constants.dart';
import '../../core/errors/app_exception.dart';

// Estado de autenticação
enum AuthStatus {
  initial,
  loading,
  authenticated,
  unauthenticated,
  error,
  /// Token existe; aguarda biometria antes de considerar autenticado
  awaitingBiometric,
}

class AuthState {
  final AuthStatus status;
  final UserModel? user;
  final String? errorMessage;

  const AuthState({
    this.status = AuthStatus.initial,
    this.user,
    this.errorMessage,
  });

  AuthState copyWith({
    AuthStatus? status,
    UserModel? user,
    String? errorMessage,
  }) => AuthState(
        status: status ?? this.status,
        user: user ?? this.user,
        errorMessage: errorMessage,
      );

  bool get isAuthenticated => status == AuthStatus.authenticated;
  bool get isLoading => status == AuthStatus.loading;
  bool get isAwaitingBiometric => status == AuthStatus.awaitingBiometric;
  /// Tem token (autenticado ou aguardando biometria)
  bool get hasSession =>
      status == AuthStatus.authenticated || status == AuthStatus.awaitingBiometric;
}

class AuthNotifier extends StateNotifier<AuthState> {
  final AuthDatasource _datasource;

  AuthNotifier(this._datasource) : super(const AuthState()) {
    _checkStoredAuth();
  }

  Future<void> _checkStoredAuth() async {
    state = state.copyWith(status: AuthStatus.loading);
    try {
      final hasToken = await _datasource.hasToken();
      if (!hasToken) {
        state = state.copyWith(status: AuthStatus.unauthenticated);
        return;
      }
      final user = await _datasource.getStoredUser();
      if (user == null) {
        state = state.copyWith(status: AuthStatus.unauthenticated);
        return;
      }
      final prefs = await SharedPreferences.getInstance();
      if (prefs.getBool(AppConstants.biometricUnlockKey) == true) {
        state = state.copyWith(status: AuthStatus.awaitingBiometric, user: user);
        return;
      }
      state = state.copyWith(status: AuthStatus.authenticated, user: user);
    } catch (_) {
      state = state.copyWith(status: AuthStatus.unauthenticated);
    }
  }

  void completeBiometricUnlock() {
    if (state.user == null) return;
    state = state.copyWith(status: AuthStatus.authenticated, user: state.user);
  }

  Future<Map<String, dynamic>> loginFull(String email, String password) async {
    state = state.copyWith(status: AuthStatus.loading, errorMessage: null);
    try {
      final result = await _datasource.login(email, password);
      state = state.copyWith(
        status: AuthStatus.authenticated,
        user: result['user'] as UserModel,
      );
      return result;
    } on AppException catch (e) {
      state = state.copyWith(
        status: AuthStatus.error,
        errorMessage: e.firstError() ?? e.message,
      );
      return {};
    }
  }

  Future<bool> login(String email, String password) async {
    final result = await loginFull(email, password);
    return result.isNotEmpty;
  }

  Future<void> logout() async {
    await _datasource.logout();
    state = const AuthState(status: AuthStatus.unauthenticated);
  }

  void updateUser(UserModel user) {
    state = state.copyWith(user: user);
  }

  void clearError() {
    state = state.copyWith(status: AuthStatus.unauthenticated, errorMessage: null);
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>(
  (ref) => AuthNotifier(ref.read(authDatasourceProvider)),
);

