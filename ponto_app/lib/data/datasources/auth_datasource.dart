import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../core/constants/app_constants.dart';
import '../../core/errors/app_exception.dart';
import '../../core/network/api_client.dart';
import '../../core/storage/secure_storage_service.dart';
import '../models/user_model.dart';

final authDatasourceProvider = Provider<AuthDatasource>(
  (ref) => AuthDatasource(
    ref.read(apiClientProvider),
    ref.read(secureStorageServiceProvider),
  ),
);

class AuthDatasource {
  final ApiClient _api;
  final SecureStorageService _secureStorage;

  AuthDatasource(this._api, this._secureStorage);

  Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final deviceName =
          prefs.getString(AppConstants.deviceNameKey) ?? 'Flutter App';

      final response = await _api.post('/login', data: {
        'email': email,
        'password': password,
        'device_name': deviceName,
      });

      final token = response.data['token'] as String;
      final user = UserModel.fromJson(response.data['user']);
      final faceEnrolled = response.data['face_enrolled'] as bool? ?? false;

      await _secureStorage.writeToken(token);
      await _secureStorage.writeUserJson(user.toJsonString());

      return {'token': token, 'user': user, 'face_enrolled': faceEnrolled};
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> logout() async {
    try {
      await _api.post('/logout');
    } catch (_) {
      // Mesmo com erro, limpa o token local
    } finally {
      await _secureStorage.clearSession();
    }
  }

  Future<UserModel?> getStoredUser() async {
    final userJson = await _secureStorage.readUserJson();
    if (userJson == null) return null;
    return UserModel.fromJsonString(userJson);
  }

  Future<bool> hasToken() async {
    return await _secureStorage.readToken() != null;
  }

  /// Salva as credenciais em armazenamento seguro para o "Lembrar de mim".
  /// Guarda também o nome do utilizador para exibição segura na tela de login.
  Future<void> saveCredentials(String email, String password,
      {String? name}) async {
    await _secureStorage.saveCredentials(email, password, name: name);
  }

  /// Remove as credenciais salvas.
  Future<void> clearCredentials() async {
    await _secureStorage.clearCredentials();
  }

  /// Retorna as credenciais salvas ou null se não houver.
  Future<Map<String, String>?> getSavedCredentials() async {
    return _secureStorage.getSavedCredentials();
  }

  /// Regista token FCM no Laravel (chame após [firebase_messaging] obter o token).
  Future<void> registerDeviceToken(String token,
      {String platform = 'android'}) async {
    await _api.post('/device-tokens', data: {
      'token': token,
      'platform': platform,
    });
  }

  /// Remove o token de push no servidor.
  Future<void> unregisterDeviceToken(String token) async {
    await _api.delete(
      '/device-tokens',
      data: {'token': token},
    );
  }

  Future<UserModel> getMe() async {
    try {
      final response = await _api.get('/me');
      return UserModel.fromJson(response.data['user']);
    } catch (e) {
      throw _handleError(e);
    }
  }

  /// Persiste dados do utilizador no armazenamento local (para sessão offline).
  Future<void> persistUser(UserModel user) async {
    await _secureStorage.writeUserJson(user.toJsonString());
  }

  AppException _handleError(dynamic e) {
    if (e is AppException) return e;
    if (e is DioException && e.error is AppException) {
      return e.error as AppException;
    }
    return AppException.unknown(e.toString());
  }
}
