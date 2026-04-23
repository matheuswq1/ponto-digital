class TimeRecordModel {
  final int? id;
  final int employeeId;
  final String type;
  final String typeLabel;
  final DateTime datetime;
  final String datetimeLocal;
  final double? latitude;
  final double? longitude;
  final String? photoUrl;
  final String status;
  final bool isMockLocation;
  final bool offline;
  final bool isEdited;
  final String? deviceId;

  const TimeRecordModel({
    this.id,
    required this.employeeId,
    required this.type,
    required this.typeLabel,
    required this.datetime,
    required this.datetimeLocal,
    this.latitude,
    this.longitude,
    this.photoUrl,
    required this.status,
    this.isMockLocation = false,
    this.offline = false,
    this.isEdited = false,
    this.deviceId,
  });

  factory TimeRecordModel.fromJson(Map<String, dynamic> json) {
    final loc = json['location'] as Map<String, dynamic>?;
    return TimeRecordModel(
      id: json['id'],
      employeeId: json['employee_id'],
      type: json['type'],
      typeLabel: json['type_label'] ?? json['type'],
      datetime: DateTime.parse(json['datetime']).toLocal(),
      datetimeLocal: json['datetime_local'] ?? '',
      latitude: _toDouble(loc?['latitude']),
      longitude: _toDouble(loc?['longitude']),
      photoUrl: json['photo_url'],
      status: json['status'] ?? 'pendente',
      isMockLocation: json['is_mock_location'] ?? false,
      offline: json['offline'] ?? false,
      isEdited: json['is_edited'] ?? false,
    );
  }

  // Converte num ou String para double com segurança
  static double? _toDouble(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'employee_id': employeeId,
        'type': type,
        'type_label': typeLabel,
        'datetime': datetime.toUtc().toIso8601String(),
        'datetime_local': datetimeLocal,
        'location': {
          'latitude': latitude,
          'longitude': longitude,
        },
        'photo_url': photoUrl,
        'status': status,
        'is_mock_location': isMockLocation,
        'offline': offline,
        'is_edited': isEdited,
      };

  // Para salvar no SQLite local
  Map<String, dynamic> toLocalDb() => {
        'employee_id': employeeId,
        'type': type,
        'datetime': datetime.toUtc().toIso8601String(),
        'latitude': latitude,
        'longitude': longitude,
        'photo_url': photoUrl,
        'device_id': deviceId,
        'is_mock_location': isMockLocation ? 1 : 0,
        'synced': 0,
      };

  TimeRecordModel copyWith({String? photoUrl, bool? offline}) => TimeRecordModel(
        id: id,
        employeeId: employeeId,
        type: type,
        typeLabel: typeLabel,
        datetime: datetime,
        datetimeLocal: datetimeLocal,
        latitude: latitude,
        longitude: longitude,
        photoUrl: photoUrl ?? this.photoUrl,
        status: status,
        isMockLocation: isMockLocation,
        offline: offline ?? this.offline,
        isEdited: isEdited,
        deviceId: deviceId,
      );
}

class TodayStatusModel {
  final String date;
  final List<TimeRecordModel> records;
  final String? nextType;
  final List<String> nextTypes;
  final bool isComplete;
  final int maxDailyRecords;

  const TodayStatusModel({
    required this.date,
    required this.records,
    this.nextType,
    required this.nextTypes,
    required this.isComplete,
    this.maxDailyRecords = 10,
  });

  factory TodayStatusModel.fromJson(Map<String, dynamic> json) => TodayStatusModel(
        date: json['date'],
        records: (json['records'] as List)
            .map((r) => TimeRecordModel.fromJson(r))
            .toList(),
        nextType: json['next_type'],
        nextTypes: List<String>.from(json['next_types'] ?? []),
        isComplete: json['is_complete'] ?? false,
        maxDailyRecords: (json['max_daily_records'] as num?)?.toInt() ?? 10,
      );

  bool get hasEntrada => records.any((r) => r.type == 'entrada');
  bool get hasSaida => records.any((r) => r.type == 'saida');

  /// Retorna pares (entrada, saida?) para exibição na tela
  List<({TimeRecordModel entrada, TimeRecordModel? saida})> get pairs {
    final result = <({TimeRecordModel entrada, TimeRecordModel? saida})>[];
    TimeRecordModel? openEntrada;
    for (final r in records) {
      if (r.type == 'entrada') {
        openEntrada = r;
      } else if (r.type == 'saida' && openEntrada != null) {
        result.add((entrada: openEntrada, saida: r));
        openEntrada = null;
      }
    }
    if (openEntrada != null) {
      result.add((entrada: openEntrada, saida: null));
    }
    return result;
  }
}

