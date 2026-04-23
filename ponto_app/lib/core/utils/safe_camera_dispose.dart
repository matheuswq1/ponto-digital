import 'dart:async';

import 'package:camera/camera.dart';
import 'package:flutter/services.dart';

/// No Android com implementation `camera_android_camerax`, o [dispose] pode
/// lançar [PlatformException] se o preview ainda não associou o surface
/// (`releaseFlutterSurfaceTexture`). Tratamos como teardown seguro.
Future<void> safeDisposeCamera(CameraController? controller) async {
  if (controller == null) return;
  try {
    await controller.dispose();
  } on PlatformException catch (_) {
    // Ignorado: race conhecido do CameraX ao fechar o ecrã / dialog.
  } catch (_) {
    // Outros erros raros no encerramento da câmara.
  }
}

/// Para usar em [State.dispose] (não-async): agenda o dispose sem bloquear.
void scheduleDisposeCamera(CameraController? controller) {
  unawaited(safeDisposeCamera(controller));
}
