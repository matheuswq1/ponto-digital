import 'dart:async';
import 'dart:io';
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/safe_camera_dispose.dart';
import '../../presentation/auth/auth_provider.dart';
import '../../services/totem_service.dart';
import 'pin_enroll_flow.dart';

// ─── Tempo de inatividade antes de bloquear (minutos) ────────────────────────
const _kInactivityMinutes = 3;

enum _TotemStep {
  idle,
  scanning,
  identified,
  registering,
  success,
  failed,
}

class TotemScreen extends ConsumerStatefulWidget {
  const TotemScreen({super.key});

  @override
  ConsumerState<TotemScreen> createState() => _TotemScreenState();
}

class _TotemScreenState extends ConsumerState<TotemScreen> {
  CameraController? _cam;
  bool _camReady = false;
  _TotemStep _step = _TotemStep.idle;
  TotemIdentifyResult? _identified;
  TotemPointResult? _lastPoint;
  String? _errorMsg;
  Timer? _resetTimer;
  Timer? _clockTimer;
  Timer? _inactivityTimer;
  String _clock = '';

  // ── Bloqueio ────────────────────────────────────────────────────────────────
  bool _locked = false;
  bool _showLogoutAfterUnlock = false;

  @override
  void initState() {
    super.initState();
    _startClock();
    _initCamera();
    _resetInactivity();
  }

  @override
  void dispose() {
    _resetTimer?.cancel();
    _clockTimer?.cancel();
    _inactivityTimer?.cancel();
    final c = _cam;
    _cam = null;
    scheduleDisposeCamera(c);
    super.dispose();
  }

  // ── Relógio ─────────────────────────────────────────────────────────────────
  void _startClock() {
    _updateClock();
    _clockTimer = Timer.periodic(const Duration(seconds: 1), (_) => _updateClock());
  }

  void _updateClock() {
    if (!mounted) return;
    setState(() => _clock = DateFormat('HH:mm:ss').format(DateTime.now()));
  }

  // ── Câmera ──────────────────────────────────────────────────────────────────
  Future<void> _initCamera() async {
    final cameras = await availableCameras();
    if (cameras.isEmpty) return;
    final front = cameras.firstWhere(
      (c) => c.lensDirection == CameraLensDirection.front,
      orElse: () => cameras.first,
    );
    _cam = CameraController(
      front,
      ResolutionPreset.low,
      enableAudio: false,
      imageFormatGroup: ImageFormatGroup.jpeg,
    );
    await _cam!.initialize();
    if (!mounted) return;
    setState(() => _camReady = true);
  }

  // ── Inatividade ──────────────────────────────────────────────────────────────
  void _resetInactivity() {
    _inactivityTimer?.cancel();
    _inactivityTimer = Timer(
      const Duration(minutes: _kInactivityMinutes),
      _lock,
    );
  }

  void _onUserInteraction() {
    if (_locked) return;
    _resetInactivity();
  }

  void _lock() {
    if (!mounted) return;
    _resetTimer?.cancel();
    setState(() {
      _locked = true;
      _showLogoutAfterUnlock = false;
      _step = _TotemStep.idle;
    });
  }

  // ── Desbloqueio via swipe ─────────────────────────────────────────────────────
  void _unlock({bool showLogout = false}) {
    setState(() {
      _locked = false;
      _showLogoutAfterUnlock = showLogout;
    });
    _resetInactivity();
    if (showLogout) {
      Future.delayed(const Duration(seconds: 10), () {
        if (mounted) setState(() => _showLogoutAfterUnlock = false);
      });
    }
  }

  // ── Scan ─────────────────────────────────────────────────────────────────────
  Future<void> _scan() async {
    _onUserInteraction();
    if (_step != _TotemStep.idle || _cam == null || !_cam!.value.isInitialized) return;

    setState(() => _step = _TotemStep.scanning);

    try {
      final xf = await _cam!.takePicture();
      final result = await ref.read(totemServiceProvider).identify(File(xf.path));

      if (!mounted) return;

      if (result.match && result.employeeId != null) {
        setState(() {
          _identified = result;
          _step = _TotemStep.identified;
        });
        if (result.nextTypes.length == 1) {
          await _registerPoint(result.nextTypes.first);
        }
      } else {
        setState(() {
          _step = _TotemStep.failed;
          _errorMsg = result.message ?? 'Rosto não reconhecido.';
        });
        _scheduleReset(seconds: 3);
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _step = _TotemStep.failed;
          _errorMsg = 'Erro ao conectar. Tente novamente.';
        });
        _scheduleReset(seconds: 3);
      }
    }
  }

  Future<void> _registerPoint(String type) async {
    _onUserInteraction();
    if (_identified == null) return;
    setState(() => _step = _TotemStep.registering);

    final result = await ref.read(totemServiceProvider).registerPoint(
          employeeId: _identified!.employeeId!,
          type: type,
        );

    if (!mounted) return;

    if (result != null) {
      setState(() {
        _lastPoint = result;
        _step = _TotemStep.success;
      });
      _scheduleReset(seconds: 5);
    } else {
      setState(() {
        _step = _TotemStep.failed;
        _errorMsg = 'Não foi possível registrar o ponto. Tente novamente.';
      });
      _scheduleReset(seconds: 4);
    }
  }

  void _scheduleReset({int seconds = 4}) {
    _resetTimer?.cancel();
    _resetTimer = Timer(Duration(seconds: seconds), () {
      if (mounted) setState(() => _step = _TotemStep.idle);
    });
  }

  Future<void> _logout() async {
    await ref.read(authProvider.notifier).logout();
  }

  /// Abre o fluxo de enroll/atualização facial via PIN.
  void _openPinEnroll() {
    _onUserInteraction();
    showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (_) => PinEnrollFlow(camera: _camReady ? _cam : null),
    );
  }

  String _typeLabel(String type) => switch (type) {
        'entrada' => 'Entrada',
        'saida' => 'Saída',
        _ => type,
      };

  Color _typeColor(String type) => switch (type) {
        'entrada' => AppColors.entrada,
        'saida' => AppColors.saida,
        _ => AppColors.primary,
      };

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    final circleDiameter = size.width * 0.72;

    return GestureDetector(
      onTap: _onUserInteraction,
      onPanDown: (_) => _onUserInteraction(),
      behavior: HitTestBehavior.translucent,
      child: Scaffold(
        backgroundColor: const Color(0xFF0A0F1E),
        body: Stack(
          children: [
            // ── Conteúdo principal ──────────────────────────────────────────
            SafeArea(
              child: Column(
                children: [
                  // ── Header ─────────────────────────────────────────────────
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.monitor, color: Colors.white54, size: 16),
                            const SizedBox(width: 6),
                            Text(
                              ref.watch(authProvider).user?.name ?? 'Totem',
                              style: const TextStyle(
                                color: Colors.white54,
                                fontSize: 13,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                        Text(
                          _clock,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 22,
                            fontWeight: FontWeight.bold,
                            fontFeatures: [FontFeature.tabularFigures()],
                          ),
                        ),
                        // Cadeado — toque longo desbloqueia e mostra logout
                        GestureDetector(
                          onLongPress: () => _unlock(showLogout: true),
                          child: AnimatedSwitcher(
                            duration: const Duration(milliseconds: 250),
                            child: Icon(
                              _locked ? Icons.lock : Icons.lock_open_outlined,
                              key: ValueKey(_locked),
                              color: _locked ? AppColors.error : Colors.white24,
                              size: 20,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),

                  const Spacer(),

                  // ── Círculo da câmera ────────────────────────────────────────
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 400),
                    width: circleDiameter,
                    height: circleDiameter,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: _locked
                            ? Colors.white12
                            : _step == _TotemStep.scanning
                                ? AppColors.primary
                                : _step == _TotemStep.failed
                                    ? AppColors.error
                                    : Colors.white24,
                        width: _step == _TotemStep.scanning ? 4 : 2,
                      ),
                      boxShadow: _step == _TotemStep.scanning && !_locked
                          ? [BoxShadow(color: AppColors.primary.withValues(alpha: 0.4), blurRadius: 24, spreadRadius: 4)]
                          : [],
                    ),
                    child: ClipOval(
                      child: _camReady && _cam != null
                          ? _buildCameraPreview()
                          : Container(color: const Color(0xFF1A2035)),
                    ),
                  ),

                  const SizedBox(height: 24),

                  // ── Instrução / feedback ────────────────────────────────────
                  AnimatedSwitcher(
                    duration: const Duration(milliseconds: 300),
                    child: _locked
                        ? const _StatusText(
                            key: ValueKey('locked'),
                            icon: Icons.lock,
                            iconColor: Colors.white38,
                            text: 'Totem bloqueado',
                            subtext: 'Pressione o cadeado para desbloquear',
                          )
                        : _buildCenterContent(),
                  ),

                  const Spacer(),

                  // ── Botão de scan (só no idle e desbloqueado) ──────────────
                  if (!_locked && _step == _TotemStep.idle) ...[
                    GestureDetector(
                      onTap: _scan,
                      child: Container(
                        width: 72,
                        height: 72,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: AppColors.primary,
                          boxShadow: [
                            BoxShadow(
                              color: AppColors.primary.withValues(alpha: 0.45),
                              blurRadius: 20,
                              spreadRadius: 2,
                            ),
                          ],
                        ),
                        child: const Icon(Icons.face, color: Colors.white, size: 36),
                      ),
                    ),
                    const SizedBox(height: 10),
                    const Text(
                      'Toque para identificar',
                      style: TextStyle(color: Colors.white38, fontSize: 13),
                    ),
                  ],

                  // ── Botões de administração (aparecem após long press no cadeado) ─
                  if (_showLogoutAfterUnlock && !_locked) ...[
                    const SizedBox(height: 8),
                    // Divisor visual
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 48),
                      child: Row(
                        children: [
                          Expanded(child: Divider(color: Colors.white12)),
                          const Padding(
                            padding: EdgeInsets.symmetric(horizontal: 8),
                            child: Text('Administração',
                                style: TextStyle(
                                    color: Colors.white24, fontSize: 11)),
                          ),
                          Expanded(child: Divider(color: Colors.white12)),
                        ],
                      ),
                    ),
                    const SizedBox(height: 8),
                    TextButton.icon(
                      onPressed: _openPinEnroll,
                      icon: const Icon(Icons.face_retouching_natural,
                          size: 16, color: Colors.white54),
                      label: const Text(
                        'Cadastrar / Atualizar rosto',
                        style: TextStyle(color: Colors.white54, fontSize: 13),
                      ),
                    ),
                    TextButton.icon(
                      onPressed: _logout,
                      icon: const Icon(Icons.logout, size: 16,
                          color: AppColors.error),
                      label: const Text(
                        'Sair do Totem',
                        style: TextStyle(color: AppColors.error, fontSize: 13),
                      ),
                    ),
                  ],

                  const SizedBox(height: 32),
                ],
              ),
            ),

            // ── Overlay de bloqueio — swipe para cima desbloqueia ────────────
            if (_locked)
              GestureDetector(
                onVerticalDragEnd: (details) {
                  // Swipe para cima (velocidade negativa no eixo Y)
                  if (details.primaryVelocity != null && details.primaryVelocity! < -200) {
                    _unlock();
                  }
                },
                child: Container(
                  color: Colors.black54,
                  alignment: Alignment.bottomCenter,
                  padding: const EdgeInsets.only(bottom: 48),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.keyboard_arrow_up, color: Colors.white54, size: 32),
                      const SizedBox(height: 4),
                      const Text(
                        'Deslize para cima para desbloquear',
                        style: TextStyle(color: Colors.white54, fontSize: 13),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildCameraPreview() {
    final cam = _cam!;
    final aspect = 1.0 / cam.value.aspectRatio;
    return AspectRatio(
      aspectRatio: 1,
      child: FittedBox(
        fit: BoxFit.cover,
        clipBehavior: Clip.hardEdge,
        child: SizedBox(
          width: cam.value.previewSize?.height ?? 480,
          height: cam.value.previewSize?.width ?? 640,
          child: AspectRatio(
            aspectRatio: aspect,
            child: CameraPreview(cam),
          ),
        ),
      ),
    );
  }

  Widget _buildCenterContent() {
    switch (_step) {
      case _TotemStep.idle:
        return const _StatusText(
          key: ValueKey('idle'),
          icon: Icons.face_outlined,
          iconColor: Colors.white38,
          text: 'Posicione seu rosto no círculo',
          subtext: '',
        );
      case _TotemStep.scanning:
        return const _StatusText(
          key: ValueKey('scanning'),
          icon: Icons.radar,
          iconColor: AppColors.primary,
          text: 'Identificando...',
          subtext: 'Aguarde',
          showSpinner: true,
        );
      case _TotemStep.identified:
        final r = _identified!;
        if (r.nextTypes.isEmpty || r.isComplete) {
          return _StatusText(
            key: const ValueKey('complete'),
            icon: Icons.check_circle,
            iconColor: AppColors.success,
            text: 'Olá, ${r.firstName}!',
            subtext: 'Sua jornada de hoje está completa.',
          );
        }
        return _IdentifiedPanel(
          key: const ValueKey('identified'),
          result: r,
          onRegister: _registerPoint,
          onCancel: () => setState(() => _step = _TotemStep.idle),
          typeLabel: _typeLabel,
          typeColor: _typeColor,
        );
      case _TotemStep.registering:
        return const _StatusText(
          key: ValueKey('registering'),
          icon: Icons.fingerprint,
          iconColor: AppColors.success,
          text: 'Registrando ponto...',
          subtext: '',
          showSpinner: true,
        );
      case _TotemStep.success:
        return _SuccessPanel(
          key: const ValueKey('success'),
          point: _lastPoint!,
          typeColor: _typeColor,
        );
      case _TotemStep.failed:
        return _StatusText(
          key: const ValueKey('failed'),
          icon: Icons.face_retouching_off,
          iconColor: AppColors.error,
          text: _errorMsg ?? 'Rosto não reconhecido.',
          subtext: 'Tente novamente.',
        );
    }
  }
}

// ─── Widgets auxiliares ───────────────────────────────────────────────────────

class _StatusText extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String text;
  final String subtext;
  final bool showSpinner;

  const _StatusText({
    super.key,
    required this.icon,
    required this.iconColor,
    required this.text,
    required this.subtext,
    this.showSpinner = false,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (showSpinner)
          SizedBox(
            width: 32,
            height: 32,
            child: CircularProgressIndicator(color: iconColor, strokeWidth: 2.5),
          )
        else
          Icon(icon, color: iconColor, size: 32),
        const SizedBox(height: 10),
        Text(
          text,
          textAlign: TextAlign.center,
          style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600),
        ),
        if (subtext.isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(subtext, style: const TextStyle(color: Colors.white54, fontSize: 13)),
        ],
      ],
    );
  }
}

class _IdentifiedPanel extends StatelessWidget {
  final TotemIdentifyResult result;
  final void Function(String) onRegister;
  final VoidCallback onCancel;
  final String Function(String) typeLabel;
  final Color Function(String) typeColor;

  const _IdentifiedPanel({
    super.key,
    required this.result,
    required this.onRegister,
    required this.onCancel,
    required this.typeLabel,
    required this.typeColor,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.person_outline, color: Colors.white70, size: 28),
        const SizedBox(height: 6),
        Text(
          'Olá, ${result.firstName}!',
          style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.white),
        ),
        if ((result.employeeCargo ?? '').isNotEmpty) ...[
          const SizedBox(height: 2),
          Text(result.employeeCargo!, style: const TextStyle(color: Colors.white54, fontSize: 12)),
        ],
        const SizedBox(height: 16),
        ...result.nextTypes.map((type) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: SizedBox(
                width: 240,
                child: ElevatedButton(
                  onPressed: () => onRegister(type),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: typeColor(type),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  child: Text(
                    typeLabel(type),
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            )),
        TextButton(
          onPressed: onCancel,
          child: const Text('Cancelar', style: TextStyle(color: Colors.white38, fontSize: 12)),
        ),
      ],
    );
  }
}

class _SuccessPanel extends StatelessWidget {
  final TotemPointResult point;
  final Color Function(String) typeColor;

  const _SuccessPanel({super.key, required this.point, required this.typeColor});

  @override
  Widget build(BuildContext context) {
    final color = typeColor(point.type);
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(Icons.check_circle, color: color, size: 40),
        const SizedBox(height: 8),
        Text(
          point.employeeName,
          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.white),
        ),
        const SizedBox(height: 4),
        Text(point.typeLabel, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: color)),
        const SizedBox(height: 2),
        Text(
          DateFormat('HH:mm').format(DateTime.parse(point.datetime).toLocal()),
          style: const TextStyle(fontSize: 30, fontWeight: FontWeight.bold, color: Colors.white),
        ),
        const SizedBox(height: 4),
        const Text('Ponto registrado!', style: TextStyle(color: Colors.white54, fontSize: 12)),
      ],
    );
  }
}
