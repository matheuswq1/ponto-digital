class AppConstants {
  // 10.0.2.2  = localhost do host no emulador Android
  // 192.168.x = IP real da máquina para dispositivo físico na mesma rede Wi-Fi
  // Emulador: 10.0.2.2. Celular físico: troque a URL abaixo para http://<IP-DA-MAQUINA>/projeto-ponto-web/public/api/v1
  static const String baseUrl = 'https://ponto.approsamistica.com/api/v1';

  static const String tokenKey = 'auth_token';
  static const String userKey = 'auth_user';
  static const String deviceNameKey = 'device_name';
  static const String themeModeKey = 'app_theme_mode';
  /// Exige desbloqueio biométrico após reabrir o app (se houver token)
  static const String biometricUnlockKey = 'biometric_unlock_enabled';

  static const int connectTimeout = 30;
  static const int receiveTimeout = 60;

  static const int maxOfflineRecords = 50;
  static const int syncRetryAttempts = 3;

  static const double defaultGeofenceRadius = 500;

  static const Map<String, String> pointTypeLabels = {
    'entrada': 'Entrada',
    'saida': 'Saída',
  };

  static const Map<String, String> pointTypeIcons = {
    'entrada': 'assets/icons/entrada.svg',
    'saida': 'assets/icons/saida.svg',
  };
}
