import 'dart:convert';

class UserModel {
  final int id;
  final String name;
  final String email;
  final String role;
  final bool active;
  final int? companyId;
  final CompanyModel? company;
  final EmployeeModel? employee;

  const UserModel({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.active,
    this.companyId,
    this.company,
    this.employee,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) => UserModel(
        id: json['id'],
        name: json['name'],
        email: json['email'],
        role: json['role'],
        active: json['active'] ?? true,
        companyId: json['company_id'] as int?,
        company: json['company'] != null
            ? CompanyModel.fromJson(json['company'] as Map<String, dynamic>)
            : null,
        employee: json['employee'] != null
            ? EmployeeModel.fromJson(json['employee'])
            : null,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'email': email,
        'role': role,
        'active': active,
        if (companyId != null) 'company_id': companyId,
        if (company != null) 'company': company!.toJson(),
        'employee': employee?.toJson(),
      };

  String toJsonString() => jsonEncode(toJson());

  factory UserModel.fromJsonString(String jsonStr) =>
      UserModel.fromJson(jsonDecode(jsonStr));

  bool get isAdmin => role == 'admin';
  bool get isGestor => role == 'gestor';
  bool get isFuncionario => role == 'funcionario';
  bool get isTotem => role == 'totem';

  String get firstName => name.split(' ').first;
}

class EmployeeModel {
  final int id;
  final int companyId;
  final String cpf;
  final String cargo;
  final String? department;
  final int weeklyHours;
  final bool active;
  final bool faceEnrolled;
  final bool appPunchDisabled;
  final CompanyModel? company;

  const EmployeeModel({
    required this.id,
    required this.companyId,
    required this.cpf,
    required this.cargo,
    this.department,
    required this.weeklyHours,
    required this.active,
    this.faceEnrolled = false,
    this.appPunchDisabled = false,
    this.company,
  });

  factory EmployeeModel.fromJson(Map<String, dynamic> json) => EmployeeModel(
        id: json['id'],
        companyId: json['company_id'],
        cpf: json['cpf'],
        cargo: json['cargo'],
        department: json['department'],
        weeklyHours: json['weekly_hours'] ?? 44,
        active: json['active'] ?? true,
        faceEnrolled: json['face_enrolled'] ?? false,
        appPunchDisabled: json['app_punch_disabled'] ?? false,
        company: json['company'] != null
            ? CompanyModel.fromJson(json['company'])
            : null,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'company_id': companyId,
        'cpf': cpf,
        'cargo': cargo,
        'department': department,
        'weekly_hours': weeklyHours,
        'active': active,
        'face_enrolled': faceEnrolled,
        'app_punch_disabled': appPunchDisabled,
        'company': company?.toJson(),
      };

  EmployeeModel copyWith({bool? faceEnrolled, bool? appPunchDisabled}) => EmployeeModel(
        id: id,
        companyId: companyId,
        cpf: cpf,
        cargo: cargo,
        department: department,
        weeklyHours: weeklyHours,
        active: active,
        faceEnrolled: faceEnrolled ?? this.faceEnrolled,
        appPunchDisabled: appPunchDisabled ?? this.appPunchDisabled,
        company: company,
      );
}

class CompanyLocationModel {
  final int id;
  final String name;
  final String? address;
  final double latitude;
  final double longitude;
  final int radiusMeters;
  final bool active;

  const CompanyLocationModel({
    required this.id,
    required this.name,
    this.address,
    required this.latitude,
    required this.longitude,
    this.radiusMeters = 300,
    this.active = true,
  });

  factory CompanyLocationModel.fromJson(Map<String, dynamic> json) =>
      CompanyLocationModel(
        id: json['id'],
        name: json['name'] ?? '',
        address: json['address'],
        latitude: _toDouble(json['latitude']) ?? 0,
        longitude: _toDouble(json['longitude']) ?? 0,
        radiusMeters: (json['radius_meters'] as num?)?.toInt() ?? 300,
        active: json['active'] ?? true,
      );

  static double? _toDouble(dynamic v) {
    if (v == null) return null;
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v);
    return null;
  }
}

class CompanyModel {
  final int id;
  final String name;
  final String cnpj;
  final bool requirePhoto;
  final bool requireGeolocation;
  final int maxDailyRecords;
  final GeofenceModel? geofence;
  // Múltiplas geocercas (novo sistema)
  final List<CompanyLocationModel> geofences;
  // Anti-fraude
  final bool blockMockLocation;
  final bool requireWifi;
  final List<String> allowedWifiSsids;
  final String fraudAction; // 'warn' | 'block'

  const CompanyModel({
    required this.id,
    required this.name,
    required this.cnpj,
    required this.requirePhoto,
    required this.requireGeolocation,
    this.maxDailyRecords = 10,
    this.geofence,
    this.geofences = const [],
    this.blockMockLocation = false,
    this.requireWifi = false,
    this.allowedWifiSsids = const [],
    this.fraudAction = 'warn',
  });

  /// True se existir pelo menos uma geocerca activa (nova ou legada).
  bool get hasAnyGeofence =>
      geofences.any((g) => g.active) ||
      (geofence?.enabled == true &&
          geofence?.latitude != null &&
          geofence?.longitude != null);

  factory CompanyModel.fromJson(Map<String, dynamic> json) {
    final settings = json['settings'] as Map<String, dynamic>?;
    final geofenceData = json['geofence'] as Map<String, dynamic>?;
    final rawGeofences = json['geofences'];
    final geofencesList = rawGeofences is List
        ? rawGeofences
            .map((e) => CompanyLocationModel.fromJson(e as Map<String, dynamic>))
            .where((g) => g.active)
            .toList()
        : <CompanyLocationModel>[];
    final rawSsids = settings?['allowed_wifi_ssids'];
    final ssids = rawSsids is List
        ? rawSsids.map((e) => e.toString()).toList()
        : <String>[];
    return CompanyModel(
      id: json['id'],
      name: json['name'],
      cnpj: json['cnpj'],
      requirePhoto: settings?['require_photo'] ?? true,
      requireGeolocation: settings?['require_geolocation'] ?? false,
      maxDailyRecords: (json['max_daily_records'] as num?)?.toInt() ?? 10,
      geofence: geofenceData != null ? GeofenceModel.fromJson(geofenceData) : null,
      geofences: geofencesList,
      blockMockLocation: settings?['block_mock_location'] ?? false,
      requireWifi: settings?['require_wifi'] ?? false,
      allowedWifiSsids: ssids,
      fraudAction: settings?['fraud_action'] ?? 'warn',
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'cnpj': cnpj,
        'max_daily_records': maxDailyRecords,
        'settings': {
          'require_photo': requirePhoto,
          'require_geolocation': requireGeolocation,
        },
        'geofence': geofence?.toJson(),
        'geofences': geofences.map((g) => {
          'id': g.id,
          'name': g.name,
          'latitude': g.latitude,
          'longitude': g.longitude,
          'radius_meters': g.radiusMeters,
          'active': g.active,
        }).toList(),
        'block_mock_location': blockMockLocation,
        'require_wifi': requireWifi,
        'allowed_wifi_ssids': allowedWifiSsids,
        'fraud_action': fraudAction,
      };
}

class GeofenceModel {
  final double? latitude;
  final double? longitude;
  final int radiusMeters;
  final bool enabled;

  const GeofenceModel({
    this.latitude,
    this.longitude,
    required this.radiusMeters,
    required this.enabled,
  });

  factory GeofenceModel.fromJson(Map<String, dynamic> json) => GeofenceModel(
        latitude: _toDouble(json['latitude']),
        longitude: _toDouble(json['longitude']),
        radiusMeters: json['radius_meters'] ?? 500,
        enabled: json['enabled'] ?? false,
      );

  static double? _toDouble(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
  }

  Map<String, dynamic> toJson() => {
        'latitude': latitude,
        'longitude': longitude,
        'radius_meters': radiusMeters,
        'enabled': enabled,
      };
}

