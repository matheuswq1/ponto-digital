class AppException implements Exception {
  final String message;
  final String type;
  final Map<String, dynamic>? errors;

  const AppException._({
    required this.message,
    required this.type,
    this.errors,
  });

  factory AppException.network(String message) =>
      AppException._(message: message, type: 'network');

  factory AppException.unauthorized(String message) =>
      AppException._(message: message, type: 'unauthorized');

  factory AppException.forbidden(String message) =>
      AppException._(message: message, type: 'forbidden');

  factory AppException.validation(String message, Map<String, dynamic>? errors) =>
      AppException._(message: message, type: 'validation', errors: errors);

  factory AppException.server(String message) =>
      AppException._(message: message, type: 'server');

  factory AppException.unknown(String message) =>
      AppException._(message: message, type: 'unknown');

  bool get isNetwork => type == 'network';
  bool get isUnauthorized => type == 'unauthorized';
  bool get isValidation => type == 'validation';

  String? firstError() {
    if (errors == null || errors!.isEmpty) return null;
    final firstList = errors!.values.first;
    if (firstList is List && firstList.isNotEmpty) {
      return firstList.first.toString();
    }
    return null;
  }

  @override
  String toString() => 'AppException[$type]: $message';
}

