import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:network_info_plus/network_info_plus.dart';
import 'package:permission_handler/permission_handler.dart';

final wifiServiceProvider = Provider<WifiService>((_) => WifiService());

/// Resultado da verificação de Wi-Fi com detalhes para diagnóstico.
enum WifiCheckResult {
  /// SSID está na lista autorizada.
  allowed,
  /// SSID não está na lista autorizada.
  denied,
  /// Permissão de localização negada — não foi possível ler o SSID.
  locationPermissionDenied,
  /// Localização desactivada no dispositivo — não foi possível ler o SSID.
  locationDisabled,
  /// Não foi possível ler o SSID por outro motivo.
  unableToRead,
}

class WifiService {
  final _info = NetworkInfo();

  /// Retorna o SSID da rede Wi-Fi conectada actualmente.
  ///
  /// No Android 8.1+ requer:
  ///  - Permissão ACCESS_FINE_LOCATION concedida
  ///  - Localização (GPS) activa no dispositivo
  ///
  /// Devolve null se não for possível obter o SSID.
  Future<String?> getCurrentSsid() async {
    try {
      // Garantir permissão de localização (necessária para SSID no Android 8.1+)
      final status = await Permission.location.status;
      if (!status.isGranted) {
        final result = await Permission.location.request();
        if (!result.isGranted) return null;
      }

      final ssid = await _info.getWifiName();
      if (ssid == null || ssid.isEmpty) return null;

      // Android devolve o SSID entre aspas: "MinhaRede" → MinhaRede
      final clean = ssid.replaceAll('"', '').trim();

      // Android devolve '<unknown ssid>' quando a localização está desligada
      if (clean == '<unknown ssid>' || clean == 'unknown ssid') return null;

      return clean.isEmpty ? null : clean;
    } catch (_) {
      return null;
    }
  }

  /// Verificação completa com diagnóstico detalhado.
  Future<WifiCheckResult> checkSsid(List<String> allowedSsids) async {
    if (allowedSsids.isEmpty) return WifiCheckResult.denied;

    // 1. Verificar permissão
    final status = await Permission.location.status;
    if (status.isPermanentlyDenied) return WifiCheckResult.locationPermissionDenied;
    if (!status.isGranted) {
      final result = await Permission.location.request();
      if (!result.isGranted) return WifiCheckResult.locationPermissionDenied;
    }

    // 2. Obter SSID
    try {
      final ssid = await _info.getWifiName();
      final clean = (ssid ?? '').replaceAll('"', '').trim();

      if (clean.isEmpty || clean == '<unknown ssid>' || clean == 'unknown ssid') {
        // SSID vazio geralmente significa localização desligada no Android
        return WifiCheckResult.locationDisabled;
      }

      final allowed = allowedSsids.any(
        (s) => s.trim().toLowerCase() == clean.toLowerCase(),
      );
      return allowed ? WifiCheckResult.allowed : WifiCheckResult.denied;
    } catch (_) {
      return WifiCheckResult.unableToRead;
    }
  }

  /// Versão simplificada — retorna true apenas se o SSID estiver autorizado.
  Future<bool> isSsidAllowed(List<String> allowedSsids) async {
    if (allowedSsids.isEmpty) return false;
    final result = await checkSsid(allowedSsids);
    return result == WifiCheckResult.allowed;
  }
}
