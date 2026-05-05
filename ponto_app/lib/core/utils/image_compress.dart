import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:image/image.dart' as img;

/// Comprime e redimensiona uma imagem para upload.
/// Roda em isolate separado via [compute] — não bloqueia a UI.
///
/// Parâmetros:
/// - [maxDim]: dimensão máxima do lado maior (padrão 640 px)
/// - [quality]: qualidade JPEG 0-100 (padrão 72)
///
/// Retorna o arquivo comprimido (ou o original em caso de falha).
Future<File> compressForUpload(
  File input, {
  int maxDim = 640,
  int quality = 72,
}) async {
  try {
    final outPath = await compute(
      _doCompress,
      {'path': input.path, 'maxDim': maxDim, 'quality': quality},
    );
    return File(outPath);
  } catch (_) {
    // Em caso de falha na compressão, usa o arquivo original
    return input;
  }
}

/// Função top-level para rodar no isolate.
Future<String> _doCompress(Map<String, dynamic> params) async {
  final path = params['path'] as String;
  final maxDim = params['maxDim'] as int;
  final quality = params['quality'] as int;

  final bytes = await File(path).readAsBytes();
  final image = img.decodeImage(bytes);
  if (image == null) return path;

  // Redimensiona apenas se necessário
  img.Image resized = image;
  if (image.width > maxDim || image.height > maxDim) {
    if (image.width >= image.height) {
      resized = img.copyResize(image, width: maxDim);
    } else {
      resized = img.copyResize(image, height: maxDim);
    }
  }

  final compressed = img.encodeJpg(resized, quality: quality);
  final outPath = '${path}_c.jpg';
  await File(outPath).writeAsBytes(compressed);
  return outPath;
}
