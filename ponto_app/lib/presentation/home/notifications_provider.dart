import 'package:flutter_riverpod/flutter_riverpod.dart';

class AppNotification {
  final String id;
  final String title;
  final String body;
  final DateTime createdAt;
  final bool read;
  final String? icon; // 'ponto', 'edit', 'add', 'info', 'warning'

  const AppNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.createdAt,
    this.read = false,
    this.icon,
  });

  AppNotification copyWith({bool? read}) => AppNotification(
        id: id,
        title: title,
        body: body,
        createdAt: createdAt,
        read: read ?? this.read,
        icon: icon,
      );
}

class NotificationsNotifier extends StateNotifier<List<AppNotification>> {
  NotificationsNotifier() : super([]);

  void add(AppNotification notification) {
    state = [notification, ...state.take(49)];
  }

  void markAllRead() {
    state = state.map((n) => n.copyWith(read: true)).toList();
  }

  void markRead(String id) {
    state = state.map((n) => n.id == id ? n.copyWith(read: true) : n).toList();
  }

  void clear() => state = [];

  int get unreadCount => state.where((n) => !n.read).length;
}

final notificationsProvider =
    StateNotifierProvider<NotificationsNotifier, List<AppNotification>>(
  (ref) => NotificationsNotifier(),
);

final unreadNotificationsCountProvider = Provider<int>((ref) {
  final notifications = ref.watch(notificationsProvider);
  return notifications.where((n) => !n.read).length;
});
