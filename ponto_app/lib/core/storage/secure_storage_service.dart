import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../constants/app_constants.dart';

final secureStorageServiceProvider = Provider<SecureStorageService>(
  (_) => SecureStorageService(),
);

class SecureStorageService {
  static const _migrationKey = 'secure_storage_migrated_v1';

  final FlutterSecureStorage _storage;

  SecureStorageService({
    FlutterSecureStorage? storage,
  }) : _storage = storage ?? const FlutterSecureStorage();

  Future<void> migrateLegacyIfNeeded() async {
    final migrated = await _storage.read(key: _migrationKey);
    if (migrated == '1') return;

    final prefs = await SharedPreferences.getInstance();
    await _copyLegacyStringIfPresent(prefs, AppConstants.tokenKey);
    await _copyLegacyStringIfPresent(prefs, AppConstants.userKey);
    await _copyLegacyStringIfPresent(prefs, AppConstants.savedEmailKey);
    await _copyLegacyStringIfPresent(prefs, AppConstants.savedPasswordKey);
    await _copyLegacyStringIfPresent(prefs, AppConstants.savedNameKey);

    final remember = prefs.getBool(AppConstants.rememberMeKey);
    if (remember != null &&
        await _storage.read(key: AppConstants.rememberMeKey) == null) {
      await _storage.write(
        key: AppConstants.rememberMeKey,
        value: remember ? '1' : '0',
      );
    }

    await prefs.remove(AppConstants.tokenKey);
    await prefs.remove(AppConstants.userKey);
    await prefs.remove(AppConstants.savedEmailKey);
    await prefs.remove(AppConstants.savedPasswordKey);
    await prefs.remove(AppConstants.savedNameKey);
    await prefs.remove(AppConstants.rememberMeKey);
    await _storage.write(key: _migrationKey, value: '1');
  }

  Future<void> writeToken(String token) async {
    await migrateLegacyIfNeeded();
    await _storage.write(key: AppConstants.tokenKey, value: token);
  }

  Future<String?> readToken() async {
    await migrateLegacyIfNeeded();
    return _storage.read(key: AppConstants.tokenKey);
  }

  Future<void> writeUserJson(String userJson) async {
    await migrateLegacyIfNeeded();
    await _storage.write(key: AppConstants.userKey, value: userJson);
  }

  Future<String?> readUserJson() async {
    await migrateLegacyIfNeeded();
    return _storage.read(key: AppConstants.userKey);
  }

  Future<void> clearSession() async {
    await migrateLegacyIfNeeded();
    await _storage.delete(key: AppConstants.tokenKey);
    await _storage.delete(key: AppConstants.userKey);
  }

  Future<void> saveCredentials(
    String email,
    String password, {
    String? name,
  }) async {
    await migrateLegacyIfNeeded();
    await _storage.write(key: AppConstants.rememberMeKey, value: '1');
    await _storage.write(key: AppConstants.savedEmailKey, value: email);
    await _storage.write(key: AppConstants.savedPasswordKey, value: password);
    if (name != null) {
      await _storage.write(key: AppConstants.savedNameKey, value: name);
    }
  }

  Future<void> clearCredentials() async {
    await migrateLegacyIfNeeded();
    await _storage.delete(key: AppConstants.rememberMeKey);
    await _storage.delete(key: AppConstants.savedEmailKey);
    await _storage.delete(key: AppConstants.savedPasswordKey);
    await _storage.delete(key: AppConstants.savedNameKey);
  }

  Future<Map<String, String>?> getSavedCredentials() async {
    await migrateLegacyIfNeeded();
    final remember = await _storage.read(key: AppConstants.rememberMeKey);
    if (remember != '1') return null;

    final email = await _storage.read(key: AppConstants.savedEmailKey);
    final password = await _storage.read(key: AppConstants.savedPasswordKey);
    final name = await _storage.read(key: AppConstants.savedNameKey);
    if (email == null || password == null) return null;

    return {
      'email': email,
      'password': password,
      if (name != null) 'name': name,
    };
  }

  Future<void> _copyLegacyStringIfPresent(
    SharedPreferences prefs,
    String key,
  ) async {
    final current = await _storage.read(key: key);
    if (current != null) return;

    final legacy = prefs.getString(key);
    if (legacy != null) {
      await _storage.write(key: key, value: legacy);
    }
  }
}
