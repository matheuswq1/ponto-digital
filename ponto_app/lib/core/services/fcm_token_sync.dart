import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/datasources/auth_datasource.dart';
import 'notification_service.dart';

/// Registra (ou atualiza) o token FCM no backend.
/// Deve ser chamado após o login bem-sucedido.
Future<void> syncFcmToken(Ref ref) async {
  try {
    String? token = await NotificationService.getToken();
    token ??= await Future.delayed(const Duration(seconds: 2), NotificationService.getToken);
    if (token == null) {
      if (kDebugMode) debugPrint('syncFcmToken: token FCM indisponível (permissões ou Firebase)');
      return;
    }

    final datasource = ref.read(authDatasourceProvider);
    final platform = Platform.isIOS ? 'ios' : 'android';
    await datasource.registerDeviceToken(token, platform: platform);

    // Ouve renovações automáticas de token
    NotificationService.onTokenRefresh.listen((newToken) async {
      try {
        await datasource.registerDeviceToken(newToken, platform: platform);
      } catch (e) {
        if (kDebugMode) debugPrint('FCM token refresh sync error: $e');
      }
    });
  } catch (e) {
    if (kDebugMode) debugPrint('syncFcmToken error: $e');
  }
}
