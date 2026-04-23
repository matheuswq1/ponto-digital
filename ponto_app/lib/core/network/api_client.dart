import 'dart:io';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:pretty_dio_logger/pretty_dio_logger.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../constants/app_constants.dart';
import '../errors/app_exception.dart';

final apiClientProvider = Provider<ApiClient>((ref) => ApiClient());

class ApiClient {
  late final Dio _dio;

  ApiClient() {
    _dio = Dio(BaseOptions(
      baseUrl: AppConstants.baseUrl,
      connectTimeout: Duration(seconds: AppConstants.connectTimeout),
      receiveTimeout: Duration(seconds: AppConstants.receiveTimeout),
      headers: {
        'Accept': 'application/json',
        // Content-Type não é fixado aqui — o Dio define automaticamente:
        // application/json para Map, multipart/form-data para FormData
      },
    ));

    _dio.interceptors.addAll([
      _AuthInterceptor(),
      _ErrorInterceptor(),
      PrettyDioLogger(
        requestHeader: false,
        requestBody: true,
        responseBody: true,
        error: true,
        compact: true,
      ),
    ]);

    // Em debug, aceita qualquer certificado para dev local (HTTP/HTTPS)
    if (kDebugMode && _dio.httpClientAdapter is IOHttpClientAdapter) {
      (_dio.httpClientAdapter as IOHttpClientAdapter).createHttpClient = () {
        final client = HttpClient();
        client.badCertificateCallback = (_, __, ___) => true;
        return client;
      };
    }
  }

  Dio get dio => _dio;

  Future<Response> get(String path, {Map<String, dynamic>? params}) =>
      _dio.get(path, queryParameters: params);

  Future<Response> post(String path, {dynamic data, FormData? formData}) =>
      _dio.post(path, data: formData ?? data);

  Future<Response> put(String path, {dynamic data}) =>
      _dio.put(path, data: data);

  Future<Response> delete(String path, {dynamic data}) =>
      _dio.delete(path, data: data);
}

class _AuthInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(AppConstants.tokenKey);
    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    // Define Content-Type apenas para requests que não sejam FormData
    if (options.data is! FormData) {
      options.headers['Content-Type'] = 'application/json';
    }
    handler.next(options);
  }
}

class _ErrorInterceptor extends Interceptor {
  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    final response = err.response;

    if (response == null) {
      // Loga o erro real para diagnóstico
      if (kDebugMode) {
        debugPrint('[ApiClient] Erro sem resposta: type=${err.type} | error=${err.error} | msg=${err.message}');
      }

      final isTimeout = err.type == DioExceptionType.connectionTimeout ||
          err.type == DioExceptionType.receiveTimeout ||
          err.type == DioExceptionType.sendTimeout;

      final cause = '${err.error ?? ''} ${err.message ?? ''}'.toLowerCase();
      final isCleartext = cause.contains('cleartext') || cause.contains('plain text');
      final isRefused = cause.contains('refused') || cause.contains('econnrefused') || cause.contains('connection refused');

      String msg;
      if (isCleartext) {
        msg = 'Tráfego HTTP bloqueado. Verifique a configuração de rede.';
      } else if (isRefused) {
        msg = 'Conexão recusada. Verifique se o servidor está rodando.';
      } else if (isTimeout) {
        msg = 'Tempo de conexão esgotado. Verifique sua rede.';
      } else {
        msg = 'Sem conexão com o servidor. Verifique sua rede.';
      }

      handler.next(DioException(
        requestOptions: err.requestOptions,
        error: AppException.network(msg),
        type: err.type,
      ));
      return;
    }

    final data = response.data;
    final message = data is Map ? (data['message'] ?? 'Erro desconhecido.') : 'Erro desconhecido.';
    final errors = data is Map ? (data['errors'] as Map<String, dynamic>?) : null;

    switch (response.statusCode) {
      case 401:
        handler.next(DioException(
          requestOptions: err.requestOptions,
          error: AppException.unauthorized(message),
          response: response,
        ));
      case 403:
        handler.next(DioException(
          requestOptions: err.requestOptions,
          error: AppException.forbidden(message),
          response: response,
        ));
      case 422:
        handler.next(DioException(
          requestOptions: err.requestOptions,
          error: AppException.validation(message, errors),
          response: response,
        ));
      default:
        handler.next(DioException(
          requestOptions: err.requestOptions,
          error: AppException.server(message),
          response: response,
        ));
    }
  }
}
