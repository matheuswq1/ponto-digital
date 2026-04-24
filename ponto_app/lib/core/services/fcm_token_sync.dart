import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/datasources/auth_datasource.dart';
import 'notification_service.dart';

/// Registra (ou atualiza) o token FCM no backend.
/// Deve ser chamado após o login bem-sucedido.
Future<void> syncFcmToken(Ref ref) async {
  try {
    final token = await NotificationService.getToken();
    if (token == null) return;

    final datasource = ref.read(authDatasourceProvider);
    await datasource.registerDeviceToken(token);

    // Ouve renovações automáticas de token
    NotificationService.onTokenRefresh.listen((newToken) async {
      try {
        await datasource.registerDeviceToken(newToken);
      } catch (e) {
        if (kDebugMode) debugPrint('FCM token refresh sync error: $e');
      }
    });
  } catch (e) {
    if (kDebugMode) debugPrint('syncFcmToken error: $e');
  }
}
