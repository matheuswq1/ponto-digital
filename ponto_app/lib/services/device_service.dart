import 'dart:io';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';
import '../core/constants/app_constants.dart';

final deviceServiceProvider = Provider<DeviceService>((_) => DeviceService());

class DeviceService {
  Future<String> getDeviceId() async {
    final prefs = await SharedPreferences.getInstance();
    var id = prefs.getString('${AppConstants.deviceNameKey}_id');
    if (id == null) {
      id = const Uuid().v4();
      await prefs.setString('${AppConstants.deviceNameKey}_id', id);
    }
    return id;
  }

  Future<String> getDeviceName() async {
    return 'Flutter App (${Platform.operatingSystem})';
  }

  String get platform => Platform.operatingSystem;
  bool get isAndroid => Platform.isAndroid;
  bool get isIOS => Platform.isIOS;
}

