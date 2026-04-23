import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';

final locationServiceProvider = Provider<LocationService>((_) => LocationService());

class LocationResult {
  final double latitude;
  final double longitude;
  final double accuracy;
  final bool isMock;

  const LocationResult({
    required this.latitude,
    required this.longitude,
    required this.accuracy,
    this.isMock = false,
  });
}

class LocationService {
  Future<bool> requestPermission() async {
    final status = await Permission.location.request();
    return status.isGranted;
  }

  Future<bool> isPermissionGranted() async {
    final status = await Permission.location.status;
    return status.isGranted;
  }

  Future<LocationResult?> getCurrentLocation() async {
    final hasPermission = await requestPermission();
    if (!hasPermission) return null;

    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) return null;

    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 15),
        ),
      );

      return LocationResult(
        latitude: position.latitude,
        longitude: position.longitude,
        accuracy: position.accuracy,
        isMock: position.isMocked,
      );
    } catch (_) {
      return null;
    }
  }

  double distanceBetween(
    double lat1, double lon1,
    double lat2, double lon2,
  ) {
    return Geolocator.distanceBetween(lat1, lon1, lat2, lon2);
  }

  bool isWithinGeofence({
    required double userLat,
    required double userLon,
    required double centerLat,
    required double centerLon,
    required double radiusMeters,
  }) {
    final distance = distanceBetween(userLat, userLon, centerLat, centerLon);
    return distance <= radiusMeters;
  }
}

