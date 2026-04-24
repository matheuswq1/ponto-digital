import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../core/network/api_client.dart';
import '../../core/constants/app_constants.dart';
import '../../core/errors/app_exception.dart';
import '../models/user_model.dart';

final authDatasourceProvider = Provider<AuthDatasource>(
  (ref) => AuthDatasource(ref.read(apiClientProvider)),
);

class AuthDatasource {
  final ApiClient _api;

  AuthDatasource(this._api);

  Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final deviceName = prefs.getString(AppConstants.deviceNameKey) ?? 'Flutter App';

      final response = await _api.post('/login', data: {
        'email': email,
        'password': password,
        'device_name': deviceName,
      });

      final token = response.data['token'] as String;
      final user = UserModel.fromJson(response.data['user']);
      final faceEnrolled = response.data['face_enrolled'] as bool? ?? false;

      await prefs.setString(AppConstants.tokenKey, token);
      await prefs.setString(AppConstants.userKey, user.toJsonString());

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
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(AppConstants.tokenKey);
      await prefs.remove(AppConstants.userKey);
    }
  }

  Future<UserModel?> getStoredUser() async {
    final prefs = await SharedPreferences.getInstance();
    final userJson = prefs.getString(AppConstants.userKey);
    if (userJson == null) return null;
    return UserModel.fromJsonString(userJson);
  }

  Future<bool> hasToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(AppConstants.tokenKey) != null;
  }

  /// Salva as credenciais localmente para o "Lembrar de mim".
  Future<void> saveCredentials(String email, String password) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(AppConstants.rememberMeKey, true);
    await prefs.setString(AppConstants.savedEmailKey, email);
    await prefs.setString(AppConstants.savedPasswordKey, password);
  }

  /// Remove as credenciais salvas.
  Future<void> clearCredentials() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(AppConstants.rememberMeKey);
    await prefs.remove(AppConstants.savedEmailKey);
    await prefs.remove(AppConstants.savedPasswordKey);
  }

  /// Retorna as credenciais salvas ou null se não houver.
  Future<Map<String, String>?> getSavedCredentials() async {
    final prefs = await SharedPreferences.getInstance();
    final remember = prefs.getBool(AppConstants.rememberMeKey) ?? false;
    if (!remember) return null;
    final email = prefs.getString(AppConstants.savedEmailKey);
    final password = prefs.getString(AppConstants.savedPasswordKey);
    if (email == null || password == null) return null;
    return {'email': email, 'password': password};
  }

  /// Regista token FCM no Laravel (chame após [firebase_messaging] obter o token).
  Future<void> registerDeviceToken(String token, {String platform = 'android'}) async {
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

  AppException _handleError(dynamic e) {
    if (e is AppException) return e;
    if (e is DioException && e.error is AppException) return e.error as AppException;
    return AppException.unknown(e.toString());
  }
}

