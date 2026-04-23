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
        'company': company?.toJson(),
      };

  EmployeeModel copyWith({bool? faceEnrolled}) => EmployeeModel(
        id: id,
        companyId: companyId,
        cpf: cpf,
        cargo: cargo,
        department: department,
        weeklyHours: weeklyHours,
        active: active,
        faceEnrolled: faceEnrolled ?? this.faceEnrolled,
        company: company,
      );
}

class CompanyModel {
  final int id;
  final String name;
  final String cnpj;
  final bool requirePhoto;
  final bool requireGeolocation;
  final GeofenceModel? geofence;

  const CompanyModel({
    required this.id,
    required this.name,
    required this.cnpj,
    required this.requirePhoto,
    required this.requireGeolocation,
    this.geofence,
  });

  factory CompanyModel.fromJson(Map<String, dynamic> json) {
    final settings = json['settings'] as Map<String, dynamic>?;
    final geofenceData = json['geofence'] as Map<String, dynamic>?;
    return CompanyModel(
      id: json['id'],
      name: json['name'],
      cnpj: json['cnpj'],
      requirePhoto: settings?['require_photo'] ?? true,
      requireGeolocation: settings?['require_geolocation'] ?? false,
      geofence: geofenceData != null ? GeofenceModel.fromJson(geofenceData) : null,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'cnpj': cnpj,
        'settings': {
          'require_photo': requirePhoto,
          'require_geolocation': requireGeolocation,
        },
        'geofence': geofence?.toJson(),
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

