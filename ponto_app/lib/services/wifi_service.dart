import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:network_info_plus/network_info_plus.dart';

final wifiServiceProvider = Provider<WifiService>((_) => WifiService());

class WifiService {
  final _info = NetworkInfo();

  /// Retorna o SSID da rede Wi-Fi conectada actualmente,
  /// ou null se não estiver conectado ou não tiver permissão.
  Future<String?> getCurrentSsid() async {
    try {
      final ssid = await _info.getWifiName();
      if (ssid == null) return null;
      // Android devolve o SSID entre aspas: "MinhaRede" → MinhaRede
      return ssid.replaceAll('"', '');
    } catch (_) {
      return null;
    }
  }

  /// Verifica se o SSID actual está na lista de permitidos.
  /// Se a lista estiver vazia, qualquer Wi-Fi (ou ausência) é inválido.
  Future<bool> isSsidAllowed(List<String> allowedSsids) async {
    if (allowedSsids.isEmpty) return false;
    final current = await getCurrentSsid();
    if (current == null || current.isEmpty) return false;
    return allowedSsids.any((s) => s.trim() == current.trim());
  }
}
