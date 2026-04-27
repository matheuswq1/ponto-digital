import 'dart:io';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:go_router/go_router.dart';

/// Canal Android de alta prioridade para alertas de ponto.
const AndroidNotificationChannel _alertChannel = AndroidNotificationChannel(
  'ponto_alerts',
  'Alertas de Ponto',
  description: 'Notificações de atraso, ausência e hora extra.',
  importance: Importance.high,
  playSound: true,
);

final FlutterLocalNotificationsPlugin _localNotifications =
    FlutterLocalNotificationsPlugin();

/// Router global injectado após MaterialApp ser criado.
GoRouter? _router;

/// Manipulador de mensagens em background (top-level, fora de qualquer classe).
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await NotificationService._showLocal(message);
}

class NotificationService {
  NotificationService._();

  /// Registar o router para permitir deep links.
  static void setRouter(GoRouter router) => _router = router;

  static Future<void> init() async {
    // Configuração de notificações locais
    const androidInit = AndroidInitializationSettings('@drawable/ic_notification');
    const iosInit = DarwinInitializationSettings(
      requestAlertPermission: false,
      requestBadgePermission: false,
      requestSoundPermission: false,
    );
    await _localNotifications.initialize(
      const InitializationSettings(android: androidInit, iOS: iosInit),
      onDidReceiveNotificationResponse: _onNotificationTap,
    );

    // Criar canal Android
    await _localNotifications
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(_alertChannel);

    // Permissão FCM
    final messaging = FirebaseMessaging.instance;
    final settings = await messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );
    if (kDebugMode) {
      debugPrint('FCM permission: ${settings.authorizationStatus}');
    }

    // Foreground: exibe notificação local pois FCM não mostra por padrão no Android
    FirebaseMessaging.onMessage.listen(_showLocal);

    // Background tap (app em background mas não fechado)
    FirebaseMessaging.onMessageOpenedApp.listen(_handleTap);

    // App aberto pelo toque numa notificação após estar fechado
    final initial = await messaging.getInitialMessage();
    if (initial != null) _handleTap(initial);

    if (Platform.isIOS) {
      await messaging.setForegroundNotificationPresentationOptions(
        alert: true,
        badge: true,
        sound: true,
      );
    }
  }

  /// Retorna o FCM token do dispositivo para enviar ao backend.
  static Future<String?> getToken() async {
    return FirebaseMessaging.instance.getToken();
  }

  /// Ouve mudanças de token (rotação automática).
  static Stream<String> get onTokenRefresh =>
      FirebaseMessaging.instance.onTokenRefresh;

  static Future<void> _showLocal(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) return;

    final androidDetails = AndroidNotificationDetails(
      _alertChannel.id,
      _alertChannel.name,
      channelDescription: _alertChannel.description,
      importance: Importance.high,
      priority: Priority.high,
      icon: '@drawable/ic_notification',
    );
    const iosDetails = DarwinNotificationDetails();

    await _localNotifications.show(
      notification.hashCode,
      notification.title,
      notification.body,
      NotificationDetails(android: androidDetails, iOS: iosDetails),
      payload: message.data['type'],
    );
  }

  static void _onNotificationTap(NotificationResponse response) {
    final payload = response.payload;
    if (payload != null) _navigateForType(payload, {});
  }

  static void _handleTap(RemoteMessage message) {
    final type = message.data['type'] as String?;
    if (type != null) _navigateForType(type, message.data);
  }

  /// Mapeia o tipo de notificação para uma rota do GoRouter.
  static void _navigateForType(String type, Map<String, dynamic> data) {
    final router = _router;
    if (router == null) return;

    switch (type) {
      case 'hour_bank_approved':
      case 'hour_bank_rejected':
        router.go('/home/balance');
      case 'time_record_edit.approve':
      case 'time_record_edit.reject':
      case 'edit_request_approved':
      case 'edit_request_rejected':
        router.go('/home/edit-requests');
      default:
        router.go('/home');
    }
  }
}
