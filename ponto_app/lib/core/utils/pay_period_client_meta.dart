import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../services/device_service.dart';

/// Metadados enviados ao aceitar/contestar espelho (auditoria no servidor).
Future<Map<String, String>> buildPayPeriodClientMeta(WidgetRef ref) async {
  final device = ref.read(deviceServiceProvider);
  final info = await PackageInfo.fromPlatform();
  final deviceId = await device.getDeviceId();
  return {
    'app_version': info.version,
    'build_number': info.buildNumber,
    'platform': device.platform,
    'device_id': deviceId,
    'locale': Platform.localeName,
  };
}
